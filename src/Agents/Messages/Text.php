<?php

namespace Utopia\Agents\Messages;

use Utopia\Agents\Message;

class Text extends Message
{
    /**
     * Create a new text message
     *
     * @param  string  $content
     * @param  string|null  $role
     */
    public function __construct(string $content, ?string $role = null)
    {
        parent::__construct($content, $role);
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
