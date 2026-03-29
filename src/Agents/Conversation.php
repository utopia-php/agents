<?php

namespace Utopia\Agents;

use Utopia\Agents\Roles\Assistant;
use Utopia\Agents\Schema\SchemaObject;

class Conversation
{
    /**
     * @var array<Message>
     */
    protected array $messages = [];

    protected Agent $agent;

    protected int $maxToolIterations = 8;

    protected int $inputTokens = 0;

    protected int $outputTokens = 0;

    protected int $cacheCreationInputTokens = 0;

    protected int $cacheReadInputTokens = 0;

    protected int $totalTokens = 0;

    protected bool $toolProtocolSeeded = false;

    /**
     * @var callable
     */
    protected $listener;

    public function __construct(Agent $agent)
    {
        $this->agent = $agent;
        $this->listener = function () {};
    }

    /**
     * Set a callback to handle chunks
     */
    public function listen(callable $listener): self
    {
        $this->listener = $listener;

        return $this;
    }

    /**
     * Add a message to the conversation
     *
     * @param  array<int, mixed>  $attachments
     */
    public function message(Role|string $from, Message $message, array $attachments = []): self
    {
        $identifier = is_string($from) ? $from : $from->getIdentifier();
        $entry = $message->withRole($identifier);
        $normalizedExistingAttachments = [];
        foreach ($entry->getAttachments() as $existingAttachment) {
            $normalizedExistingAttachments[] = $existingAttachment->withRole($identifier);
        }
        $entry->setAttachments($normalizedExistingAttachments);

        $this->validateAttachments($entry, $attachments);

        foreach ($attachments as $attachment) {
            if (! $attachment instanceof Message) {
                throw new \InvalidArgumentException('Attachments must be Message instances');
            }

            $entry->addAttachment($attachment->withRole($identifier));
        }
        $this->messages[] = $entry;

        return $this;
    }

    public function setMaxToolIterations(int $maxToolIterations): self
    {
        if ($maxToolIterations < 1) {
            throw new \InvalidArgumentException('Max tool iterations must be at least 1');
        }

        $this->maxToolIterations = $maxToolIterations;

        return $this;
    }

    /**
     * Send the conversation to the agent and get response
     *
     *
     * @throws \Exception
     */
    public function send(): Message
    {
        $adapter = $this->agent->getAdapter();
        $toolProtocolEnabled = $this->shouldUseToolProtocol();
        if ($toolProtocolEnabled) {
            $this->seedToolProtocolIfNeeded();
        }

        $previousInputTokens = $adapter->getInputTokens();
        $previousOutputTokens = $adapter->getOutputTokens();
        $previousCacheCreationInputTokens = $adapter->getCacheCreationInputTokens();
        $previousCacheReadInputTokens = $adapter->getCacheReadInputTokens();

        $iterations = 0;
        do {
            $message = $adapter->send($this->messages, $this->listener);
            $this->countAdapterTokenDeltas(
                $previousInputTokens,
                $previousOutputTokens,
                $previousCacheCreationInputTokens,
                $previousCacheReadInputTokens
            );

            if ($toolProtocolEnabled) {
                $parsedToolProtocolResponse = $this->parseToolProtocolResponse($message);
                if ($parsedToolProtocolResponse instanceof Message) {
                    $message = $parsedToolProtocolResponse;
                }
            }

            $from = new Assistant($adapter->getModel(), 'Assistant');
            $this->message($from, $message);

            if ($toolProtocolEnabled) {
                if ($parsedToolProtocolResponse instanceof Message) {
                    return $message;
                }
            }

            if (! $message->hasToolCalls()) {
                return $message;
            }

            $this->executeToolCalls($message);
            $iterations++;
        } while ($iterations < $this->maxToolIterations);

        throw new \RuntimeException('Tool-calling loop exceeded max iterations');
    }

    /**
     * Get all messages in the conversation
     *
     * @return array<Message>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get the agent in the conversation
     */
    public function getAgent(): Agent
    {
        return $this->agent;
    }

    /**
     * Get the current listener callback
     */
    public function getListener(): callable
    {
        return $this->listener;
    }

    /**
     * Get input tokens count
     */
    public function getInputTokens(): int
    {
        return $this->inputTokens;
    }

