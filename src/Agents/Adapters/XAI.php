<?php

namespace Utopia\Agents\Adapters;

use Utopia\Fetch\Chunk;

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

    /**
     * Process a stream chunk from the OpenAI API
     *
     * @param  \Utopia\Fetch\Chunk  $chunk
     * @param  callable|null  $listener
     * @return string
     *
     * @throws \Exception
     */
    protected function process(Chunk $chunk, ?callable $listener): string
    {
        $block = '';
        $data = $chunk->getData();
        $lines = explode("\n", $data);

        $json = json_decode($data, true);
        if (is_array($json) && isset($json['error'])) {
            return $this->formatErrorMessage($json);
        }

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            if (! str_starts_with($line, 'data: ')) {
                continue;
            }

            // Handle [DONE] message
            if (trim($line) === 'data: [DONE]') {
                continue;
            }

            $json = json_decode(substr($line, 6), true);
            if (! is_array($json)) {
                continue;
            }

            // Extract content from the choices array
            if (isset($json['choices'][0]['delta']['content'])) {
                $block = $json['choices'][0]['delta']['content'];

                if (! empty($block) && $listener !== null) {
                    $listener($block);
                }
            }
        }

        return $block;
    }

    /**
     * Extract and format error information from API response
     *
     * @param  mixed  $json
     * @return string
     */
    protected function formatErrorMessage($json): string
    {
        if (! is_array($json)) {
            return '(unknown_error) Unknown error';
        }

        $errorType = isset($json['code']) ? (string) $json['code'] : 'unknown_error';
        $errorMessage = isset($json['error']) ? (string) $json['error'] : 'Unknown error';

        return '('.$errorType.') '.$errorMessage;
    }
}
