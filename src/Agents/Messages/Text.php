<?php

namespace Utopia\Agents\Messages;

use Utopia\Agents\Message;

class Text extends Message
{
    /**
     * Create a new text message
     *
     * @param  array<int, mixed>  $attachments
     */
    public function __construct(string $content, ?string $role = null, array $attachments = [])
    {
        parent::__construct($content, $role, $attachments);
    }

    /**
     * Get the message content
     */
    public function getContent(): string
    {
        return $this->content;
    }
}
