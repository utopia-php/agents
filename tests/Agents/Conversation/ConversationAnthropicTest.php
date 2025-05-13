<?php

namespace Utopia\Tests\Agents\Conversation;

use Utopia\Agents\Adapter;
use Utopia\Agents\Adapters\Anthropic;
use Utopia\Agents\Messages\Text;
use Utopia\Agents\Roles\User;
use Utopia\Agents\Schema;

class ConversationAnthropicTest extends ConversationBase
{
    protected function createAdapter(): Adapter
    {
        $apiKey = getenv('LLM_KEY_ANTHROPIC');

        if ($apiKey === false || empty($apiKey)) {
            throw new \RuntimeException('LLM_KEY_ANTHROPIC environment variable is not set');
        }

        return new Anthropic(
            $apiKey,
            Anthropic::MODEL_CLAUDE_3_SONNET,
            1024,
            1.0
        );
    }

    protected function getAgentDescription(): string
    {
        return 'Test Anthropic Agent Description';
    }

    public function testSchema(): void
    {
        $schema = new Schema(
            'get_weather',
            'Get the current weather in a given location in well structured JSON',
            'object',
            [
                'location' => [
                    'type' => 'string',
                    'description' => 'The city and state, e.g. San Francisco, CA',
                ],
                'unit' => [
                    'type' => 'string',
                    'enum' => ['celsius', 'fahrenheit'],
                    'description' => 'The unit of temperature, either "celsius" or "fahrenheit"',
                ],
            ],
            ['location']
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

        // Check that we have content array
        $this->assertArrayHasKey('content', $json);
        $this->assertIsArray($json['content']);
        $this->assertNotEmpty($json['content']);

        // Check first content item is tool_use
        $this->assertArrayHasKey('type', $json['content'][0]);
        $this->assertEquals('tool_use', $json['content'][0]['type']);

        // Check tool_use has input
        $this->assertArrayHasKey('input', $json['content'][0]);
        $this->assertIsArray($json['content'][0]['input']);

        // Now check the actual values
        $input = $json['content'][0]['input'];
        $this->assertArrayHasKey('location', $input);
        $this->assertArrayHasKey('unit', $input);

        $this->assertIsString($input['location'], 'Location must be a string');
        $this->assertIsString($input['unit'], 'Unit must be a string');

        $this->assertStringContainsString('San Francisco', $input['location']);
        $this->assertEquals('celsius', $input['unit']);
    }
}
