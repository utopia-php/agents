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
        int $timeout = 90
    ) {
        $this->apiKey = $apiKey;
        $this->maxTokens = $maxTokens;
        $this->temperature = $temperature;
        $this->timeout = $timeout;
        $this->setModel($model);
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

        $message = new Text($content);

        return $message;
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
            $type = isset($json['error']['type']) ? $json['error']['type'] : '';
            $message = isset($json['error']['message']) ? $json['error']['message'] : 'Unknown error';

            return '('.$type.') '.$message;
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
        return 'deepseek';
    }
}
