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

    protected string $apiKey;

    protected string $model;

    protected int $maxTokens;

    protected float $temperature;

    protected int $timeout;

    /**
     * Create a new Deepseek adapter
     *
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
     */
    public function isSchemaSupported(): bool
    {
        return true;
    }

    /**
     * Send a message to the Deepseek API
     *
     * @param  array<Message>  $messages
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
            $text = is_array($content) ? implode("\n", $content) : $content;
            $instructions[] = '# '.$name."\n\n".$text;
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
                /** @var Chunk $chunk */
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

            $choices = isset($json['choices']) && is_array($json['choices']) ? $json['choices'] : [];
            $firstChoice = isset($choices[0]) && is_array($choices[0]) ? $choices[0] : [];
            $delta = isset($firstChoice['delta']) && is_array($firstChoice['delta']) ? $firstChoice['delta'] : [];
            if (isset($delta['content']) && is_string($delta['content'])) {
                $deltaContent = $delta['content'];
                if (! empty($deltaContent)) {
                    $block .= $deltaContent;
                    if ($listener !== null) {
                        $listener($deltaContent);
                    }
                }
            }

            if (isset($json['usage']) && is_array($json['usage'])) {
                $usage = $json['usage'];
                if (isset($usage['prompt_tokens']) && is_int($usage['prompt_tokens'])) {
                    $this->countInputTokens($usage['prompt_tokens']);
                }
                if (isset($usage['completion_tokens']) && is_int($usage['completion_tokens'])) {
                    $this->countOutputTokens($usage['completion_tokens']);
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
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Set model to use
     */
    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Set max tokens
     */
    public function setMaxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;

        return $this;
    }

    /**
     * Set temperature
     */
    public function setTemperature(float $temperature): self
    {
        $this->temperature = $temperature;

        return $this;
    }

    /**
     * Get the adapter name
     */
    public function getName(): string
    {
        return 'deepseek';
    }

    /**
     * Extract and format error information from API response
     *
     * @param  mixed  $json
     */
    protected function formatErrorMessage($json): string
    {
        if (! is_array($json)) {
            return '(unknown_error) Unknown error';
        }

        $error = isset($json['error']) && is_array($json['error']) ? $json['error'] : [];
        $errorType = isset($error['type']) && is_string($error['type']) ? $error['type'] : 'unknown_error';
        $errorMessage = isset($error['message']) && is_string($error['message']) ? $error['message'] : 'Unknown error';

        return '('.$errorType.') '.$errorMessage;
    }

    public function getSupportForEmbeddings(): bool
    {
        return false;
    }

    /**
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
