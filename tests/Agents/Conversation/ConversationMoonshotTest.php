<?php

namespace Utopia\Tests\Agents\Conversation;

use Utopia\Agents\Adapter;
use Utopia\Agents\Adapters\Moonshot;

class ConversationMoonshotTest extends ConversationBase
{
    protected function createAdapter(): Adapter
    {
        $apiKey = getenv('LLM_KEY_MOONSHOT');

        if ($apiKey === false || empty($apiKey)) {
            throw new \RuntimeException('LLM_KEY_MOONSHOT environment variable is not set');
        }

        return new Moonshot(
            $apiKey,
            Moonshot::MODEL_KIMI_K2_5,
            1024,
            1.0
        );
    }

    protected function getAgentDescription(): string
    {
        return 'Test Moonshot Agent Description';
    }
}
