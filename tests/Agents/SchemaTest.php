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
            'id' => ['type' => SchemaObject::TYPE_STRING],
            'name' => ['type' => SchemaObject::TYPE_STRING],
            'age' => ['type' => SchemaObject::TYPE_INTEGER],
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

    public function testToArray(): void
    {
        $array = $this->schema->toArray();
        $this->assertIsArray($array);
        $this->assertEquals($this->name, $array['name']);
        $this->assertEquals($this->description, $array['description']);
        $this->assertArrayHasKey('input_schema', $array);
        $this->assertIsArray($array['input_schema']);
        $inputSchema = $array['input_schema'];
        $this->assertArrayHasKey('properties', $inputSchema);
        $this->assertArrayHasKey('required', $inputSchema);
        $this->assertEquals($this->object->getProperties(), $inputSchema['properties']);
        $this->assertEquals($this->required, $inputSchema['required']);
    }
}
