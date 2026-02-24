<?php

namespace Utopia\Agents\Messages;

use Utopia\Agents\Message;

class Text extends Message
{
    /**
     * Create a new text message
     */
    public function __construct(string $content, ?string $role = null)
    {
        parent::__construct($content, $role);
    }

    /**
     * Get the message content
     */
    public function getContent(): string
    {
        return $this->content;
    }
}
