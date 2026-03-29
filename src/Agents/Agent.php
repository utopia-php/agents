<?php

namespace Utopia\Agents;

class Agent
{
    protected string $description;

    /**
     * @var array<string, string|list<string>>
     */
    protected array $instructions;

    protected ?Schema $schema = null;

    /**
     * @var array<string, Tool>
     */
    protected array $tools = [];

    protected Adapter $adapter;

    /**
     * Create a new agent
     *
     * @param  Adapter  $adapter  The AI model adapter to use
     *
     * @throws \Exception
     */
    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->instructions = [];
        $this->description = '';

        $this->adapter->setAgent($this);
    }

    /**
     * Set the agent's description
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set the agent's instructions
     *
     * @param  array<string, string|list<string>>  $instructions
     */
    public function setInstructions(array $instructions): self
    {
        $this->instructions = $instructions;

        return $this;
    }

    /**
     * Add an instruction to the agent
     */
    public function addInstruction(string $name, string $content): self
    {
        $this->instructions[$name] = $content;

        return $this;
    }

    /**
     * Get the agent's description
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get the agent's instructions
     *
     * @return array<string, string|list<string>>
     */
    public function getInstructions(): array
    {
        return $this->instructions;
    }

    /**
     * Get the agent's adapter
     */
    public function getAdapter(): Adapter
    {
        return $this->adapter;
    }

    /**
     * Get the agent's schema
     */
    public function getSchema(): ?Schema
    {
        return $this->schema;
    }

    public function addTool(
        string $name,
        callable $handler,
        string $description = '',
        ?array $schema = null
    ): self {
        $this->tools[$name] = new Tool($name, $handler, $description, $schema);

        return $this;
    }

    public function setTool(Tool $tool): self
    {
        $this->tools[$tool->getName()] = $tool;

        return $this;
    }

    public function removeTool(string $name): self
    {
        unset($this->tools[$name]);

        return $this;
    }

    /**
     * @return list<Tool>
     */
    public function getTools(): array
    {
        return array_values($this->tools);
    }

    public function getTool(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function callTool(string $name, array $arguments = []): mixed
    {
        $tool = $this->getTool($name);
        if ($tool === null) {
            throw new \InvalidArgumentException('Tool not found: '.$name);
        }

        return $tool->run($arguments);
    }

    /**
     * Short alias for addTool().
     */
    public function tool(
        string $name,
        callable $handler,
        string $description = '',
        ?array $schema = null
    ): self {
        return $this->addTool($name, $handler, $description, $schema);
    }

    public function drop(string $name): self
    {
        return $this->removeTool($name);
    }

    /**
     * @return list<Tool>
     */
    public function tools(): array
    {
        return $this->getTools();
    }

    public function find(string $name): ?Tool
    {
        return $this->getTool($name);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function call(string $name, array $arguments = []): mixed
    {
        return $this->callTool($name, $arguments);
    }

    /**
     * Set the agent's schema
     */
    public function setSchema(Schema $schema): self
    {
        if (! $this->adapter->isSchemaSupported()) {
            throw new \Exception('Schema is not supported for this model');
        }

        $this->schema = $schema;

        return $this;
    }

    /**
     * Get embedding for input text using underlying adapter (if supported)
     *
     * @return array{
     *     embedding: array<int, float>,
     *     tokensProcessed: int|null,
     *     totalDuration: int|null ,
     *     modelLoadingDuration: int|null
     * }
     *
     * @throws \Exception
     */
    public function embed(string $text): array
    {
        if (! $this->adapter->getSupportForEmbeddings()) {
            throw new \Exception('This adapter does not support embedding/embedding API.');
        }

        return $this->adapter->embed($text);
    }
}
