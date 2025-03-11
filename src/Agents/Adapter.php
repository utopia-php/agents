<?php

namespace Utopia\Agents;

abstract class Adapter
{
    /**
     * Send a message to the AI model
     *
     * @param array<string, mixed> $messages Array of messages in the conversation
     * @return array<string, mixed> Response from the AI model
     * @throws \Exception
     */
    abstract public function send(array $messages): array;

    /**
     * Get available models for this adapter
     *
     * @return array<string>
     */
    abstract public function getModels(): array;

    /**
     * Get the currently selected model
     *
     * @return string
     */
    abstract public function getModel(): string;

    /**
     * Set the model to use
     *
     * @param string $model
     * @return self
     * @throws \Exception if model is not supported
     */
    abstract public function setModel(string $model): self;
} 