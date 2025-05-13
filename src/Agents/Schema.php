<?php

namespace Utopia\Agents;

use Utopia\Agents\Schema\SchemaObject;

class Schema
{
    public const MODEL_OPENAI = 'openai';

    public const MODEL_ANTHROPIC = 'anthropic';

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
     * Convert the schema parameters to a JSON Schema object
     *
     * @param  string  $model - the model to use (anthropic, openai) default is openai
     * @return array<string, mixed>
     */
    public function toSchema(string $model = self::MODEL_OPENAI): array
    {
        if (! in_array($model, $this->getValidModels())) {
            throw new \InvalidArgumentException(
                'Invalid model selected. Must be one of: '.implode(', ', $this->getValidModels())
            );
        }

        if ($model === self::MODEL_ANTHROPIC) {
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

        return [
            'name' => $this->name,
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'properties' => $this->object->getProperties(),
                'required' => $this->required,
                'additionalProperties' => false,
            ],
        ];
    }

    /**
     * Convert the schema parameters to a simple JSON object
     *
     * @return array<string, string>
     */
    public function toJson(): array
    {
        $json = [];
        foreach ($this->object->getProperties() as $property => $value) {
            $description = $value['description'] ?? '';
            $type = $value['type'] ?? '';
            $json[$property] = $description.' ('.$type.')';
        }

        return $json;
    }

    /**
     * Returns an array of valid models
     *
     * @return array<int, string>
     */
    public function getValidModels(): array
    {
        return [
            self::MODEL_OPENAI,
            self::MODEL_ANTHROPIC,
        ];
    }
}
