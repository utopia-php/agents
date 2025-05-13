<?php

namespace Utopia\Agents\Adapters;

class XAI extends OpenAI
{
    /**
     * Default XAI API endpoint
     */
    protected const ENDPOINT = 'https://api.x.ai/v1/chat/completions';

    /**
     * Grok 2 Latest - Latest Grok model
     */
    public const MODEL_GROK_2_LATEST = 'grok-2-latest';

    /**
     * Grok 2 Image - Latest Grok model with image support
     */
    public const MODEL_GROK_2_IMAGE = 'grok-2-image';

    /**
     * Create a new XAI adapter
     *
     * @param  string  $apiKey
     * @param  string  $model
     * @param  int  $maxTokens
     * @param  float  $temperature
     * @param  string|null  $endpoint
     * @param  int  $timeout
     *
     * @throws \Exception
     */
    public function __construct(
        string $apiKey,
        string $model = self::MODEL_GROK_2_LATEST,
        int $maxTokens = 1024,
        float $temperature = 1.0,
        ?string $endpoint = null,
        int $timeout = 90
    ) {
        parent::__construct(
            $apiKey,
            $model,
            $maxTokens,
            $temperature,
            $endpoint ?? self::ENDPOINT,
            $timeout
        );
    }

    /**
     * Check if the model supports JSON schema
     *
     * @return bool
     */
    public function isSchemaSupported(): bool
    {
        return false;
    }

    /**
     * Get available models
     *
     * @return array<string>
     */
    public function getModels(): array
    {
        return [
            self::MODEL_GROK_2_LATEST,
            self::MODEL_GROK_2_IMAGE,
        ];
    }

    /**
     * Get the adapter name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'xai';
    }
}
