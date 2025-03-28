<?php

namespace Utopia\Agents\Adapters;

use Utopia\Agents\Adapter;
use Utopia\Agents\Message;
use Utopia\Agents\Messages\Text;

class OpenAI extends Adapter
{
    /**
     * GPT-4 Turbo - Latest and most capable model
     */
    public const MODEL_GPT_4_TURBO = 'gpt-4-turbo-preview';

    /**
     * GPT-4 - Previous generation model
     */
    public const MODEL_GPT_4 = 'gpt-4';

    /**
     * GPT-3.5 Turbo - Fast and efficient model
     */
    public const MODEL_GPT_3_5_TURBO = 'gpt-3.5-turbo';

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
     * Create a new OpenAI adapter
     *
     * @param  string  $apiKey
     * @param  string  $model
     * @param  int  $maxTokens
     * @param  float  $temperature
     *
     * @throws \Exception
     */
    public function __construct(
        string $apiKey,
        string $model = self::MODEL_GPT_3_5_TURBO,
        int $maxTokens = 1024,
        float $temperature = 1.0
    ) {
        $this->apiKey = $apiKey;
        $this->maxTokens = $maxTokens;
        $this->temperature = $temperature;
        $this->setModel($model);
    }

    /**
     * Send a message to the OpenAI API
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

        $content = '';

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        if ($ch === false) {
            throw new \Exception('Failed to initialize CURL');
        }

        $payload = json_encode([
            'model' => $this->model,
            'messages' => $formattedMessages,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'stream' => true,
        ]);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$content, $listener) {
                $content .= $this->process($data, $listener);

                return strlen($data);
            },
            CURLOPT_TIMEOUT => 90,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception('CURL request failed: '.$error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode >= 400) {
            throw new \Exception('OpenAI API error ('.$httpCode.'): '.$response);
        }

        curl_close($ch);

        $message = new Text($content);

        return $message;
    }

    /**
     * Process a stream chunk from the OpenAI API
     *
     * @param  string  $data
     * @param  callable|null  $listener
     * @return string
     *
     * @throws \Exception
     */
    protected function process(string $data, ?callable $listener): string
    {
        $block = '';
        $lines = explode("\n", $data);

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
            self::MODEL_GPT_4_TURBO,
            self::MODEL_GPT_4,
            self::MODEL_GPT_3_5_TURBO,
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
        return 'openai';
    }
}
