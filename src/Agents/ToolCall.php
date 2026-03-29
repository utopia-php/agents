<?php

namespace Utopia\Agents;

class ToolCall
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_ERROR = 'error';

    protected string $id;

    protected string $name;

    /**
     * @var array<string, mixed>|string
     */
    protected array|string $arguments;

    protected string $status = self::STATUS_PENDING;

    protected ?string $error = null;

    /**
     * @param  array<string, mixed>|string  $arguments
     */
    public function __construct(string $id, string $name, array|string $arguments = '{}')
    {
        $this->id = $id;
        $this->name = $name;
        $this->arguments = $arguments;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>|string
     */
    public function getArguments(): array|string
    {
        return $this->arguments;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    public function markSuccess(): self
    {
        $this->status = self::STATUS_SUCCESS;
        $this->error = null;

        return $this;
    }

    public function markError(string $error): self
    {
        $this->status = self::STATUS_ERROR;
        $this->error = $error;

        return $this;
    }
}
