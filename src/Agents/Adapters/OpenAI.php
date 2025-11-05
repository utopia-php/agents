<?php

namespace Utopia\Agents\Adapters;

use Utopia\Agents\Adapter;
use Utopia\Agents\Message;
use Utopia\Agents\Messages\Text;
use Utopia\Agents\Schema;
use Utopia\Fetch\Chunk;
use Utopia\Fetch\Client;

class OpenAI extends Adapter
{
    /**
     * GPT-4.5 Preview - OpenAI's most advanced model with enhanced reasoning, broader knowledge, and improved instruction following
     */
    public const MODEL_GPT_4_5_PREVIEW = 'gpt-4.5-preview';

    /**
     * GPT-4.1 - Advanced large language model with strong reasoning capabilities and improved context handling
     */
    public const MODEL_GPT_4_1 = 'gpt-4.1';

    /**
     * GPT-4o - Multimodal model optimized for both text and image processing with faster response times
     */
    public const MODEL_GPT_4O = 'gpt-4o';

    /**
     * o4-mini - Compact version of GPT-4o offering good performance with higher throughput and lower latency
     */
    public const MODEL_O4_MINI = 'o4-mini';

    /**
     * o3 - Balanced model offering good performance for general language tasks with efficient resource usage
     */
    public const MODEL_O3 = 'o3';

    /**
     * o3-mini - Streamlined model optimized for speed and efficiency while maintaining good capabilities for routine tasks
     */
    public const MODEL_O3_MINI = 'o3-mini';

    /**
     * Default OpenAI API endpoint
     */
    protected const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

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
     * @var string
     */
    protected string $endpoint;

    /**
     * @var int
     */
    protected int $timeout;

    /**
     * Create a new OpenAI adapter
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
        string $model = self::MODEL_O3_MINI,
        int $maxTokens = 1024,
        float $temperature = 1.0,
        ?string $endpoint = null,
        int $timeout = 90
    ) {
        $this->apiKey = $apiKey;
        $this->maxTokens = $maxTokens;
        $this->temperature = $temperature;
        $this->endpoint = $endpoint ?? self::ENDPOINT;
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
     * Send a message to the API
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
            if (empty($message->getRole()) || empty($message->getContent())) {
                throw new \Exception('Invalid message format');
            }
            $formattedMessages[] = [
                'role' => $message->getRole(),
                'content' => $message->getContent(),
            ];
        }

        $instructions = [];
        foreach ($this->getAgent()->getInstructions() as $name => $content) {
            $instructions[] = '# '.$name."\n\n".$content;
        }

        $systemMessage = $this->getAgent()->getDescription().
            (empty($instructions) ? '' : "\n\n".implode("\n\n", $instructions));

        if (! empty($systemMessage)) {
            array_unshift($formattedMessages, [
                'role' => 'system',
                'content' => $systemMessage,
            ]);
        }

        $payload = [
            'model' => $this->model,
            'messages' => $formattedMessages,
            'temperature' => $this->temperature,
        ];

        $schema = $this->getAgent()->getSchema();
        if ($schema !== null) {
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $schema->getName(),
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => $schema->getProperties(),
                        'required' => $schema->getRequired(),
                        'additionalProperties' => false,
                    ],
                ],
            ];
            $payload['stream'] = false;
        } else {
            $payload['stream'] = true;
        }

        // Use 'max_completion_tokens' for o-series models, else 'max_tokens'
        $oSeriesModels = [
            self::MODEL_O3,
            self::MODEL_O3_MINI,
            self::MODEL_O4_MINI,
        ];
        if (in_array($this->model, $oSeriesModels)) {
            $payload['max_completion_tokens'] = $this->maxTokens;
        } else {
            $payload['max_tokens'] = $this->maxTokens;
        }

        $content = '';

        if ($payload['stream']) {
            $response = $client->fetch(
                $this->endpoint,
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
        } else {
            $response = $client->fetch(
                $this->endpoint,
                Client::METHOD_POST,
                $payload,
            );
            $body = $response->getBody();

            if ($response->getStatusCode() >= 400) {
                $json = is_string($body) ? json_decode($body, true) : null;
                $content = $this->formatErrorMessage($json);
                throw new \Exception(
                    ucfirst($this->getName()).' API error: '.$content,
                    $response->getStatusCode()
                );
            }

            $json = is_string($body) ? json_decode($body, true) : null;
            if (is_array($json) && isset($json['choices'][0]['message']['content'])) {
                $content = $json['choices'][0]['message']['content'];
            } else {
                throw new \Exception('Invalid response format received from the API');
            }
        }

        return new Text($content);
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
     * Get available models
     *
     * @return array<string>
     */
    public function getModels(): array
    {
        return [
            self::MODEL_GPT_4_5_PREVIEW,
            self::MODEL_GPT_4_1,
            self::MODEL_GPT_4O,
            self::MODEL_O4_MINI,
            self::MODEL_O3,
            self::MODEL_O3_MINI,
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

    /**
     * Get the adapter name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'openai';
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

        $errorType = isset($json['error']['code']) ? (string) $json['error']['code'] : 'unknown_error';
        $errorMessage = isset($json['error']['message']) ? (string) $json['error']['message'] : 'Unknown error';

        return '('.$errorType.') '.$errorMessage;
    }

    public function getSupportForEmbeddings(): bool
    {
        return false;
    }

    public function embed(string $text): array
    {
        throw new \Exception('Embeddings are not supported for this adapter.');
    }

    public function getEmbeddingDimension(): int
    {
        throw new \Exception('Embeddings are not supported for this adapter.');
    }
}
