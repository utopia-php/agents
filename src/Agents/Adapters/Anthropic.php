<?php

namespace Utopia\Agents\Adapters;

use Utopia\Agents\Adapter;
use Utopia\Agents\Message;
use Utopia\Agents\Messages\Text;
use Utopia\Agents\Schema;
use Utopia\Fetch\Chunk;
use Utopia\Fetch\Client;

class Anthropic extends Adapter
{
    /**
     * Claude 3 Opus - Most powerful model for highly complex tasks
     */
    public const MODEL_CLAUDE_3_OPUS = 'claude-3-opus-20240229';

    /**
     * Claude 3 Sonnet - Ideal balance of intelligence and speed
     */
    public const MODEL_CLAUDE_3_SONNET = 'claude-3-7-sonnet-20250219';

    /**
     * Claude 3 Haiku - Fastest and most compact model
     */
    public const MODEL_CLAUDE_3_HAIKU = 'claude-3-haiku-20240229';

    /**
     * Claude 2.1 - Previous generation model
     */
    public const MODEL_CLAUDE_2_1 = 'claude-2.1';

    /**
     * Cache TTL for 3600 seconds
     */
    private const CACHE_TTL_3600 = 'ephemeral';

    /**
     * Limit of instructions that can be cached
     */
    private const CACHE_LIMIT = 4;

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
        string $model = self::MODEL_CLAUDE_3_SONNET,
        int $maxTokens = 1024,
        float $temperature = 1.0,
        int $timeout = 90
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
     * Send a message to the Anthropic API
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
            ->addHeader('x-api-key', $this->apiKey)
            ->addHeader('anthropic-version', '2023-06-01')
            ->addHeader('content-type', Client::CONTENT_TYPE_APPLICATION_JSON);

        $systemMessages = [];
        if (! empty($this->getAgent()->getDescription())) {
            $systemMessages[] = [
                'type' => 'text',
                'text' => $this->getAgent()->getDescription(),
                'cache_control' => [
                    'type' => self::CACHE_TTL_3600,
                ],
            ];
        }

        $cacheControlCount = ! empty($this->getAgent()->getDescription()) ? 1 : 0;
        foreach ($this->getAgent()->getInstructions() as $name => $content) {
            $message = [
                'type' => 'text',
                'text' => '# '.$name."\n\n".$content,
            ];

            if ($cacheControlCount < self::CACHE_LIMIT) {
                $message['cache_control'] = [
                    'type' => self::CACHE_TTL_3600,
                ];
                $cacheControlCount++;
            }

            $systemMessages[] = $message;
        }

        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'role' => $message->getRole(),
                'content' => $message->getContent(),
            ];
        }

        $schema = $this->getAgent()->getSchema();
        $payload = [
            'model' => $this->model,
            'system' => $systemMessages,
            'messages' => $formattedMessages,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
        ];

        if (isset($schema)) {
            $payload['tools'] = [
                [
                    'name' => $schema->getName(),
                    'description' => $schema->getDescription(),
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => $schema->getProperties(),
                        'required' => $schema->getRequired(),
                    ],
                ],
            ];
            $payload['tool_choice'] = [
                'type' => 'tool',
                'name' => $schema->getName(),
            ];
            $payload['stream'] = false;
        } else {
            $payload['stream'] = true;
        }

        $content = '';
        if ($payload['stream']) {
            $response = $client->fetch(
                'https://api.anthropic.com/v1/messages',
                Client::METHOD_POST,
                $payload,
                [],
                function ($chunk) use (&$content, $listener) {
                    $content .= $this->process($chunk, $listener);
                }
            );
        } else {
            $response = $client->fetch(
                'https://api.anthropic.com/v1/messages',
                Client::METHOD_POST,
                $payload,
            );
        }

        if ($response->getStatusCode() >= 400) {
            throw new \Exception(
                ucfirst($this->getName()).' API error: '.$content,
                $response->getStatusCode()
            );
        }

        if ($payload['stream']) {
            return new Text($content);
        }

        $body = $response->getBody();
        $json = is_string($body) ? json_decode($body, true) : null;

        $text = '';
        if (is_array($json) && $schema !== null) {
            $content = $json['content'] ?? null;
            if (is_array($content) && isset($content[0])) {
                $item = $content[0];
                if (is_array($item) &&
                    isset($item['type']) && $item['type'] === 'tool_use' &&
                    isset($item['name']) && $item['name'] === $schema->getName()) {
                    $text = $item['input'];
                }
            }
        }

        if ($text === '') {
            $text = is_string($body) ? $body : (is_array($json) ? json_encode($json) : '');
        }

        if (is_array($text)) {
            $text = json_encode($text);
        }

        return new Text($text);
    }

    /**
     * Process a stream chunk from the Anthropic API
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

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $json = json_decode($line, true);
            if (is_array($json) && isset($json['type']) && $json['type'] === 'error') {
                $type = $json['error']['type'] ?? '';
                $message = $json['error']['message'] ?? 'Unknown error';

                return '('.$type.') '.$message;
            }

            $json = json_decode(substr($line, 6), true);
            if (! is_array($json)) {
                continue;
            }

            $type = $json['type'] ?? null;
            if ($type === null) {
                continue;
            }

            switch ($type) {
                case 'message_start':
                    if (isset($json['message']['usage'])) {
                        $usage = $json['message']['usage'];
                        if (isset($usage['input_tokens']) && is_int($usage['input_tokens'])) {
                            $this->countInputTokens($usage['input_tokens']);
                        }
                        if (isset($usage['output_tokens']) && is_int($usage['output_tokens'])) {
                            $this->countOutputTokens($usage['output_tokens']);
                        }
                        if (isset($usage['cache_creation_input_tokens']) && is_int($usage['cache_creation_input_tokens'])) {
                            $this->countCacheCreationInputTokens($usage['cache_creation_input_tokens']);
                        }
                        if (isset($usage['cache_read_input_tokens']) && is_int($usage['cache_read_input_tokens'])) {
                            $this->countCacheReadInputTokens($usage['cache_read_input_tokens']);
                        }
                    }
                    break;

                case 'content_block_start':
                    // Initialize content block
                    break;

                case 'content_block_delta':
                    if (! isset($json['delta']['type'])) {
                        break;
                    }

                    $deltaType = $json['delta']['type'];

                    if ($deltaType === 'text_delta' && isset($json['delta']['text']) && is_string($json['delta']['text'])) {
                        $block = $json['delta']['text'];
                    }

                    if (! empty($block)) {
                        if ($listener !== null) {
                            $listener($block);
                        }
                    }
                    break;

                case 'content_block_stop':
                    // End of content block
                    break;

                case 'message_delta':
                    if (isset($json['usage'])) {
                        $usage = $json['usage'];
                        if (isset($usage['input_tokens']) && is_int($usage['input_tokens'])) {
                            $this->countInputTokens($usage['input_tokens']);
                        }
                        if (isset($usage['output_tokens']) && is_int($usage['output_tokens'])) {
                            $this->countOutputTokens($usage['output_tokens']);
                        }
                    }
                    break;

                case 'message_stop':
                    break;

                case 'error':
                    $errorMessage = isset($json['error']['message']) ? (string) $json['error']['message'] : 'Unknown error';
                    throw new \Exception('Anthropic API error: '.$errorMessage);
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
     * @param  string  $model
     * @return self
     *
     * @throws \Exception
     */
    public function setModel(string $model): self
    {
        if (! in_array($model, $this->getModels())) {
            throw new \Exception('Unsupported model: '.$model);
        }

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
        return 'anthropic';
    }
}
