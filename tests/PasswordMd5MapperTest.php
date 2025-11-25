<?php

namespace Tests;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;
use ByJG\Authenticate\Enum\LoginField;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\Authenticate\Repository\UserPropertiesRepository;
use ByJG\Authenticate\Repository\UsersRepository;
use ByJG\Authenticate\Service\UsersService;
use ByJG\Util\Uri;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\PasswordMd5Mapper;
use Tests\Fixture\UserModelMd5;

class PasswordMd5MapperTest extends TestCase
{
    const CONNECTION_STRING = 'sqlite:///tmp/teste_md5.db';

    protected $db;
    protected $service;

    #[\Override]
    public function setUp(): void
    {
        $this->db = Factory::getDbInstance(self::CONNECTION_STRING);
        $this->db->execute('create table users (
            userid integer primary key autoincrement,
            name varchar(45),
            email varchar(200),
            username varchar(20),
            password varchar(40),
            created_at datetime default (datetime(\'2017-12-04\')),
            updated_at datetime,
            deleted_at datetime,
            role varchar(20));'
        );

        $this->db->execute('create table users_property (
            id integer primary key autoincrement,
            userid integer,
            name varchar(45),
            value varchar(45));'
        );

        // Create repositories and service with custom MD5 password mapper via UserModelMd5
        $executor = DatabaseExecutor::using($this->db);
        $usersRepository = new UsersRepository($executor, UserModelMd5::class);
        $propertiesRepository = new UserPropertiesRepository($executor, UserPropertiesModel::class);
        $this->service = new UsersService(
            $usersRepository,
            $propertiesRepository,
            LoginField::Username
        );
    }

    #[\Override]
    public function tearDown(): void
    {
        $uri = new Uri(self::CONNECTION_STRING);
        if (file_exists($uri->getPath())) {
            unlink($uri->getPath());
        }
        $this->db = null;
        $this->service = null;
    }

    public function testPasswordIsHashedWithMd5OnSave(): void
    {
        // Add a user with a plain text password
        $plainPassword = 'mySecretPassword123';
        $user = $this->service->addUser('John Doe', 'johndoe', 'john@example.com', $plainPassword);

        // Verify the password was hashed with MD5
        $expectedHash = md5($plainPassword);
        $this->assertEquals($expectedHash, $user->getPassword());
        $this->assertEquals(32, strlen($user->getPassword())); // MD5 is always 32 chars
        $this->assertTrue(ctype_xdigit($user->getPassword())); // MD5 is hexadecimal
    }

    public function testPasswordIsNotRehashedIfAlreadyMd5(): void
    {
        // Create a user
        $user = $this->service->addUser('Jane Doe', 'janedoe', 'jane@example.com', 'password123');
        $originalHash = $user->getPassword();

        // Update the user without changing password
        $user->setName('Jane Smith');
        $updatedUser = $this->service->save($user);

        // Password hash should remain the same
        $this->assertEquals($originalHash, $updatedUser->getPassword());
    }

    public function testPasswordIsHashedWhenUpdating(): void
    {
        // Create a user
        $user = $this->service->addUser('Jane Doe', 'janedoe', 'jane@example.com', 'oldPassword');
        $oldHash = $user->getPassword();

        // Update the password with a new plain text password
        $newPlainPassword = 'newPassword123';
        $user->setPassword($newPlainPassword);
        $updatedUser = $this->service->save($user);

        // Verify the new password was hashed with MD5
        $expectedNewHash = md5($newPlainPassword);
        $this->assertEquals($expectedNewHash, $updatedUser->getPassword());

        // Verify it's different from the old hash
        $this->assertNotEquals($oldHash, $updatedUser->getPassword());

        // Verify user can login with new password
        $authenticatedUser = $this->service->isValidUser('janedoe', $newPlainPassword);
        $this->assertNotNull($authenticatedUser);

        // Verify user cannot login with old password
        $authenticatedUserOld = $this->service->isValidUser('janedoe', 'oldPassword');
        $this->assertNull($authenticatedUserOld);
    }

    public function testPasswordRemainsUnchangedWhenUpdatingOtherFields(): void
    {
        // Create a user
        $originalPassword = 'myPassword123';
        $user = $this->service->addUser('John Smith', 'johnsmith', 'john@example.com', $originalPassword);
        $originalHash = $user->getPassword();

        // Update other fields WITHOUT touching the password
        $user->setName('John Updated');
        $user->setEmail('johnupdated@example.com');
        $user->setRole('admin');
        $updatedUser = $this->service->save($user);

        // Verify the password hash remained exactly the same
        $this->assertEquals($originalHash, $updatedUser->getPassword());
        $this->assertEquals(32, strlen($updatedUser->getPassword()));

        // Verify other fields were updated
        $this->assertEquals('John Updated', $updatedUser->getName());
        $this->assertEquals('johnupdated@example.com', $updatedUser->getEmail());
        $this->assertEquals('admin', $updatedUser->getRole());

        // Verify user can still login with original password
        $authenticatedUser = $this->service->isValidUser('johnsmith', $originalPassword);
        $this->assertNotNull($authenticatedUser);
        $this->assertEquals('John Updated', $authenticatedUser->getName());
    }

    public function testUserCanLoginWithMd5HashedPassword(): void
    {
        // Add a user
        $plainPassword = 'testPassword456';
        $this->service->addUser('Test User', 'testuser', 'test@example.com', $plainPassword);

        // Verify user can login with the plain text password
        $authenticatedUser = $this->service->isValidUser('testuser', $plainPassword);

        $this->assertNotNull($authenticatedUser);
        $this->assertEquals('Test User', $authenticatedUser->getName());
        $this->assertEquals('testuser', $authenticatedUser->getUsername());
    }

    public function testUserCannotLoginWithWrongPassword(): void
    {
        // Add a user
        $this->service->addUser('Test User', 'testuser', 'test@example.com', 'correctPassword');

        // Try to login with wrong password
        $authenticatedUser = $this->service->isValidUser('testuser', 'wrongPassword');

        $this->assertNull($authenticatedUser);
    }

    public function testEmptyPasswordReturnsNull(): void
    {
        $mapper = new PasswordMd5Mapper();

        $this->assertNull($mapper->processedValue('', null));
        $this->assertNull($mapper->processedValue(null, null));
    }

    public function testExistingMd5HashIsNotRehashed(): void
    {
        $mapper = new PasswordMd5Mapper();
        $existingHash = '5f4dcc3b5aa765d61d8327deb882cf99'; // MD5 of 'password'

        $result = $mapper->processedValue($existingHash, null);

        $this->assertEquals($existingHash, $result);
    }
}
