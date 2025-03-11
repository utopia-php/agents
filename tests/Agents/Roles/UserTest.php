<?php

namespace Tests\Utopia\Agents\Roles;

use PHPUnit\Framework\TestCase;
use Utopia\Agents\Role;
use Utopia\Agents\Roles\User;

class UserTest extends TestCase
{
    public function testConstructor()
    {
        $id = 'test-id';
        $name = 'Test User';
        
        $user = new User($id, $name);
        
        $this->assertEquals($id, $user->getId());
        $this->assertEquals($name, $user->getName());
    }

    public function testConstructorWithoutName()
    {
        $id = 'test-id';
        
        $user = new User($id);
        
        $this->assertEquals($id, $user->getId());
        $this->assertEquals('', $user->getName());
    }

    public function testGetIdentifier()
    {
        $user = new User('test-id');
        
        $this->assertEquals(Role::ROLE_USER, $user->getIdentifier());
    }
} 