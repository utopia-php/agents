<?php

namespace Utopia\Agents\Schema;

/**
 * Schema Object, represents a JSON Schema Object
 *
 * @link https://json-schema.org/understanding-json-schema/reference/object#object
 */
class SchemaObject
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $properties;

    /**
     * @param array<string, array<string, mixed>> $properties
     */
    public function __construct(array $properties = [])
    {
        $this->properties = $properties;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getProperty(string $name): ?array
    {
        return $this->properties[$name] ?? null;
    }

    /**
     * Add a property to the object
     *
     * @param  string  $name - name of the property
     * @param  array<string, mixed>  $property - property definition (must be defined in JSON Schema format)
     *
     * @link https://json-schema.org/understanding-json-schema/reference/object#properties
     *
     * @return self
     */
    public function addProperty(string $name, array $property): self
    {
        $this->properties[$name] = $property;

        return $this;
    }

    public function removeProperty(string $name): self
    {
        unset($this->properties[$name]);

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getNames(): array
    {
        return array_keys($this->properties);
    }
}
