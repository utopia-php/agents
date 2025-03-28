<?php

namespace Utopia\Tests\Agents\Conversation;

use PHPUnit\Framework\TestCase;
use Utopia\Agents\Adapter;
use Utopia\Agents\Agent;
use Utopia\Agents\Conversation;
use Utopia\Agents\Messages\Text;
use Utopia\Agents\Role;
use Utopia\Agents\Roles\Assistant;
use Utopia\Agents\Roles\User;

abstract class ConversationBase extends TestCase
{
    protected Conversation $conversation;

    protected Agent $agent;

    protected Adapter $adapter;

    /**
     * Abstract method to be implemented by child classes
     * to specify the specific Adapter
     *
     * @return Adapter
     */
    abstract protected function createAdapter(): Adapter;

    /**
     * Optional method to customize agent description
     *
     * @return string
     */
    protected function getAgentDescription(): string
    {
        return 'Test Agent Description';
    }

    protected function setUp(): void
    {
        $this->adapter = $this->createAdapter();

        $this->agent = new Agent($this->adapter);
        $this->agent->setDescription($this->getAgentDescription());

        $this->conversation = new Conversation($this->agent);
    }

    public function testConstructor(): void
    {
        $this->assertSame($this->agent, $this->conversation->getAgent());
        $this->assertEmpty($this->conversation->getMessages());
        $this->assertEquals(0, $this->conversation->getInputTokens());
        $this->assertEquals(0, $this->conversation->getOutputTokens());
        $this->assertEquals(0, $this->conversation->getTotalTokens());
        $this->assertIsCallable($this->conversation->getListener());
    }

    public function testMessage(): void
    {
        $user = new User('user-1', 'Test User');
        $message = new Text('Hello, AI!');

        $result = $this->conversation->message($user, $message);

        $this->assertSame($this->conversation, $result);
        $this->assertCount(1, $this->conversation->getMessages());

        $firstMessage = $this->conversation->getMessages()[0];
        $this->assertEquals(Role::ROLE_USER, $firstMessage->getRole());
        $this->assertEquals('Hello, AI!', $firstMessage->getContent());
    }

    public function testMultipleMessages(): void
    {
        $user = new User('user-1', 'Test User');
        $assistant = new Assistant('assistant-1', 'Test Assistant');

        $this->conversation
            ->message($user, new Text('Hello'))
            ->message($assistant, new Text('Hi there!'))
            ->message($user, new Text('How are you?'));

        $messages = $this->conversation->getMessages();
        $this->assertCount(3, $messages);

        $this->assertEquals(Role::ROLE_USER, $messages[0]->getRole());
        $this->assertEquals('Hello', $messages[0]->getContent());

        $this->assertEquals(Role::ROLE_ASSISTANT, $messages[1]->getRole());
        $this->assertEquals('Hi there!', $messages[1]->getContent());

        $this->assertEquals(Role::ROLE_USER, $messages[2]->getRole());
        $this->assertEquals('How are you?', $messages[2]->getContent());
    }

    public function testSend(): void
    {
        $messages = $this->conversation
            ->message(new User('user-1', 'Test User'), new Text('Hello'))
            ->send();

        $this->assertNotEmpty($messages);
        $this->assertInstanceOf(Text::class, $messages);

        // Verify the response was added to conversation
        $conversationMessages = $this->conversation->getMessages();
        $this->assertNotEmpty($conversationMessages);
        $this->assertEquals(Role::ROLE_USER, $conversationMessages[0]->getRole());
        $this->assertEquals('Hello', $conversationMessages[0]->getContent());

        // Verify AI response
        $this->assertEquals(Role::ROLE_ASSISTANT, $conversationMessages[1]->getRole());
        $this->assertNotEmpty($conversationMessages[1]->getContent());
    }

    public function testTokenCounting(): void
    {
        $this->conversation->countInputTokens(10);
        $this->assertEquals(10, $this->conversation->getInputTokens());

        $this->conversation->countInputTokens(5);
        $this->assertEquals(15, $this->conversation->getInputTokens());

        $this->conversation->countOutputTokens(20);
        $this->assertEquals(20, $this->conversation->getOutputTokens());

        $this->conversation->countOutputTokens(10);
        $this->assertEquals(30, $this->conversation->getOutputTokens());

        $this->assertEquals(45, $this->conversation->getTotalTokens());
    }

    public function testListener(): void
    {
        $called = false;
        $testListener = function () use (&$called) {
            $called = true;
        };

        $result = $this->conversation->listen($testListener);

        $this->assertSame($this->conversation, $result);
        $this->assertSame($testListener, $this->conversation->getListener());

        // Call the listener to verify it works
        $listener = $this->conversation->getListener();
        $listener();
        $this->assertTrue($called);
    }
}
