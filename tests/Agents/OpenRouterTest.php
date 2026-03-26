<?php

namespace Tests\Utopia\Agents;

use PHPUnit\Framework\TestCase;
use Utopia\Agents\Adapters\OpenRouter;
use Utopia\Agents\Adapters\OpenRouter\Models as OpenRouterModels;

class OpenRouterTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $adapter = new OpenRouter('test-api-key');

        $this->assertSame('openrouter', $adapter->getName());
        $this->assertSame(OpenRouterModels::MODEL_OPENAI_GPT_4O, $adapter->getModel());
        $this->assertSame('https://openrouter.ai/api/v1/chat/completions', $adapter->getEndpoint());
    }

    public function testGetModels(): void
    {
        $adapter = new OpenRouter('test-api-key');

        $models = $adapter->getModels();

        $this->assertContains(OpenRouterModels::MODEL_OPENAI_GPT_4O, $models);
        $this->assertContains(OpenRouterModels::MODEL_ANTHROPIC_CLAUDE_SONNET_4, $models);
        $this->assertContains('openai/gpt-5-nano', $models);
    }

    public function testModelSetterAcceptsArbitraryRoutedModel(): void
    {
        $adapter = new OpenRouter('test-api-key');

        $adapter->setModel('meta-llama/llama-3.3-70b-instruct');

        $this->assertSame('meta-llama/llama-3.3-70b-instruct', $adapter->getModel());
    }

    public function testSchemaNotSupported(): void
    {
        $adapter = new OpenRouter('test-api-key');

        $this->assertFalse($adapter->isSchemaSupported());
    }

    public function testCustomEndpoint(): void
    {
        $adapter = new OpenRouter(
            apiKey: 'test-api-key',
            endpoint: 'https://custom-proxy.example/v1/chat/completions'
        );

        $this->assertSame('https://custom-proxy.example/v1/chat/completions', $adapter->getEndpoint());
    }
}