    /**
     * Add to input tokens count
     */
    public function countInputTokens(int $tokens): self
    {
        $this->inputTokens += $tokens;

        return $this;
    }

    /**
     * Get output tokens count
     */
    public function getOutputTokens(): int
    {
        return $this->outputTokens;
    }

    /**
     * Add to output tokens count
     */
    public function countOutputTokens(int $tokens): self
    {
        $this->outputTokens += $tokens;

        return $this;
    }

    /**
     * Get cache creation input tokens count
     */
    public function getCacheCreationInputTokens(): int
    {
        return $this->cacheCreationInputTokens;
    }

    /**
     * Add to cache creation input tokens count
     */
    public function countCacheCreationInputTokens(int $tokens): self
    {
        $this->cacheCreationInputTokens += $tokens;

        return $this;
    }

    /**
     * Get cache read input tokens count
     */
    public function getCacheReadInputTokens(): int
    {
        return $this->cacheReadInputTokens;
    }

    /**
     * Add to cache read input tokens count
     */
    public function countCacheReadInputTokens(int $tokens): self
    {
        $this->cacheReadInputTokens += $tokens;

        return $this;
    }

    /**
     * Get total tokens count
     */
    public function getTotalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens + $this->cacheCreationInputTokens + $this->cacheReadInputTokens;
    }

    /**
     * @param  array<int, mixed>  $attachments
     */
    protected function validateAttachments(Message $message, array $attachments): void
    {
        $adapter = $this->agent->getAdapter();
        $allAttachments = array_merge($message->getAttachments(), $attachments);

        $maxAttachmentsPerMessage = $adapter->getMaxAttachmentsPerMessage();
        if ($maxAttachmentsPerMessage !== null && count($allAttachments) > $maxAttachmentsPerMessage) {
            throw new \InvalidArgumentException('Too many attachments in this message');
        }

        $maxAttachmentBytes = $adapter->getMaxAttachmentBytes();
        $maxTotalAttachmentBytes = $adapter->getMaxTotalAttachmentBytes();
        $allowedAttachmentMimeTypes = $adapter->getAllowedAttachmentMimeTypes();

        $totalBytes = 0;
        foreach ($allAttachments as $attachment) {
            if (! $attachment instanceof Message) {
                throw new \InvalidArgumentException('Attachments must be Message instances');
            }

            $bytes = strlen($attachment->getContent());
            if ($bytes === 0) {
                throw new \InvalidArgumentException('Attachment payload cannot be empty');
            }

            if ($maxAttachmentBytes !== null && $bytes > $maxAttachmentBytes) {
                throw new \InvalidArgumentException('Attachment exceeds per-file size limit');
            }

            $mimeType = $attachment->getMimeType();
            if ($mimeType === null) {
                throw new \InvalidArgumentException('Attachment MIME type cannot be detected');
            }

            if (
                $allowedAttachmentMimeTypes !== null &&
                ! in_array($mimeType, $allowedAttachmentMimeTypes, true)
            ) {
                throw new \InvalidArgumentException('Attachment MIME type is not allowed');
            }

            if (! $adapter->supportsAttachment($attachment)) {
                throw new \InvalidArgumentException('Attachment type is not supported by this adapter');
            }

            $totalBytes += $bytes;
        }

        if ($maxTotalAttachmentBytes !== null && $totalBytes > $maxTotalAttachmentBytes) {
            throw new \InvalidArgumentException('Attachments exceed total payload size limit');
        }
    }

    protected function countAdapterTokenDeltas(
        int &$previousInputTokens,
        int &$previousOutputTokens,
        int &$previousCacheCreationInputTokens,
        int &$previousCacheReadInputTokens
    ): void {
        $adapter = $this->agent->getAdapter();

        $currentInputTokens = $adapter->getInputTokens();
        $currentOutputTokens = $adapter->getOutputTokens();
        $currentCacheCreationInputTokens = $adapter->getCacheCreationInputTokens();
        $currentCacheReadInputTokens = $adapter->getCacheReadInputTokens();

        $this->countInputTokens(max(0, $currentInputTokens - $previousInputTokens));
        $this->countOutputTokens(max(0, $currentOutputTokens - $previousOutputTokens));
        $this->countCacheCreationInputTokens(max(0, $currentCacheCreationInputTokens - $previousCacheCreationInputTokens));
        $this->countCacheReadInputTokens(max(0, $currentCacheReadInputTokens - $previousCacheReadInputTokens));

        $previousInputTokens = $currentInputTokens;
        $previousOutputTokens = $currentOutputTokens;
        $previousCacheCreationInputTokens = $currentCacheCreationInputTokens;
        $previousCacheReadInputTokens = $currentCacheReadInputTokens;
    }

    protected function executeToolCalls(Message $assistantMessage): void
    {
        foreach ($assistantMessage->getToolCalls() as $toolCall) {
            try {
                $arguments = $this->decodeToolArguments($toolCall->getArguments());
                $result = $this->agent->callTool($toolCall->getName(), $arguments);
                $toolCall->markSuccess();
            } catch (\Throwable $error) {
                $toolCall->markError($error->getMessage());
                throw $error;
            }

            $toolMessage = (new Message($this->normalizeToolResult($result)))
                ->setToolCallId($toolCall->getId())
                ->setToolName($toolCall->getName());

            $this->message('user', new Message($this->buildToolResultMessage($toolCall, $toolMessage)));
        }
    }

    /**
     * @param  array<string, mixed>|string  $arguments
     * @return array<string, mixed>
     */
    protected function decodeToolArguments(array|string $arguments): array
    {
        if (is_array($arguments)) {
            return $arguments;
        }

        $decoded = json_decode($arguments, true);
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new \InvalidArgumentException('Tool call arguments must decode into an object');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    protected function normalizeToolResult(mixed $result): string
    {
        if (is_string($result)) {
            return $result;
        }

        $encoded = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new \RuntimeException('Failed to encode tool result');
        }

        return $encoded;
    }

    protected function shouldUseToolProtocol(): bool
    {
        return ! empty($this->agent->getTools());
    }

    protected function seedToolProtocolIfNeeded(): void
    {
        if ($this->toolProtocolSeeded) {
            return;
        }

        $this->message('user', new Message($this->buildToolProtocolPrompt()));
        $this->toolProtocolSeeded = true;
    }

    protected function buildToolProtocolPrompt(): string
    {
        $tools = [];
        foreach ($this->agent->getTools() as $tool) {
            $tools[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'parameters' => $tool->getParameters(),
            ];
        }

        $toolsJson = json_encode($tools, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($toolsJson === false) {
            throw new \RuntimeException('Failed to encode tool definitions');
        }

        $toolCallSchemaJson = json_encode(
            $this->schemaToJsonSchema($this->getToolCallSchema()),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $finalSchemaJson = json_encode(
            $this->schemaToJsonSchema($this->getFinalResponseSchema()),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($toolCallSchemaJson === false || $finalSchemaJson === false) {
            throw new \RuntimeException('Failed to encode protocol schema definitions');
        }

        return "Tool protocol enabled.\n"
            ."Available tools (JSON schema):\n".$toolsJson."\n\n"
            ."When you need a tool, respond ONLY with JSON matching this schema:\n"
            .$toolCallSchemaJson."\n\n"
            ."When you are done, respond ONLY with JSON matching this schema:\n"
            .$finalSchemaJson;
    }

    protected function buildToolResultMessage(ToolCall $toolCall, Message $toolMessage): string
    {
        $payload = [
            'type' => 'tool_result',
            'id' => $toolCall->getId(),
            'name' => $toolCall->getName(),
            'content' => $toolMessage->getContent(),
        ];
        if (! $this->matchesSchemaPayload($payload, $this->getToolResultSchema())) {
            throw new \RuntimeException('Generated tool result payload does not match schema');
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode generic tool result payload');
        }

        return "Tool execution result:\n".$json."\n"
            .'Now continue with the same protocol. '
            .'Respond with either tool_call JSON or final JSON only.';
    }

    protected function parseToolProtocolResponse(Message $message): ?Message
    {
        if ($message->hasToolCalls()) {
            return null;
        }

        $payload = $this->decodeToolProtocolPayload($message->getContent());
        if ($payload === null) {
            return null;
        }

        $type = isset($payload['type']) && is_string($payload['type']) ? $payload['type'] : null;
        if ($type === 'tool_call') {
            if (! $this->matchesSchemaPayload($payload, $this->getToolCallSchema())) {
                return null;
            }

            $name = isset($payload['name']) && is_string($payload['name']) ? $payload['name'] : null;
            if ($name === null) {
                return null;
            }

            $id = isset($payload['id']) && is_string($payload['id']) ? $payload['id'] : ('call_'.bin2hex(random_bytes(6)));
            $arguments = $payload['arguments'] ?? [];
            if (! is_array($arguments) && ! is_string($arguments)) {
                $arguments = [];
            }

            $message->setToolCalls([new ToolCall($id, $name, $arguments)]);

            return null;
        }

        if ($type === 'final') {
            if (! $this->matchesSchemaPayload($payload, $this->getFinalResponseSchema())) {
                return null;
            }

            $content = isset($payload['content']) && is_string($payload['content']) ? $payload['content'] : '';

            return new Message($content);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decodeToolProtocolPayload(string $content): ?array
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, '```')) {
            if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $trimmed, $matches) === 1) {
                $trimmed = trim($matches[1]);
            }
        }

        $decoded = json_decode($trimmed, true);
        if (! is_array($decoded) || array_is_list($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    protected function getToolCallSchema(): Schema
    {
        $object = (new SchemaObject())
            ->addProperty('type', ['type' => SchemaObject::TYPE_STRING, 'description' => 'Must be "tool_call"'])
            ->addProperty('id', ['type' => SchemaObject::TYPE_STRING])
            ->addProperty('name', ['type' => SchemaObject::TYPE_STRING])
            ->addProperty('arguments', ['type' => SchemaObject::TYPE_OBJECT]);

        return new Schema(
            'tool_call',
            'Schema for assistant tool call request',
            $object,
            ['type', 'name', 'arguments']
        );
    }

    protected function getFinalResponseSchema(): Schema
    {
        $object = (new SchemaObject())
            ->addProperty('type', ['type' => SchemaObject::TYPE_STRING, 'description' => 'Must be "final"'])
            ->addProperty('content', ['type' => SchemaObject::TYPE_STRING]);

        return new Schema(
            'final_response',
            'Schema for final assistant answer',
            $object,
            ['type', 'content']
        );
    }

    protected function getToolResultSchema(): Schema
    {
        $object = (new SchemaObject())
            ->addProperty('type', ['type' => SchemaObject::TYPE_STRING, 'description' => 'Must be "tool_result"'])
            ->addProperty('id', ['type' => SchemaObject::TYPE_STRING])
            ->addProperty('name', ['type' => SchemaObject::TYPE_STRING])
            ->addProperty('content', ['type' => SchemaObject::TYPE_STRING]);

        return new Schema(
            'tool_result',
            'Schema for backend tool execution result',
            $object,
            ['type', 'id', 'name', 'content']
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function schemaToJsonSchema(Schema $schema): array
    {
        return [
            'name' => $schema->getName(),
            'description' => $schema->getDescription(),
            'type' => SchemaObject::TYPE_OBJECT,
            'properties' => $schema->getProperties(),
            'required' => $schema->getRequired(),
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function matchesSchemaPayload(array $payload, Schema $schema): bool
    {
        foreach ($schema->getRequired() as $required) {
            if (! array_key_exists($required, $payload)) {
                return false;
            }
        }

        $properties = $schema->getProperties();
        foreach ($payload as $key => $value) {
            if (! array_key_exists($key, $properties)) {
                return false;
            }

            $type = $properties[$key]['type'] ?? null;
            if (! is_string($type)) {
                return false;
            }

            if (! $this->matchesSchemaType($value, $type)) {
                return false;
            }
        }

        return true;
    }

    protected function matchesSchemaType(mixed $value, string $type): bool
    {
        return match ($type) {
            SchemaObject::TYPE_STRING => is_string($value),
            // json_decode('{}', true) becomes [], which is still an object payload in this protocol.
            SchemaObject::TYPE_OBJECT => is_array($value),
            SchemaObject::TYPE_ARRAY => is_array($value) && array_is_list($value),
            SchemaObject::TYPE_BOOLEAN => is_bool($value),
            SchemaObject::TYPE_INTEGER => is_int($value),
            SchemaObject::TYPE_NUMBER => is_int($value) || is_float($value),
            SchemaObject::TYPE_NULL => $value === null,
            default => false,
        };
    }
}
