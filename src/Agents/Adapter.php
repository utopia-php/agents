<?php

namespace Utopia\Agents;

abstract class Adapter
{
    /**
     * The agent instance
     */
    protected ?Agent $agent = null;

    /**
     * Input tokens count
     */
    protected int $inputTokens = 0;

    /**
     * Output tokens count
     */
    protected int $outputTokens = 0;

    /**
     * Cache creation input tokens count
     */
    protected int $cacheCreationInputTokens = 0;

    /**
     * Cache read input tokens count
     */
    protected int $cacheReadInputTokens = 0;

    /**
     * Request timeout in milliseconds
     */
    protected int $timeout = 90000;

    /**
     * Get the adapter name
     */
    abstract public function getName(): string;

    /**
     * Send a message to the AI model
     *
     * @param  array<Message>  $messages  The messages to send to the AI model
     * @param  callable|null  $listener  The listener to call when the message is sent
     * @return Message Response from the AI model
     *
     * @throws \Exception
     */
    abstract public function send(array $messages, ?callable $listener = null): Message;

    /**
     * Get available models for this adapter
     *
     * @return array<string>
     */
    abstract public function getModels(): array;

    /**
     * Get the currently selected model
     */
    abstract public function getModel(): string;

    /**
     * Set the model to use
     */
    abstract public function setModel(string $model): self;

    /**
     * Check if the model supports JSON schema
     */
    abstract public function isSchemaSupported(): bool;

    /**
     * Does this adapter support embeddings?
     */
    abstract public function getSupportForEmbeddings(): bool;

    /**
     * Generate embedding for input text (must be implemented if getSupportForEmbeddings is true)
     *
     * @return array{
     *     embedding: array<int, float>,
     *     tokensProcessed: int|null,
     *     totalDuration: int|null ,
     *     modelLoadingDuration: int|null
     * }
     */
    abstract public function embed(string $text): array;

    /**
     * get embedding dimenion of the current model
     */
    abstract public function getEmbeddingDimension(): int;

    /**
     * Format error message
     *
     * @param  mixed  $json
     */
    abstract protected function formatErrorMessage($json): string;

    /**
     * Get the current agent
     */
    public function getAgent(): ?Agent
    {
        return $this->agent;
    }

    /**
     * Set the agent
     */
    public function setAgent(Agent $agent): self
    {
        $this->agent = $agent;

        return $this;
    }

    /**
     * Get input tokens count
     */
    public function getInputTokens(): int
    {
        return $this->inputTokens;
    }

    /**
     * Get output tokens count
     */
    public function getOutputTokens(): int
    {
        return $this->outputTokens;
    }

    /**
     * Get cache creation input tokens count
     */
    public function getCacheCreationInputTokens(): int
    {
        return $this->cacheCreationInputTokens;
    }

    /**
     * Get cache read input tokens count
     */
    public function getCacheReadInputTokens(): int
    {
        return $this->cacheReadInputTokens;
    }

    /**
     * Add to input tokens count
     */
    public function countInputTokens(int $tokens): self
    {
        $this->inputTokens += $tokens;

        return $this;
    }

    /**
     * Add to output tokens count
     */
    public function countOutputTokens(int $tokens): self
    {
        $this->outputTokens += $tokens;

        return $this;
    }

    /**
     * Add to cache creation input tokens count
     */
    public function countCacheCreationInputTokens(int $tokens): self
    {
        $this->cacheCreationInputTokens += $tokens;

        return $this;
    }

    /**
     * Add to cache read input tokens count
     */
    public function countCacheReadInputTokens(int $tokens): self
    {
        $this->cacheReadInputTokens += $tokens;

        return $this;
    }

    /**
     * Get total tokens count
     */
    public function getTotalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens + $this->cacheCreationInputTokens + $this->cacheReadInputTokens;
    }

    /**
     * Set timeout in milliseconds
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Get timeout in milliseconds
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }
}
