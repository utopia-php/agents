<?php

namespace Tests\Utopia\Agents\Roles;

use PHPUnit\Framework\TestCase;
use Utopia\Agents\Role;
use Utopia\Agents\Roles\Assistant;

class AssistantTest extends TestCase
{
    public function test_constructor(): void
    {
        $id = 'test-id';
        $name = 'Test Assistant';

        $assistant = new Assistant($id, $name);

        $this->assertSame($id, $assistant->getId());
        $this->assertSame($name, $assistant->getName());
    }

    public function test_constructor_without_name(): void
    {
        $id = 'test-id';

        $assistant = new Assistant($id);

        $this->assertSame($id, $assistant->getId());
        $this->assertSame('', $assistant->getName());
    }

    public function test_get_identifier(): void
    {
        $assistant = new Assistant('test-id');

        $this->assertSame(Role::ROLE_ASSISTANT, $assistant->getIdentifier());
    }
}
