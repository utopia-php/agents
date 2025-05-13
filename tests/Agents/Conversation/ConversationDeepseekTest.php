<?php

namespace Utopia\Tests\Agents\Conversation;

use Utopia\Agents\Adapter;
use Utopia\Agents\Adapters\Deepseek;
use Utopia\Agents\Messages\Text;
use Utopia\Agents\Roles\User;
use Utopia\Agents\Schema;
use Utopia\Agents\Schema\SchemaObject;

class ConversationDeepseekTest extends ConversationBase
{
    protected function createAdapter(): Adapter
    {
        $apiKey = getenv('LLM_KEY_DEEPSEEK');

        if ($apiKey === false || empty($apiKey)) {
            throw new \RuntimeException('LLM_KEY_DEEPSEEK environment variable is not set');
        }

        return new Deepseek(
            $apiKey,
            Deepseek::MODEL_DEEPSEEK_CHAT,
            1024,
            1.0
        );
    }

    protected function getAgentDescription(): string
    {
        return 'Test Deepseek Agent Description';
    }

    public function testSchema(): void
    {
        $object = new SchemaObject();
        $object->addProperty('location', [
            'type' => SchemaObject::TYPE_STRING,
            'description' => 'The city and state, e.g. San Francisco, CA',
        ]);
        $object->addProperty('unit', [
            'type' => SchemaObject::TYPE_STRING,
            'enum' => ['celsius', 'fahrenheit'],
            'description' => 'The unit of temperature, either "celsius" or "fahrenheit"',
        ]);

        $schema = new Schema(
            'get_weather',
            'Get the current weather in a given location in well structured JSON',
            $object,
            $object->getNames()
        );

        $this->agent->setSchema($schema);

        $messages = $this->conversation
            ->message(new User('user-2', 'Test User'), new Text('What is the weather in San Francisco in celsius?'))
            ->send();

        $content = $messages->getContent();
        $this->assertIsString($content, 'Message content must be a string');

        $json = json_decode($content, true);
        $this->assertNotNull($json, 'JSON decoding failed');
        $this->assertIsArray($json, 'Decoded content must be an array');

        $this->assertArrayHasKey('location', $json);
        $this->assertArrayHasKey('unit', $json);

        $this->assertIsString($json['location'], 'Location must be a string');
        $this->assertIsString($json['unit'], 'Unit must be a string');

        $this->assertStringContainsString('San Francisco', $json['location']);
        $this->assertEquals('celsius', $json['unit']);
    }
}
