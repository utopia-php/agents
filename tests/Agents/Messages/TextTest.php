<?php

namespace Tests\Utopia\Agents\Messages;

use PHPUnit\Framework\TestCase;
use Utopia\Agents\Message;
use Utopia\Agents\Messages\Text;

class TextTest extends TestCase
{
    public function test_constructor(): void
    {
        $content = 'Hello, world!';
        $message = new Text($content);

        $this->assertInstanceOf(Message::class, $message);
        $this->assertInstanceOf(Text::class, $message);
    }

    public function test_get_content(): void
    {
        $content = 'Test message content';
        $message = new Text($content);

        $this->assertSame($content, $message->getContent());
        $this->assertIsString($message->getContent());
    }

    public function test_empty_content(): void
    {
        $message = new Text('');

        $this->assertSame('', $message->getContent());
        $this->assertIsString($message->getContent());
    }

    public function test_multiline_content(): void
    {
        $content = "Line 1\nLine 2\nLine 3";
        $message = new Text($content);

        $this->assertSame($content, $message->getContent());
        $this->assertStringContainsString("\n", $message->getContent());
    }

    public function test_special_characters(): void
    {
        $content = 'Special chars: !@#$%^&*()_+ 😀 🌟';
        $message = new Text($content);

        $this->assertSame($content, $message->getContent());
    }
}
