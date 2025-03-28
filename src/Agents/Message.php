<?php

namespace Utopia\Agents;

abstract class Message
{
    /**
     * @var string
     */
    protected string $role;

    /**
     * @var string
     */
    protected string $content;

    /**
     * Create a new message
     *
     * @param  string  $content
     * @param  string|null  $role
     */
    public function __construct(string $content, ?string $role = null)
    {
        $this->content = $content;
        $this->role = $role ?? Role::ROLE_USER;
    }

    /**
     * Get the message role
     *
     * @return string
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * Get the message content
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }
}
