<?php

namespace Tests\Utopia\Agents;

use PHPUnit\Framework\TestCase;
use Utopia\Agents\Messages\Image;
use Utopia\Agents\Messages\Text;
use Utopia\Agents\Role;

class MessageTest extends TestCase
{
    public function testWithRoleCreatesCloneWithRequestedRole(): void
    {
        $message = new Text('hello');

        $withAssistantRole = $message->withRole(Role::ROLE_ASSISTANT);

        $this->assertNotSame($message, $withAssistantRole);
        $this->assertSame(Role::ROLE_USER, $message->getRole());
        $this->assertSame(Role::ROLE_ASSISTANT, $withAssistantRole->getRole());
    }

    public function testCanAddAndReadAttachments(): void
    {
        $message = new Text('describe this');
        $attachment = new Image(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVQYV2NgYAAAAAMAAWgmWQ0AAAAASUVORK5CYII='));

        $message->addAttachment($attachment);

        $this->assertTrue($message->hasAttachments());
        $this->assertCount(1, $message->getAttachments());
        $this->assertSame($attachment, $message->getAttachments()[0]);
    }
}
