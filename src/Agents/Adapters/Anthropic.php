<?php

namespace Utopia\Agents\Adapters;

use Utopia\Agents\Adapter;
use Utopia\Agents\Conversation;
use Utopia\Agents\Message;
use Utopia\Agents\Messages\Text;
use Utopia\Agents\Messages\Image;
use Utopia\Agents\Roles\Assistant;

class Anthropic extends Adapter
{
    /**
     * Claude 3 Opus - Most powerful model for highly complex tasks
     */
    public const MODEL_CLAUDE_3_OPUS = 'claude-3-opus-20240229';

    /**
     * Claude 3 Sonnet - Ideal balance of intelligence and speed
     */
    public const MODEL_CLAUDE_3_SONNET = 'claude-3-sonnet-20240229';

    /**
     * Claude 3 Haiku - Fastest and most compact model
     */
    public const MODEL_CLAUDE_3_HAIKU = 'claude-3-haiku-20240229';

    /**
     * Claude 2.1 - Previous generation model
     */
    public const MODEL_CLAUDE_2_1 = 'claude-2.1';

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
     * @param string $apiKey
     * @param string $model
     * @param int $maxTokens
     * @param float $temperature
     * 
     * @throws \Exception
     */
    public function __construct(
        string $apiKey,
        string $model = self::MODEL_CLAUDE_3_SONNET,
        int $maxTokens = 1024,
        float $temperature = 1.0
    ) {
        $this->apiKey = $apiKey;
        $this->maxTokens = $maxTokens;
        $this->temperature = $temperature;
        $this->setModel($model);
    }

    /**
     * Send a message to the Anthropic API
     *
     * @param Conversation $conversation
     * @return array<Message>
     * @throws \Exception
     */
    public function send(Conversation $conversation): array
    {
        $client = new \Utopia\Fetch\Client();
        $client
            ->addHeader('x-api-key', $this->apiKey)
            ->addHeader('anthropic-version', '2023-06-01')
            ->addHeader('content-type', 'application/json');

        $messages = [];
        foreach ($conversation->getMessages() as $message) {
            $messages[] = [
                'role' => $message['role'],
                'content' => $message['content']
            ];
        }

        $response = $client->fetch(
            'https://api.anthropic.com/v1/messages',
            \Utopia\Fetch\Client::METHOD_POST,
            [
                'model' => $this->model,
                'messages' => $messages,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature
            ]
        );

        if ($response->getStatusCode() >= 400) {
            throw new \Exception('Anthropic API error: ' . $response->getBody());
        }

        $result = json_decode($response->getBody(), true);

        if (!$result || !isset($result['content'])) {
            throw new \Exception('Invalid response from Anthropic API');
        }

        // Set token usage if available
        if (isset($result['usage'])) {
            $conversation->setInputTokens($result['usage']['input_tokens'] ?? 0);
            $conversation->setOutputTokens($result['usage']['output_tokens'] ?? 0);
        }

        $messages = [];
        foreach ($result['content'] as $content) {
            if (!isset($content['type'])) {
                throw new \Exception('Invalid message type in response');
            }

            switch ($content['type']) {
                case 'image':
                    if (!isset($content['source']['data'])) {
                        throw new \Exception('Invalid image data in response');
                    }
                    $messages[] = new Image(base64_decode($content['source']['data']));
                    break;

                case 'text':
                    if (!isset($content['text'])) {
                        throw new \Exception('Invalid text content in response');
                    }
                    $messages[] = new Text($content['text']);
                    break;

                default:
                    throw new \Exception('Unsupported message type: ' . $content['type']);
            }
        }

        // Add all messages to the conversation
        foreach ($messages as $message) {
            $conversation->addMessage(new Assistant('anthropic'), $message);
        }

        return $messages;
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
     * @param string $model
     * @return self
     * @throws \Exception
     */
    public function setModel(string $model): self
    {
        if (!in_array($model, $this->getModels())) {
            throw new \Exception('Unsupported model: ' . $model);
        }

        $this->model = $model;
        return $this;
    }

    /**
     * Set max tokens
     *
     * @param int $maxTokens
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
     * @param float $temperature
     * @return self
     */
    public function setTemperature(float $temperature): self
    {
        $this->temperature = $temperature;
        return $this;
    }
} 