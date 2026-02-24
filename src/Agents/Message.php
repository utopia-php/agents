<?php

namespace Utopia\Agents;

abstract class Message
{
    protected string $role;

    protected string $content;

    /**
     * Create a new message
     */
    public function __construct(string $content, ?string $role = null)
    {
        $this->content = $content;
        $this->role = $role ?? Role::ROLE_USER;
    }

    /**
     * Get the message role
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * Get the message content
     */
    public function getContent(): string
    {
        return $this->content;
    }
}
