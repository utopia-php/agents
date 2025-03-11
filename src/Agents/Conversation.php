<?php

namespace Utopia\Agents;

use Utopia\Agents\Role;
use Utopia\Agents\Roles\Agent;

class Conversation
{
    /**
     * @var array<array<string, mixed>>
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
    protected int $totalTokens = 0;

    /**
     * @param Agent $agent
     */
    public function __construct(Agent $agent)
    {
        $this->agent = $agent;
    }

    /**
     * Add a message to the conversation
     *
     * @param Message $message
     * @param Role $from
     * @return self
     */
    public function addMessage(Role $from,Message $message): self
    {
        $this->messages[] = [
            'role' => $from->getIdentifier(),
            'content' => $message->getContent(),
        ];
        return $this;
    }

    /**
     * Send the conversation to the agent and get response
     *
     * @return array<Message>
     * @throws \Exception
     */
    public function send(): array
    {
        $messages = $this->agent->getAdapter()->send($this);
        
        foreach ($messages as $message) {
            $this->addMessage($this->agent, $message);
        }
        
        return $messages;
    }

    /**
     * Get all messages in the conversation
     *
     * @return array<array<string, mixed>>
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
     * Get input tokens count
     *
     * @return int
     */
    public function getInputTokens(): int
    {
        return $this->inputTokens;
    }

    /**
     * Set input tokens count
     *
     * @param int $tokens
     * @return self
     */
    public function setInputTokens(int $tokens): self
    {
        $this->inputTokens = $tokens;
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
     * Set output tokens count
     *
     * @param int $tokens
     * @return self
     */
    public function setOutputTokens(int $tokens): self
    {
        $this->outputTokens = $tokens;
        return $this;
    }

    /**
     * Get total tokens count
     *
     * @return int
     */
    public function getTotalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
} 