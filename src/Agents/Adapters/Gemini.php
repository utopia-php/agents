<?php

namespace Utopia\Agents\Adapters;

use Utopia\Agents\Adapter;
use Utopia\Agents\Message;
use Utopia\Agents\Messages\Text;
use Utopia\Fetch\Chunk;
use Utopia\Fetch\Client;

class Gemini extends Adapter
{
    /**
     * Gemini 2.5 Flash Preview - Our best model in terms of price-performance, offering well-rounded capabilities.
     */
    public const MODEL_GEMINI_2_5_FLASH_PREVIEW = 'gemini-2.5-flash-preview-04-17';

    /**
     * Gemini 2.5 Pro Preview - Enhanced thinking and reasoning, multimodal understanding, advanced coding, and more.
     */
    public const MODEL_GEMINI_2_5_PRO_PREVIEW = 'gemini-2.5-pro-preview-03-25';

    /**
     * Gemini 2.0 Flash - Next generation features, speed, thinking, realtime streaming, and multimodal generation.
     */
    public const MODEL_GEMINI_2_0_FLASH = 'gemini-2.0-flash';

    /**
     * Gemini 2.0 Flash Lite - Cost efficiency and low latency.
     */
    public const MODEL_GEMINI_2_0_FLASH_LITE = 'gemini-2.0-flash-lite';

    /**
     * Gemini 1.5 Pro - Complex reasoning tasks requiring more intelligence.
     */
    public const MODEL_GEMINI_1_5_PRO = 'gemini-1.5-pro';

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
     * Create a new Gemini adapter
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
        string $model = self::MODEL_GEMINI_2_0_FLASH,
        int $maxTokens = 1024,
        float $temperature = 1.0,
        ?string $endpoint = null,
        int $timeout = 90
    ) {
        $this->apiKey = $apiKey;
        $this->maxTokens = $maxTokens;
        $this->temperature = $temperature;
        $this->endpoint = $endpoint ?? 'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':streamGenerateContent?alt=sse&key='.$apiKey;
        $this->timeout = $timeout;
        $this->setModel($model);
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
            ->addHeader('content-type', Client::CONTENT_TYPE_APPLICATION_JSON);

        $systemParts = [];
        $systemParts[] = [
            'text' => $this->getAgent()->getDescription(),
        ];

        foreach ($this->getAgent()->getInstructions() as $name => $content) {
            $systemParts[] = [
                'text' => '# '.$name."\n\n".$content,
            ];
        }

        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'role' => $message->getRole() === 'user' ? 'user' : 'model',
                'parts' => [
                    [
                        'text' => $message->getContent(),
                    ],
                ],
            ];
        }

        $payload = [
            'system_instruction' => [
                'parts' => $systemParts,
            ],
            'contents' => $formattedMessages,
            'generationConfig' => [
                'maxOutputTokens' => $this->maxTokens,
                'temperature' => $this->temperature,
            ],
        ];

        $content = '';
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

        $message = new Text($content);

        return $message;
    }

    /**
     * Process a stream chunk from the Gemini API
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
            $error = '('.($json['error']['status'] ?? '').') '.($json['error']['message'] ?? 'Unknown error');
            if (! empty($json['error']['details'])) {
                $error .= PHP_EOL.json_encode($json['error']['details'], JSON_PRETTY_PRINT);
            }

            return $error;
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

            // Extract content from Gemini response format
            if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                $block = $json['candidates'][0]['content']['parts'][0]['text'];

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
            self::MODEL_GEMINI_2_0_FLASH,
            self::MODEL_GEMINI_2_0_FLASH_LITE,
            self::MODEL_GEMINI_1_5_PRO,
            self::MODEL_GEMINI_2_5_FLASH_PREVIEW,
            self::MODEL_GEMINI_2_5_PRO_PREVIEW,
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
        return 'gemini';
    }
}
