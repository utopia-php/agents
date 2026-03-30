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
        $this->assertCount(5, $conversation->getMessages());
        $this->assertSame('assistant', $conversation->getMessages()[2]->getRole());
        $this->assertTrue($conversation->getMessages()[2]->hasToolCalls());
        $this->assertTrue($conversation->getMessages()[2]->getToolCalls()[0]->isSuccess());
        $this->assertNull($conversation->getMessages()[2]->getToolCalls()[0]->getError());
        $this->assertSame('user', $conversation->getMessages()[3]->getRole());
        $this->assertStringContainsString($expected, $conversation->getMessages()[3]->getContent());
        $this->assertSame('assistant', $conversation->getMessages()[4]->getRole());
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
        $this->assertCount(3, $messages);
        $this->assertTrue($messages[2]->hasToolCalls());
        $this->assertTrue($messages[2]->getToolCalls()[0]->isError());
        $this->assertSame('Tool exploded', $messages[2]->getToolCalls()[0]->getError());
    }

    public function testToolProtocolWorksForAnyAdapter(): void
    {
        $nonce = bin2hex(random_bytes(8));
        $adapter = new ToolProtocolFakeAdapter($nonce);
        $agent = new Agent($adapter);
        $agent->addTool(
            'stamp',
            fn (string $nonce): string => hash('sha256', 'tool::'.$nonce)
        );

        $conversation = new Conversation($agent);
        $streamed = '';
        $streamChunks = 0;
        $conversation->listen(function (string $chunk) use (&$streamed, &$streamChunks): void {
            if ($chunk !== '') {
                $streamChunks++;
            }
            $streamed .= $chunk;
        });
        $conversation->message(new User('user-1'), new Message('Use stamp tool for my token.'));

        $final = $conversation->send();
        $expected = hash('sha256', 'tool::'.$nonce);

        $this->assertSame('STAMP: '.$expected, $final->getContent());
        $this->assertSame('STAMP: '.$expected, $streamed);
        $this->assertGreaterThan(1, $streamChunks);
        $this->assertStringNotContainsString('"type"', $streamed);

        $messages = $conversation->getMessages();
        $this->assertGreaterThanOrEqual(5, count($messages));

        $assistantWithToolCall = null;
        foreach ($messages as $message) {
            if ($message->getRole() === 'assistant' && $message->hasToolCalls()) {
                $assistantWithToolCall = $message;
                break;
            }
        }

        $this->assertInstanceOf(Message::class, $assistantWithToolCall);
        $this->assertTrue($assistantWithToolCall->getToolCalls()[0]->isSuccess());
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
        if (! $last instanceof Message || $last->getRole() !== 'user') {
            throw new \RuntimeException('Expected a generic tool result user message before final answer');
        }

        if (! str_contains($last->getContent(), '"type":"tool_result"')) {
            throw new \RuntimeException('Expected generic tool_result payload');
        }

        return new Message('{"name":"final_response","type":"final","content":"STAMP: '.hash('sha256', 'tool::'.$this->nonce).'"}');
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

class ToolProtocolFakeAdapter extends Adapter
{
    private int $sendCount = 0;

    public function __construct(
        private readonly string $nonce
    ) {}

    public function getName(): string
    {
        return 'generic-tool-protocol-fake';
    }

    /**
     * @param  array<Message>  $messages
     */
    public function send(array $messages, ?callable $listener = null): Message
    {
        $this->sendCount++;

        if ($this->sendCount === 1) {
            if ($listener !== null) {
                $listener('{"type":"tool_call","id":"call_1","name":"stamp","arguments":{"nonce":"');
                $listener($this->nonce.'"}}');
            }

            return new Message(
                '{"type":"tool_call","id":"call_1","name":"stamp","arguments":{"nonce":"'.$this->nonce.'"}}'
            );
        }

        $expected = hash('sha256', 'tool::'.$this->nonce);
        if ($listener !== null) {
            $listener('{"type":"final","content":"ST');
            $listener('AMP: '.$expected.'"}');
        }

        return new Message('{"type":"final","content":"STAMP: '.$expected.'"}');
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
