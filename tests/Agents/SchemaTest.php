<?php

namespace Tests\Utopia\Agents;

use PHPUnit\Framework\TestCase;
use Utopia\Agents\Schema;
use Utopia\Agents\Schema\SchemaObject;

class SchemaTest extends TestCase
{
    private Schema $schema;

    private SchemaObject $object;

    private string $name = 'TestSchema';

    private string $description = 'A test schema.';

    /**
     * @var array<int, string>
     */
    private array $required = ['id', 'name'];

    protected function setUp(): void
    {
        $this->object = new SchemaObject([
            'id' => [
                'type' => SchemaObject::TYPE_STRING,
                'description' => 'The ID of the user',
            ],
            'name' => [
                'type' => SchemaObject::TYPE_STRING,
                'description' => 'The name of the user',
            ],
            'age' => [
                'type' => SchemaObject::TYPE_INTEGER,
                'description' => 'The age of the user',
            ],
        ]);
        $this->schema = new Schema(
            $this->name,
            $this->description,
            $this->object,
            $this->required
        );
    }

    public function testConstructorAndGetters(): void
    {
        $this->assertEquals($this->name, $this->schema->getName());
        $this->assertEquals($this->description, $this->schema->getDescription());
        $this->assertSame($this->object, $this->schema->getObject());
        $this->assertEquals($this->required, $this->schema->getRequired());
    }

    public function testToSchema(): void
    {
        // Test default (openai) model
        $array = $this->schema->toSchema();
        $this->assertIsArray($array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('schema', $array);
        $schema = $array['schema'];
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('required', $schema);

        // Test anthropic model
        $arrayAnthropic = $this->schema->toSchema(Schema::MODEL_ANTHROPIC);
        $this->assertIsArray($arrayAnthropic);
        $this->assertArrayHasKey('name', $arrayAnthropic);
        $this->assertArrayHasKey('description', $arrayAnthropic);
        $this->assertArrayHasKey('input_schema', $arrayAnthropic);
        $inputSchema = $arrayAnthropic['input_schema'];
        $this->assertArrayHasKey('properties', $inputSchema);
        $this->assertArrayHasKey('required', $inputSchema);
    }

    public function testToJson(): void
    {
        $json = $this->schema->toJson();
        $this->assertIsArray($json);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('name', $json);
        $this->assertArrayHasKey('age', $json);
        $this->assertEquals('The ID of the user (string)', $json['id']);
        $this->assertEquals('The name of the user (string)', $json['name']);
        $this->assertEquals('The age of the user (integer)', $json['age']);
    }
}
