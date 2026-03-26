<?php

namespace Utopia\Tests\Agents\Adapters;

use Utopia\Agents\Adapter as AgentAdapter;
use Utopia\Agents\Adapters\Anthropic;

class AnthropicTest extends Adapter
{
    protected function createAdapter(): AgentAdapter
    {
        return new Anthropic('test-api-key');
    }

    protected function expectedName(): string
    {
        return 'anthropic';
    }

    protected function expectedDefaultModel(): string
    {
        return Anthropic::MODEL_CLAUDE_3_HAIKU;
    }

    protected function expectedModels(): array
    {
        return [
            Anthropic::MODEL_CLAUDE_4_OPUS,
            Anthropic::MODEL_CLAUDE_3_OPUS,
            Anthropic::MODEL_CLAUDE_4_SONNET,
            Anthropic::MODEL_CLAUDE_3_7_SONNET,
            Anthropic::MODEL_CLAUDE_3_5_SONNET,
            Anthropic::MODEL_CLAUDE_3_5_HAIKU,
            Anthropic::MODEL_CLAUDE_3_HAIKU,
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

    public function testSseStreamProcessing(): void
    {
        $this->assertSseStreamingBehavior($this->createAdapter(), [
            '{"type":"content_block_delta","delta":{"type":"text_delta","text":"Hel',
            'lo"}}'."\n",
        ], 'Hello');
    }

    public function testSseUsageTokensAreCountedAcrossEvents(): void
    {
        $adapter = $this->createAdapter();

        $this->beginStream($adapter);
        $this->processStreamChunk($adapter, $this->createChunk(
            'data: {"type":"message_start","message":{"usage":{"input_tokens":3,"output_tokens":0,"cache_creation_input_tokens":2,"cache_read_input_tokens":1}}}'."\n"
        ));
        $this->processStreamChunk($adapter, $this->createChunk(
            'data: {"type":"message_delta","usage":{"input_tokens":0,"output_tokens":4}}'."\n"
        ));
        $this->endStream($adapter);

        $this->assertSame(3, $adapter->getInputTokens());
        $this->assertSame(4, $adapter->getOutputTokens());
        $this->assertSame(2, $adapter->getCacheCreationInputTokens());
        $this->assertSame(1, $adapter->getCacheReadInputTokens());
    }
}
