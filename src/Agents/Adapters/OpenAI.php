<?php

namespace Utopia\Agents\Adapters;

use Utopia\Agents\Adapter;
use Utopia\Agents\Message;
use Utopia\Agents\Messages\Text;
use Utopia\Fetch\Chunk;
use Utopia\Fetch\Client;

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

        $client = new Client();
        $client
            ->setTimeout(90)
            ->addHeader('authorization', 'Bearer '.$this->apiKey)
            ->addHeader('content-type', Client::CONTENT_TYPE_APPLICATION_JSON);

        $formattedMessages = [];
        foreach ($messages as $message) {
            if (! isset($message['role']) || ! isset($message['content'])) {
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
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'stream' => true,
        ];

        $content = '';
        $response = $client->fetch(
            'https://api.openai.com/v1/chat/completions',
            Client::METHOD_POST,
            $payload,
            [],
            function ($chunk) use (&$content, $listener) {
                $content .= $this->process($chunk, $listener);
            }
        );

        if ($response->getStatusCode() >= 400) {
            throw new \Exception('Anthropic API error ('.$response->getStatusCode().'): '.$response->getBody());
        }

        $message = new Text($content);

        return $message;
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

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            if (! str_starts_with($line, 'data: ')) {
                continue;
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
