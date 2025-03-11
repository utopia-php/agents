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
     * @return array<string, mixed>
     * @throws \Exception
     */
    public function send(): array
    {
        return $this->agent->getAdapter()->send($this->messages);
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
} 