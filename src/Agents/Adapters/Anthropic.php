<?php

namespace Utopia\Agents\Adapters;

use Utopia\Agents\Adapter;

class Anthropic extends Adapter
{
    /**
     * Claude 3 Opus - Most powerful model for highly complex tasks
     */
    public const MODEL_CLAUDE_3_OPUS = 'claude-3-opus-20240229';

    /**
     * Claude 3 Sonnet - Ideal balance of intelligence and speed
     */
    public const MODEL_CLAUDE_3_SONNET = 'claude-3-sonnet-20240229';

    /**
     * Claude 3 Haiku - Fastest and most compact model
     */
    public const MODEL_CLAUDE_3_HAIKU = 'claude-3-haiku-20240229';

    /**
     * Claude 2.1 - Previous generation model
     */
    public const MODEL_CLAUDE_2_1 = 'claude-2.1';

    /**
     * @var string
     */
    protected string $apiKey;

    /**
     * @var string
     */
    protected string $model;

    /**
     * @var int
     */
    protected int $maxTokens;

    /**
     * @var float
     */
    protected float $temperature;

    /**
     * Create a new Anthropic adapter
     *
     * @param string $apiKey
     * @param string $model
     * @param int $maxTokens
     * @param float $temperature
     * 
     * @throws \Exception
     */
    public function __construct(
        string $apiKey,
        string $model = self::MODEL_CLAUDE_3_SONNET,
        int $maxTokens = 1024,
        float $temperature = 1.0
    ) {
        $this->apiKey = $apiKey;
        $this->maxTokens = $maxTokens;
        $this->temperature = $temperature;
        $this->setModel($model);
    }

    /**
     * Send a message to the Anthropic API
     *
     * @param array<string, mixed> $messages
     * @return array<string, mixed>
     * @throws \Exception
     */
    public function send(array $messages): array
    {
        // TODO: Implement Anthropic API call
        throw new \Exception('Not implemented');
    }

    /**
     * Get available models
     *
     * @return array<string>
     */
    public function getModels(): array
    {
        return [
            self::MODEL_CLAUDE_3_OPUS,
            self::MODEL_CLAUDE_3_SONNET,
            self::MODEL_CLAUDE_3_HAIKU,
            self::MODEL_CLAUDE_2_1,
        ];
    }

    /**
     * Get current model
     *
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Set model to use
     *
     * @param string $model
     * @return self
     * @throws \Exception
     */
    public function setModel(string $model): self
    {
        if (!in_array($model, $this->getModels())) {
            throw new \Exception('Unsupported model: ' . $model);
        }

        $this->model = $model;
        return $this;
    }

    /**
     * Set max tokens
     *
     * @param int $maxTokens
     * @return self
     */
    public function setMaxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;
        return $this;
    }

    /**
     * Set temperature
     *
     * @param float $temperature
     * @return self
     */
    public function setTemperature(float $temperature): self
    {
        $this->temperature = $temperature;
        return $this;
    }
} 