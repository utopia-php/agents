<?php

namespace Utopia\Agents;

class Schema
{
    protected string $name;

    protected string $description;

    /**
     * @var array<string, mixed> Properties following JSON Schema format
     */
    protected array $properties;

    protected string $type;

    /**
     * @var array<int, string> List of required property names
     */
    protected array $required;

    /**
     * @param  string  $name - name of the schema
     * @param  string  $description - description of the schema
     * @param  string  $type - array, boolean, null, integer, object, string
     * @param  array<string, mixed>  $properties must follow JSON Schema format - https://json-schema.org/understanding-json-schema/
     * @param  array<int, string>  $required - array of required properties
     */
    public function __construct(
        string $name,
        string $description,
        string $type = 'object',
        array $properties = [],
        array $required = []
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->type = $type;
        $this->properties = $properties;
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

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return $this->properties;
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
                'type' => $this->type,
                'properties' => $this->properties,
                'required' => $this->required,
            ],
        ];
    }
}
