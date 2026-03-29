<?php

namespace Utopia\Agents;

class Tool
{
    protected string $name;

    protected string $description;

    /**
     * JSON Schema-like parameters object.
     *
     * @var array<string, mixed>
     */
    protected array $schema;

    /**
     * @var callable
     */
    protected $handler;

    /**
     * @param  array<string, mixed>|null  $schema
     */
    public function __construct(
        string $name,
        callable $handler,
        string $description = '',
        ?array $schema = null
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->handler = $handler;
        $this->schema = $schema ?? $this->inferSchema($handler);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function desc(): string
    {
        return $this->description;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return $this->schema;
    }

    /**
     * @return array<string, mixed>
     */
    public function def(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $this->schema,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function run(array $arguments = []): mixed
    {
        $this->validateArguments($arguments);

        return $this->invoke($arguments);
    }

    /**
     * Minimal JSON Schema validation for tool arguments.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function validateArguments(array $arguments): void
    {
        $type = $this->schema['type'] ?? null;
        if ($type !== null && $type !== 'object') {
            throw new \InvalidArgumentException('Tool parameters schema must be an object schema');
        }

        $properties = $this->schema['properties'] ?? [];
        if (! is_array($properties)) {
            $properties = [];
        }

        $required = $this->schema['required'] ?? [];
        if (is_array($required)) {
            foreach ($required as $key) {
                if (! is_string($key) || ! array_key_exists($key, $arguments)) {
                    throw new \InvalidArgumentException('Missing required tool argument: '.(string) $key);
                }
            }
        }

        $additionalProperties = $this->schema['additionalProperties'] ?? true;
        if ($additionalProperties === false) {
            foreach (array_keys($arguments) as $key) {
                if (! is_string($key)) {
                    throw new \InvalidArgumentException('Tool arguments must be an object with string keys');
                }

                if (! array_key_exists($key, $properties)) {
                    throw new \InvalidArgumentException('Unexpected tool argument: '.$key);
                }
            }
        }

        foreach ($properties as $key => $definition) {
            if (! is_string($key) || ! array_key_exists($key, $arguments)) {
                continue;
            }

            if (! is_array($definition)) {
                continue;
            }

            $expectedType = $definition['type'] ?? null;
            if (! is_string($expectedType)) {
                continue;
            }

            if (! $this->matchesType($arguments[$key], $expectedType)) {
                throw new \InvalidArgumentException(
                    'Invalid type for tool argument "'.$key.'". Expected '.$expectedType
                );
            }
        }
    }

    protected function matchesType(mixed $value, string $expectedType): bool
    {
        return match ($expectedType) {
            'string' => is_string($value),
            'number' => is_float($value) || is_int($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value) && ! array_is_list($value),
            default => true,
        };
    }

    public function getName(): string
    {
        return $this->name();
    }

    public function getDescription(): string
    {
        return $this->desc();
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->schema();
    }

    /**
     * @return array<string, mixed>
     */
    public function toDefinition(): array
    {
        return $this->def();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function execute(array $arguments = []): mixed
    {
        return $this->run($arguments);
    }

    /**
     * @return array<string, mixed>
     */
    protected function inferSchema(callable $handler): array
    {
        $reflection = $this->reflect($handler);
        $params = $reflection->getParameters();

        if (count($params) === 1 && $this->isBagParam($params[0])) {
            return [
                'type' => 'object',
                'properties' => [],
                'required' => [],
                'additionalProperties' => true,
            ];
        }

        $properties = [];
        $required = [];

        foreach ($params as $param) {
            if ($param->isVariadic()) {
                continue;
            }

            $name = $param->getName();
            $properties[$name] = [
                'type' => $this->jsonType($param->getType()),
            ];

            if (! $param->isOptional() && ! $param->allowsNull()) {
                $required[] = $name;
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }

    protected function reflect(callable $handler): \ReflectionFunctionAbstract
    {
        if (is_array($handler)) {
            return new \ReflectionMethod($handler[0], $handler[1]);
        }

        if (is_string($handler) && str_contains($handler, '::')) {
            [$class, $method] = explode('::', $handler, 2);

            return new \ReflectionMethod($class, $method);
        }

        if (is_object($handler) && ! $handler instanceof \Closure) {
            return new \ReflectionMethod($handler, '__invoke');
        }

        return new \ReflectionFunction($handler);
    }

    protected function jsonType(?\ReflectionType $type): string
    {
        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if ($unionType->getName() === 'null') {
                    continue;
                }

                return $this->jsonType($unionType);
            }

            return 'string';
        }

        if (! $type instanceof \ReflectionNamedType) {
            return 'string';
        }

        return match ($type->getName()) {
            'int' => 'integer',
            'float' => 'number',
            'bool' => 'boolean',
            'array' => 'array',
            'string' => 'string',
            default => 'string',
        };
    }

    protected function isBagParam(\ReflectionParameter $param): bool
    {
        $type = $param->getType();
        if (! $type instanceof \ReflectionNamedType) {
            return true;
        }

        return in_array($type->getName(), ['array', 'mixed'], true);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function invoke(array $arguments): mixed
    {
        $reflection = $this->reflect($this->handler);
        $params = $reflection->getParameters();

        if (count($params) === 1 && $this->isBagParam($params[0])) {
            return call_user_func($this->handler, $arguments);
        }

        $values = [];
        foreach ($params as $param) {
            $name = $param->getName();

            if ($param->isVariadic()) {
                $value = $arguments[$name] ?? [];
                if (! is_array($value) || ! array_is_list($value)) {
                    $value = [$value];
                }

                foreach ($value as $item) {
                    $values[] = $item;
                }

                continue;
            }

            if (array_key_exists($name, $arguments)) {
                $values[] = $arguments[$name];

                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $values[] = $param->getDefaultValue();

                continue;
            }

            if ($param->allowsNull()) {
                $values[] = null;

                continue;
            }

            throw new \InvalidArgumentException('Missing required tool argument: '.$name);
        }

        return call_user_func_array($this->handler, $values);
    }
}
