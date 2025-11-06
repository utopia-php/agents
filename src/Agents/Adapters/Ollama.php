<?php

namespace Utopia\Agents\Adapters;

use Utopia\Agents\Adapter;
use Utopia\Agents\Message;
use Utopia\Fetch\Client;

class Ollama extends Adapter
{
    /**
     * EmbeddingGemma - Gemma embedding model for Ollama
     */
    public const MODEL_EMBEDDING_GEMMA = 'embeddinggemma';

    /**
     * @var string
     */
    protected string $model;

    /**
     * @var int
     */
    protected int $timeout;

    private string $endpoint = 'http://ollama:11434/api/embed';

    public const MODELS = [self::MODEL_EMBEDDING_GEMMA];

    /**
     * Embedding dimensions of specific embedding model
     */
    protected const DIMENSIONS = [
        self::MODEL_EMBEDDING_GEMMA => 768,
    ];

    /**
     * Create a new Ollama adapter (no API key required for local call)
     *
     * @param  string  $model
     * @param  int  $timeout
     */
    public function __construct(
        string $model = self::MODEL_EMBEDDING_GEMMA,
        int $timeout = 90
    ) {
        $this->model = $model;
        $this->timeout = $timeout;
    }

    /**
     * Embedding generation (Ollama only supports embeddings, not chat)
     *
     * @param  string  $text
     * @return array{
     *     embedding: array<int, float>,
     *     total_duration: int|null,
     *     load_duration: int|null
     * }
     *
     * @throws \Exception
     */
    public function embed(string $text): array
    {
        $client = new Client();
        $client->setTimeout($this->timeout);
        $client->addHeader('Content-Type', 'application/json');
        $payload = [
            'model' => $this->model,
            'input' => $text,
        ];
        $response = $client->fetch(
            $this->getEndpoint(),
            Client::METHOD_POST,
            $payload
        );
        $body = $response->getBody();
        $json = is_string($body) ? json_decode($body, true) : null;
        if (! is_array($json) || ! isset($json['embeddings'][0])) {
            throw new \Exception('Invalid response from Ollama embed API');
        }

        return [
            'embedding' => $json['embeddings'][0],
            'total_duration' => $json['total_duration'] ?? null,
            'load_duration' => $json['load_duration'] ?? null,
        ];
    }

    /**
     * Get available models for embeddings (for now, only embeddinggemma)
     *
     * @return array<string>
     */
    public function getModels(): array
    {
        return self::MODELS;
    }

    /**
     * Get currently selected embedding model
     *
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * get embedding dimenion of the current model
     */
    public function getEmbeddingDimension(): int
    {
        return self::DIMENSIONS[$this->model];
    }

    /**
     * Set model to use for embedding
     *
     * @param  string  $model
     * @return self
     */
    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Not applicable for embedding-only adapters.
     *
     * @param  array<\Utopia\Agents\Message>  $messages
     * @param  callable|null  $listener
     *
     * @throws \Exception
     */
    public function send(array $messages, ?callable $listener = null): Message
    {
        throw new \Exception('OllamaAdapter does not support chat or messages. Use embed() instead.');
    }

    /**
     * Embeddings do not support schema.
     *
     * @return bool
     */
    public function isSchemaSupported(): bool
    {
        return false;
    }

    /**
     * Get the adapter name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'ollama';
    }

    /**
     * Error formatter (minimal)
     *
     * @param  mixed  $json
     * @return string
     */
    protected function formatErrorMessage($json): string
    {
        if (! is_array($json)) {
            return '(unknown_error) Unknown error';
        }
        $msg = $json['error'] ?? ($json['message'] ?? 'Unknown error');

        return '(ollama_error) '.$msg;
    }

    /**
     * Get the API endpoint
     *
     * @return string
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Set the API endpoint
     *
     * @param  string  $endpoint
     * @return self
     */
    public function setEndpoint(string $endpoint): self
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function getSupportForEmbeddings(): bool
    {
        return true;
    }
}
