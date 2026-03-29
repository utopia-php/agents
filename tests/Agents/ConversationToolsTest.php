<?php

namespace Tests\Utopia\Agents;

use PHPUnit\Framework\TestCase;
use Utopia\Agents\Adapter;
use Utopia\Agents\Agent;
use Utopia\Agents\Conversation;
use Utopia\Agents\Message;
use Utopia\Agents\Roles\User;
use Utopia\Agents\ToolCall;

class ConversationToolsTest extends TestCase
{
    public function testConversationRunsToolLoopAndReturnsFinalAnswer(): void
    {
        $nonce = bin2hex(random_bytes(8));
        $adapter = new ToolLoopFakeAdapter($nonce);
        $agent = new Agent($adapter);
        $agent->addTool(
            'stamp',
            fn (string $nonce): string => hash('sha256', 'tool::'.$nonce)
        );

        $conversation = new Conversation($agent);
        $conversation->message(new User('user-1'), new Message('Use stamp tool for my token.'));

        $final = $conversation->send();
        $expected = hash('sha256', 'tool::'.$nonce);

        $this->assertSame('STAMP: '.$expected, $final->getContent());
        $this->assertCount(4, $conversation->getMessages());
        $this->assertSame('assistant', $conversation->getMessages()[1]->getRole());
        $this->assertTrue($conversation->getMessages()[1]->hasToolCalls());
        $this->assertTrue($conversation->getMessages()[1]->getToolCalls()[0]->isSuccess());
        $this->assertNull($conversation->getMessages()[1]->getToolCalls()[0]->getError());
        $this->assertSame('tool', $conversation->getMessages()[2]->getRole());
        $this->assertSame($expected, $conversation->getMessages()[2]->getContent());
        $this->assertSame('call_1', $conversation->getMessages()[2]->getToolCallId());
        $this->assertSame('assistant', $conversation->getMessages()[3]->getRole());
    }

    public function testToolCallStatusIsErrorWhenToolFails(): void
    {
        $adapter = new ToolErrorFakeAdapter();
        $agent = new Agent($adapter);
        $agent->addTool(
            'explode',
            function (): string {
                throw new \RuntimeException('Tool exploded');
            }
        );

        $conversation = new Conversation($agent);
        $conversation->message(new User('user-1'), new Message('Run explode tool.'));

        try {
            $conversation->send();
            $this->fail('Expected tool execution failure');
        } catch (\RuntimeException $error) {
            $this->assertSame('Tool exploded', $error->getMessage());
        }

        $messages = $conversation->getMessages();
        $this->assertCount(2, $messages);
        $this->assertTrue($messages[1]->hasToolCalls());
        $this->assertTrue($messages[1]->getToolCalls()[0]->isError());
        $this->assertSame('Tool exploded', $messages[1]->getToolCalls()[0]->getError());
    }
}

class ToolLoopFakeAdapter extends Adapter
{
    private int $sendCount = 0;

    public function __construct(
        private readonly string $nonce
    ) {}

    public function getName(): string
    {
        return 'tool-loop-fake';
    }

    /**
     * @param  array<Message>  $messages
     */
    public function send(array $messages, ?callable $listener = null): Message
    {
        $this->sendCount++;

        if ($this->sendCount === 1) {
            return (new Message(''))->setToolCalls([
                new ToolCall(
                    'call_1',
                    'stamp',
                    '{"nonce":"'.$this->nonce.'"}'
                ),
            ]);
        }

        $last = end($messages);
        if (! $last instanceof Message || $last->getRole() !== 'tool') {
            throw new \RuntimeException('Expected a tool result message before final answer');
        }

        return new Message('STAMP: '.$last->getContent());
    }

    public function supportsTools(): bool
    {
        return true;
    }

    public function getModels(): array
    {
        return ['fake-model'];
    }

    public function getModel(): string
    {
        return 'fake-model';
    }

    public function setModel(string $model): self
    {
        return $this;
    }

    public function isSchemaSupported(): bool
    {
        return false;
    }

    public function getSupportForEmbeddings(): bool
    {
        return false;
    }

    public function embed(string $text): array
    {
        throw new \Exception('Embeddings not supported');
    }

    public function getEmbeddingDimension(): int
    {
        throw new \Exception('Embeddings not supported');
    }

    protected function formatErrorMessage($json): string
    {
        return 'fake error';
    }
}

class ToolErrorFakeAdapter extends Adapter
{
    public function getName(): string
    {
        return 'tool-error-fake';
    }

    /**
     * @param  array<Message>  $messages
     */
    public function send(array $messages, ?callable $listener = null): Message
    {
        return (new Message(''))->setToolCalls([
            new ToolCall(
                'call_error_1',
                'explode',
                []
            ),
        ]);
    }

    public function supportsTools(): bool
    {
        return true;
    }

    public function getModels(): array
    {
        return ['fake-model'];
    }

    public function getModel(): string
    {
        return 'fake-model';
    }

    public function setModel(string $model): self
    {
        return $this;
    }

    public function isSchemaSupported(): bool
    {
        return false;
    }

    public function getSupportForEmbeddings(): bool
    {
        return false;
    }

    public function embed(string $text): array
    {
        throw new \Exception('Embeddings not supported');
    }

    public function getEmbeddingDimension(): int
    {
        throw new \Exception('Embeddings not supported');
    }

    protected function formatErrorMessage($json): string
    {
        return 'fake error';
    }
}
