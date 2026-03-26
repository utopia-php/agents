<?php

namespace Utopia\Tests\Agents\Adapters;

use Utopia\Agents\Adapter as AgentAdapter;
use Utopia\Agents\Adapters\Deepseek;

class DeepseekAdapterTest extends StreamingAdapterSseContract
{
    protected function createAdapter(): AgentAdapter
    {
        return new Deepseek('test-api-key');
    }

    protected function expectedName(): string
    {
        return 'deepseek';
    }

    protected function expectedDefaultModel(): string
    {
        return Deepseek::MODEL_DEEPSEEK_CHAT;
    }

    protected function expectedModels(): array
    {
        return [
            Deepseek::MODEL_DEEPSEEK_CHAT,
            Deepseek::MODEL_DEEPSEEK_CODER,
        ];
    }

    protected function expectsSchemaSupport(): bool
    {
        return true;
    }

    protected function expectsEmbeddingSupport(): bool
    {
        return false;
    }

    protected function streamChunks(): array
    {
        return [
            'data: {"choices":[{"delta":{"content":"Hel',
            'lo"}}]}'."\n",
        ];
    }

    protected function expectedStreamedContent(): string
    {
        return 'Hello';
    }

    public function testSseUsageTokensAreCounted(): void
    {
        $adapter = $this->createAdapter();

        $this->beginStream($adapter);
        $this->processStreamChunk($adapter, $this->createChunk(
            'data: {"choices":[{"delta":{"content":"Ok"}}],"usage":{"prompt_tokens":12,"completion_tokens":7}}'."\n"
        ));
        $this->endStream($adapter);

        $this->assertSame(12, $adapter->getInputTokens());
        $this->assertSame(7, $adapter->getOutputTokens());
    }
}
