<?php

namespace Utopia\Tests\Agents\Adapters;

use Utopia\Agents\Adapter as AgentAdapter;
use Utopia\Fetch\Chunk;

abstract class StreamingAdapterSseContract extends Adapter
{
    /**
     * @return array<string>
     */
    abstract protected function streamChunks(): array;

    abstract protected function expectedStreamedContent(): string;

    public function testSseChunkFragmentsAreReassembled(): void
    {
        $adapter = $this->createAdapter();

        $this->beginStream($adapter);
        $output = '';
        foreach ($this->streamChunks() as $chunk) {
            $output .= $this->processStreamChunk($adapter, $this->createChunk($chunk));
        }
        $this->endStream($adapter);

        $this->assertSame($this->expectedStreamedContent(), $output);
    }

    public function testSseListenerReceivesExpectedTokens(): void
    {
        $adapter = $this->createAdapter();

        $listenerOutput = '';
        $listener = function (string $token) use (&$listenerOutput): void {
            $listenerOutput .= $token;
        };

        $this->beginStream($adapter);
        foreach ($this->streamChunks() as $chunk) {
            $this->processStreamChunk($adapter, $this->createChunk($chunk), $listener);
        }
        $this->endStream($adapter);

        $this->assertSame($this->expectedStreamedContent(), $listenerOutput);
    }

    public function testSseDoneMarkerIsIgnored(): void
    {
        $adapter = $this->createAdapter();

        $this->beginStream($adapter);
        $result = $this->processStreamChunk($adapter, $this->createChunk("data: [DONE]\n"));
        $this->endStream($adapter);

        $this->assertSame('', $result);
    }

    protected function createChunk(string $data): Chunk
    {
        $chunk = $this->createStub(Chunk::class);
        $chunk
            ->method('getData')
            ->willReturn($data);

        return $chunk;
    }

    protected function beginStream(AgentAdapter $adapter): void
    {
        $method = new \ReflectionMethod(AgentAdapter::class, 'beginStreamProcessing');
        $method->setAccessible(true);
        $method->invoke($adapter);
    }

    protected function endStream(AgentAdapter $adapter): void
    {
        $method = new \ReflectionMethod(AgentAdapter::class, 'endStreamProcessing');
        $method->setAccessible(true);
        $method->invoke($adapter);
    }

    protected function processStreamChunk(AgentAdapter $adapter, Chunk $chunk, ?callable $listener = null): string
    {
        $method = new \ReflectionMethod($adapter, 'process');
        $method->setAccessible(true);

        return $method->invoke($adapter, $chunk, $listener);
    }
}
