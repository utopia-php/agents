<?php

namespace Utopia\Agents\Messages;

use Utopia\Agents\Message;

class Image extends Message
{
    /**
     * Create a new image message
     *
     * @param  string  $content Binary content of the image
     * @param  string|null  $role
     */
    public function __construct(string $content, ?string $role = null)
    {
        parent::__construct($content, $role);
    }

    /**
     * Get the message content
     *
     * @return string Binary content of the image
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get the MIME type of the image
     *
     * @return string|null
     */
    public function getMimeType(): ?string
    {
        if (empty($this->content)) {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        $mimeType = $finfo->buffer($this->content);

        return $mimeType === false ? null : $mimeType;
    }
}
