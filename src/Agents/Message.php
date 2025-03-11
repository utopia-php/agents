<?php

namespace Utopia\Agents;

abstract class Message
{
    /**
     * @var array<string, mixed>|string
     */
    protected array|string $content;

    /**
     * Create a new message
     *
     * @param array<string, mixed>|string $content
     */
    public function __construct(array|string $content)
    {
        $this->content = $content;
    }

    /**
     * Get the message content
     *
     * @return array<string, mixed>|string
     */
    public function getContent(): array|string
    {
        return $this->content;
    }
} 