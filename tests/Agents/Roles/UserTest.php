<?php

namespace Tests\Utopia\Agents\Roles;

use PHPUnit\Framework\TestCase;
use Utopia\Agents\Role;
use Utopia\Agents\Roles\User;

class UserTest extends TestCase
{
    public function test_constructor(): void
    {
        $id = 'test-id';
        $name = 'Test User';

        $user = new User($id, $name);

        $this->assertSame($id, $user->getId());
        $this->assertSame($name, $user->getName());
    }

    public function test_constructor_without_name(): void
    {
        $id = 'test-id';

        $user = new User($id);

        $this->assertSame($id, $user->getId());
        $this->assertSame('', $user->getName());
    }

    public function test_get_identifier(): void
    {
        $user = new User('test-id');

        $this->assertSame(Role::ROLE_USER, $user->getIdentifier());
    }
}
