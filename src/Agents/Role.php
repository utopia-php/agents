<?php

namespace Utopia\Agents;

abstract class Role
{
    /**
     * Role constants
     */
    public const ROLE_ASSISTANT = 'assistant';

    public const ROLE_USER = 'user';

    /**
     * @var string
     */
    protected string $id;

    /**
     * @var string
     */
    protected string $name;

    /**
     * Create a new role
     *
     * @param  string  $id
     * @param  string  $name
     */
    public function __construct(string $id, string $name = '')
    {
        $this->id = $id;
        $this->name = $name;
    }

    /**
     * Get the role's ID
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the role's name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the role's name
     *
     * @param  string  $name
     * @return self
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the role identifier
     *
     * @return string
     */
    abstract public function getIdentifier(): string;
}
