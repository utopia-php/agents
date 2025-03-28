<?php

namespace Utopia\Agents\Adapters;

class Perplexity extends OpenAI
{
    /**
     * Default Perplexity API endpoint
     */
    protected const ENDPOINT = 'https://api.perplexity.ai/chat/completions';

    /**
     * Sonar - Lightweight, cost-effective search model
     */
    public const MODEL_SONAR = 'sonar';

    /**
     * Sonar Pro - Enhanced search model with advanced features
     */
    public const MODEL_SONAR_PRO = 'sonar-pro';

    /**
     * Sonar Deep Research - Advanced search model with deep analysis capabilities
     */
    public const MODEL_SONAR_DEEP_RESEARCH = 'sonar-deep-research';

    /**
     * Sonar Reasoning - Reasoning model with analysis capabilities
     */
    public const MODEL_SONAR_REASONING = 'sonar-reasoning';

    /**
     * Sonar Reasoning Pro - Enhanced reasoning model with advanced features
     */
    public const MODEL_SONAR_REASONING_PRO = 'sonar-reasoning-pro';

    /**
     * Create a new Perplexity adapter
     *
     * @param  string  $apiKey
     * @param  string  $model
     * @param  int  $maxTokens
     * @param  float  $temperature
     * @param  string|null  $endpoint
     *
     * @throws \Exception
     */
    public function __construct(
        string $apiKey,
        string $model = self::MODEL_SONAR,
        int $maxTokens = 1024,
        float $temperature = 1.0,
        ?string $endpoint = null
    ) {
        parent::__construct(
            $apiKey,
            $model,
            $maxTokens,
            $temperature,
            $endpoint ?? self::ENDPOINT
        );
    }

    /**
     * Get available models
     *
     * @return array<string>
     */
    public function getModels(): array
    {
        return [
            self::MODEL_SONAR,
            self::MODEL_SONAR_PRO,
            self::MODEL_SONAR_DEEP_RESEARCH,
            self::MODEL_SONAR_REASONING,
            self::MODEL_SONAR_REASONING_PRO,
        ];
    }

    /**
     * Get the adapter name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'perplexity';
    }
}
