<?php

namespace Utopia\Agents\Adapters;

use Utopia\Agents\Message;
use Utopia\Agents\Messages\Text;
use Utopia\Fetch\Chunk;
use Utopia\Fetch\Client;

class Moonshot extends OpenAI
{
    /**
     * Default Moonshot API endpoint
     */
    protected const ENDPOINT = 'https://api.moonshot.ai/v1/chat/completions';

    /**
     * Kimi K2.5 - General-purpose Moonshot model optimized for long-context chat and coding workflows
     */
    public const MODEL_KIMI_K2_5 = 'kimi-k2.5';

    /**
     * Create a new Moonshot adapter
     *
     * @throws \Exception
     */
    public function __construct(
        string $apiKey,
        string $model = self::MODEL_KIMI_K2_5,
        int $maxTokens = 1024,
        float $temperature = 1.0,
        ?string $endpoint = null,
        int $timeout = 90000
    ) {
        parent::__construct(
            $apiKey,
            $model,
            $maxTokens,
            $temperature,
            $endpoint ?? self::ENDPOINT,
            $timeout
        );
    }

    /**
     * Check if the model supports structured output.
     *
     * Moonshot currently exposes JSON mode rather than OpenAI-style strict
     * json_schema transport, so we keep schema support enabled and adapt the
     * request format inside send().
     */
    public function isSchemaSupported(): bool
    {
        return true;
    }

    /**
     * Send a message to the Moonshot API.
     *
     * @param  array<Message>  $messages
     *
     * @throws \Exception
     */
    public function send(array $messages, ?callable $listener = null): Message
    {
        $agent = $this->getAgent();
        if ($agent === null) {
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
        foreach ($agent->getInstructions() as $name => $content) {
            $text = is_array($content) ? implode("\n", $content) : $content;
            $instructions[] = '# '.$name."\n\n".$text;
        }

        $systemMessage = $agent->getDescription().
            (empty($instructions) ? '' : "\n\n".implode("\n\n", $instructions));

        $schema = $agent->getSchema();
        if ($schema !== null) {
            $systemMessage .= "\n\nUSE THE JSON SCHEMA BELOW TO GENERATE A VALID JSON RESPONSE:\n".$schema->toJson();
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
            'stream' => $schema === null,
            'max_completion_tokens' => $this->maxTokens,
        ];

        $temperature = $this->temperature;
        if (! $this->usesDefaultTemperatureOnly()) {
            $payload['temperature'] = $temperature;
        }

        if ($schema !== null) {
            $payload['response_format'] = [
                'type' => 'json_object',
            ];
        }

        $content = '';

        if ($payload['stream']) {
            $response = $client->fetch(
                $this->endpoint,
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
            $choices = is_array($json) && isset($json['choices']) && is_array($json['choices']) ? $json['choices'] : [];
            $firstChoice = isset($choices[0]) && is_array($choices[0]) ? $choices[0] : [];
            $message = isset($firstChoice['message']) && is_array($firstChoice['message']) ? $firstChoice['message'] : [];
            if (isset($message['content']) && is_string($message['content'])) {
                $content = $message['content'];
            } else {
                throw new \Exception('Invalid response format received from the API');
            }
        }

        return new Text($content);
    }

    /**
     * Get available models.
     *
     * @return array<string>
     */
    public function getModels(): array
    {
        return [
            self::MODEL_KIMI_K2_5,
        ];
    }

    /**
     * Moonshot expects max_completion_tokens for kimi-k2.5.
     */
    protected function usesMaxCompletionTokens(): bool
    {
        return true;
    }

    /**
     * kimi-k2.5 only supports the default temperature.
     */
    protected function usesDefaultTemperatureOnly(): bool
    {
        if ($this->temperature !== 1.0 && ! $this->hasWarnedTemperatureOverride) {
            $this->hasWarnedTemperatureOverride = true;
            error_log(
                "Moonshot adapter warning: model '{$this->model}' only supports temperature=1.0. "
                ."Ignoring provided value {$this->temperature}. "
                .'Set temperature to 1.0 to remove this warning.'
            );
        }

        return true;
    }

    /**
     * Get the adapter name.
     */
    public function getName(): string
    {
        return 'moonshot';
    }
}
