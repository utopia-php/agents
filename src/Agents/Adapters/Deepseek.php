<?php

namespace Utopia\Agents\Adapters;

use Utopia\Agents\Adapter;
use Utopia\Agents\Message;
use Utopia\Agents\Messages\Text;
use Utopia\Fetch\Chunk;
use Utopia\Fetch\Client;

class Deepseek extends Adapter
{
    /**
     * Deepseek-Chat - Most powerful model
     */
    public const MODEL_DEEPSEEK_CHAT = 'deepseek-chat';

    /**
     * Deepseek-Coder - Specialized for code
     */
    public const MODEL_DEEPSEEK_CODER = 'deepseek-coder';

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
     * @var int
     */
    protected int $timeout;

    /**
     * Create a new Deepseek adapter
     *
     * @param  string  $apiKey
     * @param  string  $model
     * @param  int  $maxTokens
     * @param  float  $temperature
     * @param  int  $timeout
     *
     * @throws \Exception
     */
    public function __construct(
        string $apiKey,
        string $model = self::MODEL_DEEPSEEK_CHAT,
        int $maxTokens = 1024,
        float $temperature = 1.0,
        int $timeout = 90000
    ) {
        $this->apiKey = $apiKey;
        $this->maxTokens = $maxTokens;
        $this->temperature = $temperature;
        $this->timeout = $timeout;
        $this->setModel($model);
    }

    /**
     * Check if the model supports JSON schema
     *
     * @return bool
     */
    public function isSchemaSupported(): bool
    {
        return true;
    }

    /**
     * Send a message to the Deepseek API
     *
     * @param  array<Message>  $messages
     * @param  callable|null  $listener
     * @return Message
     *
     * @throws \Exception
     */
    public function send(array $messages, ?callable $listener = null): Message
    {
        if ($this->getAgent() === null) {
            throw new \Exception('Agent not set');
        }

        $client = new Client();
        $client
            ->setTimeout($this->timeout)
            ->addHeader('authorization', 'Bearer '.$this->apiKey)
            ->addHeader('content-type', Client::CONTENT_TYPE_APPLICATION_JSON);

        $formattedMessages = [];
        foreach ($messages as $message) {
            if (! empty($message->getRole()) && ! empty($message->getContent())) {
                $formattedMessages[] = [
                    'role' => $message->getRole(),
                    'content' => $message->getContent(),
                ];
            }
        }

        $instructions = [];
        foreach ($this->getAgent()->getInstructions() as $name => $content) {
            $instructions[] = '# '.$name."\n\n".$content;
        }

        $systemMessage = $this->getAgent()->getDescription().
            (empty($instructions) ? '' : "\n\n".implode("\n\n", $instructions));

        $schema = $this->getAgent()->getSchema();
        if ($schema !== null) {
            $systemMessage .= "\n\n"."USE THE JSON SCHEMA BELOW TO GENERATE A VALID JSON RESPONSE: \n".$schema->toJson();
        }

        if (! empty($systemMessage)) {
            array_unshift($formattedMessages, [
                'role' => 'system',
                'content' => $systemMessage,
            ]);
        }

        $payload = [
            'model' => $this->model,
            'messages' => $formattedMessages,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'stream' => true,
        ];

        if ($schema !== null) {
            $payload['response_format'] = [
                'type' => 'json_object',
            ];
        }

        $content = '';
        $response = $client->fetch(
            'https://api.deepseek.com/chat/completions',
            Client::METHOD_POST,
            $payload,
            [],
            function ($chunk) use (&$content, $listener) {
                $content .= $this->process($chunk, $listener);
            }
        );

        if ($response->getStatusCode() >= 400) {
            throw new \Exception(
                ucfirst($this->getName()).' API error: '.$content,
                $response->getStatusCode()
            );
        }

        return new Text($content);
    }

    /**
     * Process a stream chunk from the Deepseek API
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

            $line = substr($line, 6);
            if ($line === '[DONE]') {
                continue;
            }

            $json = json_decode($line, true);
            if (! is_array($json)) {
                continue;
            }

            if (isset($json['choices'][0]['delta']['content'])) {
                $delta = $json['choices'][0]['delta']['content'];
                if (! empty($delta)) {
                    $block .= $delta;
                    if ($listener !== null) {
                        $listener($delta);
                    }
                }
            }

            if (isset($json['usage'])) {
                if (isset($json['usage']['prompt_tokens'])) {
                    $this->countInputTokens($json['usage']['prompt_tokens']);
                }
                if (isset($json['usage']['completion_tokens'])) {
                    $this->countOutputTokens($json['usage']['completion_tokens']);
                }
            }
        }

        return $block;
    }

    /**
     * Get available models
     *
     * @return array<string>
     */
    public function getModels(): array
    {
        return [
            self::MODEL_DEEPSEEK_CHAT,
            self::MODEL_DEEPSEEK_CODER,
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
     * @param  string  $model
     * @return self
     */
    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Set max tokens
     *
     * @param  int  $maxTokens
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
     * @param  float  $temperature
     * @return self
     */
    public function setTemperature(float $temperature): self
    {
        $this->temperature = $temperature;

        return $this;
    }

    /**
     * Get the adapter name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'deepseek';
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

        $errorType = isset($json['error']['type']) ? (string) $json['error']['type'] : 'unknown_error';
        $errorMessage = isset($json['error']['message']) ? (string) $json['error']['message'] : 'Unknown error';

        return '('.$errorType.') '.$errorMessage;
    }

    public function getSupportForEmbeddings(): bool
    {
        return false;
    }

    /**
     * @param  string  $text
     * @return array{
     *     embedding: array<int, float>,
     *     tokensProcessed: int|null,
     *     totalDuration: int|null ,
     *     modelLoadingDuration: int|null
     * }
     */
    public function embed(string $text): array
    {
        throw new \Exception('Embeddings are not supported for this adapter.');
    }

    public function getEmbeddingDimension(): int
    {
        throw new \Exception('Embeddings are not supported for this adapter.');
    }
}
