<?php

namespace Utopia\Agents;

use Utopia\Agents\Messages\Text;
use Utopia\Agents\Roles\Assistant;

class Conversation
{
    /**
     * @var array<Message>
     */
    protected array $messages = [];

    /**
     * @var Agent
     */
    protected Agent $agent;

    /**
     * @var int
     */
    protected int $inputTokens = 0;

    /**
     * @var int
     */
    protected int $outputTokens = 0;

    /**
     * @var int
     */
    protected int $cacheCreationInputTokens = 0;

    /**
     * @var int
     */
    protected int $cacheReadInputTokens = 0;

    /**
     * @var int
     */
    protected int $totalTokens = 0;

    /**
     * @var callable
     */
    protected $listener;

    /**
     * @param  Agent  $agent
     */
    public function __construct(Agent $agent)
    {
        $this->agent = $agent;
        $this->listener = function () {
        };
    }

    /**
     * Set a callback to handle chunks
     *
     * @param  callable  $listener
     * @return self
     */
    public function listen(callable $listener): self
    {
        $this->listener = $listener;

        return $this;
    }

    /**
     * Add a message to the conversation
     *
     * @param  Message  $message
     * @param  Role  $from
     * @return self
     */
    public function message(Role $from, Message $message): self
    {
        $this->messages[] = new Text($message->getContent(), $from->getIdentifier());

        return $this;
    }

    /**
     * Send the conversation to the agent and get response
     *
     * @return Message
     *
     * @throws \Exception
     */
    public function send(): Message
    {
        $message = $this->agent->getAdapter()->send($this->messages, $this->listener);

        $this->countInputTokens($this->agent->getAdapter()->getInputTokens());
        $this->countOutputTokens($this->agent->getAdapter()->getOutputTokens());
        $this->countCacheCreationInputTokens($this->agent->getAdapter()->getCacheCreationInputTokens());
        $this->countCacheReadInputTokens($this->agent->getAdapter()->getCacheReadInputTokens());

        $from = new Assistant($this->agent->getAdapter()->getModel(), 'Assistant');
        $this->message($from, $message);

        return $message;
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
     *
     * @return Agent
     */
    public function getAgent(): Agent
    {
        return $this->agent;
    }

    /**
     * Get the current listener callback
     *
     * @return callable
     */
    public function getListener(): callable
    {
        return $this->listener;
    }

    /**
     * Get input tokens count
     *
     * @return int
     */
    public function getInputTokens(): int
    {
        return $this->inputTokens;
    }

    /**
     * Add to input tokens count
     *
     * @param  int  $tokens
     * @return self
     */
    public function countInputTokens(int $tokens): self
    {
        $this->inputTokens += $tokens;

        return $this;
    }

    /**
     * Get output tokens count
     *
     * @return int
     */
    public function getOutputTokens(): int
    {
        return $this->outputTokens;
    }

    /**
     * Add to output tokens count
     *
     * @param  int  $tokens
     * @return self
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
     *
     * @param  int  $tokens
     * @return self
     */
    public function countCacheCreationInputTokens(int $tokens): self
    {
        $this->cacheCreationInputTokens += $tokens;

        return $this;
    }

    /**
     * Get cache read input tokens count
     *
     * @return int
     */
    public function getCacheReadInputTokens(): int
    {
        return $this->cacheReadInputTokens;
    }

    /**
     * Add to cache read input tokens count
     *
     * @param  int  $tokens
     * @return self
     */
    public function countCacheReadInputTokens(int $tokens): self
    {
        $this->cacheReadInputTokens += $tokens;

        return $this;
    }

    /**
     * Get total tokens count
     *
     * @return int
     */
    public function getTotalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens + $this->cacheCreationInputTokens + $this->cacheReadInputTokens;
    }
}
