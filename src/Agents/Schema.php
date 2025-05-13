<?php

namespace Utopia\Agents;

use Utopia\Agents\Schema\SchemaObject;

class Schema
{
    protected string $name;

    protected string $description;

    /**
     * @var SchemaObject Properties following JSON Schema format
     */
    protected SchemaObject $object;

    /**
     * @var array<int, string> List of required property names
     */
    protected array $required;

    /**
     * @param  string  $name - name of the schema
     * @param  string  $description - description of the schema
     * @param  SchemaObject  $object a Schema object
     * @param  array<int, string>  $required - array of required properties
     */
    public function __construct(
        string $name,
        string $description,
        SchemaObject $object,
        array $required = []
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->object = $object;
        $this->required = $required;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return SchemaObject
     */
    public function getObject(): SchemaObject
    {
        return $this->object;
    }

    /**
     * @return array<int, string>
     */
    public function getRequired(): array
    {
        return $this->required;
    }

    /**
     * Convert the schema to an array compatible with JSON Schema
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'input_schema' => [
                'type' => 'object',
                'properties' => $this->object->getProperties(),
                'required' => $this->required,
            ],
        ];
    }
}
