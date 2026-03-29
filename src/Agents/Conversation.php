<?php

namespace Utopia\Agents;

use Utopia\Agents\Roles\Assistant;

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

            $from = new Assistant($adapter->getModel(), 'Assistant');
            $this->message($from, $message);

            if (! $message->hasToolCalls()) {
                return $message;
            }

            if (! $adapter->supportsTools()) {
                throw new \Exception('Tool calls are not supported for this adapter');
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

            $this->message('tool', $toolMessage);
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
}
