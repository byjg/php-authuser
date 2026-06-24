<?php

namespace Tests;

use ByJG\Authenticate\Enum\LoginField;

class UsersDBDatasetByEmailOrUsernameTest extends UsersDBDatasetByUsernameTestUsersBase
{
    #[\Override]
    public function setUp(): void
    {
        $this->__setUp(LoginField::EmailOrUsername);
    }

    #[\Override]
    public function testIsValidUser(): void
    {
        // Both username and email must work
        $user = $this->object->isValidUser('user3', 'pwd3');
        $this->assertEquals('User 3', $user->getName());

        $user = $this->object->isValidUser('user3@gmail.com', 'pwd3');
        $this->assertEquals('User 3', $user->getName());

        // Wrong password returns null regardless of field used
        $this->assertNull($this->object->isValidUser('user3', 'wrongpwd'));
        $this->assertNull($this->object->isValidUser('user3@gmail.com', 'wrongpwd'));

        // Non-existent identifier returns null
        $this->assertNull($this->object->isValidUser('nonexistent', 'pwd3'));
    }

    public function testGetByLoginWithUsername(): void
    {
        $user = $this->object->getByLogin('user1');
        $this->assertNotNull($user);
        $this->assertEquals('User 1', $user->getName());
        $this->assertEquals('user1', $user->getUsername());
    }

    public function testGetByLoginWithEmail(): void
    {
        $user = $this->object->getByLogin('user1@gmail.com');
        $this->assertNotNull($user);
        $this->assertEquals('User 1', $user->getName());
        $this->assertEquals('user1@gmail.com', $user->getEmail());
    }

    public function testGetLoginValueReturnsUsername(): void
    {
        $user = $this->object->getByLogin('user2');
        $this->assertEquals('user2', $this->object->getLoginValue($user));
    }

    public function testGetLoginFieldIsEmailOrUsername(): void
    {
        $this->assertEquals(LoginField::EmailOrUsername, $this->object->getLoginField());
    }
}