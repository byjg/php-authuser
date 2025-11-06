<?php

namespace Tests;

use ByJG\AnyDataset\Db\Factory;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\UsersDBDataset;
use ByJG\Util\Uri;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\PasswordMd5Mapper;

class PasswordMd5MapperTest extends TestCase
{
    const CONNECTION_STRING = 'sqlite:///tmp/teste_md5.db';

    protected $db;
    protected $userDefinition;
    protected $propertyDefinition;

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
            created datetime default (datetime(\'2017-12-04\')),
            admin char(1));'
        );

        $this->db->execute('create table users_property (
            id integer primary key autoincrement,
            userid integer,
            name varchar(45),
            value varchar(45));'
        );

        // Create user definition with custom MD5 password mapper
        $this->userDefinition = new UserDefinition('users', UserModel::class, UserDefinition::LOGIN_IS_USERNAME);
        $this->userDefinition->defineMapperForUpdate(UserDefinition::FIELD_PASSWORD, PasswordMd5Mapper::class);
        $this->userDefinition->markPropertyAsReadOnly(UserDefinition::FIELD_CREATED);

        $this->propertyDefinition = new UserPropertiesDefinition();
    }

    #[\Override]
    public function tearDown(): void
    {
        $uri = new Uri(self::CONNECTION_STRING);
        if (file_exists($uri->getPath())) {
            unlink($uri->getPath());
        }
        $this->db = null;
        $this->userDefinition = null;
        $this->propertyDefinition = null;
    }

    public function testPasswordIsHashedWithMd5OnSave(): void
    {
        $dataset = new UsersDBDataset($this->db, $this->userDefinition, $this->propertyDefinition);

        // Add a user with a plain text password
        $plainPassword = 'mySecretPassword123';
        $user = $dataset->addUser('John Doe', 'johndoe', 'john@example.com', $plainPassword);

        // Verify the password was hashed with MD5
        $expectedHash = md5($plainPassword);
        $this->assertEquals($expectedHash, $user->getPassword());
        $this->assertEquals(32, strlen($user->getPassword())); // MD5 is always 32 chars
        $this->assertTrue(ctype_xdigit($user->getPassword())); // MD5 is hexadecimal
    }

    public function testPasswordIsNotRehashedIfAlreadyMd5(): void
    {
        $dataset = new UsersDBDataset($this->db, $this->userDefinition, $this->propertyDefinition);

        // Create a user
        $user = $dataset->addUser('Jane Doe', 'janedoe', 'jane@example.com', 'password123');
        $originalHash = $user->getPassword();

        // Update the user without changing password
        $user->setName('Jane Smith');
        $updatedUser = $dataset->save($user);

        // Password hash should remain the same
        $this->assertEquals($originalHash, $updatedUser->getPassword());
    }

    public function testPasswordIsHashedWhenUpdating(): void
    {
        $dataset = new UsersDBDataset($this->db, $this->userDefinition, $this->propertyDefinition);

        // Create a user
        $user = $dataset->addUser('Jane Doe', 'janedoe', 'jane@example.com', 'oldPassword');
        $oldHash = $user->getPassword();

        // Update the password with a new plain text password
        $newPlainPassword = 'newPassword123';
        $user->setPassword($newPlainPassword);
        $updatedUser = $dataset->save($user);

        // Verify the new password was hashed with MD5
        $expectedNewHash = md5($newPlainPassword);
        $this->assertEquals($expectedNewHash, $updatedUser->getPassword());

        // Verify it's different from the old hash
        $this->assertNotEquals($oldHash, $updatedUser->getPassword());

        // Verify user can login with new password
        $authenticatedUser = $dataset->isValidUser('janedoe', $newPlainPassword);
        $this->assertNotNull($authenticatedUser);

        // Verify user cannot login with old password
        $authenticatedUserOld = $dataset->isValidUser('janedoe', 'oldPassword');
        $this->assertNull($authenticatedUserOld);
    }

    public function testPasswordRemainsUnchangedWhenUpdatingOtherFields(): void
    {
        $dataset = new UsersDBDataset($this->db, $this->userDefinition, $this->propertyDefinition);

        // Create a user
        $originalPassword = 'myPassword123';
        $user = $dataset->addUser('John Smith', 'johnsmith', 'john@example.com', $originalPassword);
        $originalHash = $user->getPassword();

        // Update other fields WITHOUT touching the password
        $user->setName('John Updated');
        $user->setEmail('johnupdated@example.com');
        $user->setAdmin('y');
        $updatedUser = $dataset->save($user);

        // Verify the password hash remained exactly the same
        $this->assertEquals($originalHash, $updatedUser->getPassword());
        $this->assertEquals(32, strlen($updatedUser->getPassword()));

        // Verify other fields were updated
        $this->assertEquals('John Updated', $updatedUser->getName());
        $this->assertEquals('johnupdated@example.com', $updatedUser->getEmail());
        $this->assertEquals('y', $updatedUser->getAdmin());

        // Verify user can still login with original password
        $authenticatedUser = $dataset->isValidUser('johnsmith', $originalPassword);
        $this->assertNotNull($authenticatedUser);
        $this->assertEquals('John Updated', $authenticatedUser->getName());
    }

    public function testUserCanLoginWithMd5HashedPassword(): void
    {
        $dataset = new UsersDBDataset($this->db, $this->userDefinition, $this->propertyDefinition);

        // Add a user
        $plainPassword = 'testPassword456';
        $dataset->addUser('Test User', 'testuser', 'test@example.com', $plainPassword);

        // Verify user can login with the plain text password
        $authenticatedUser = $dataset->isValidUser('testuser', $plainPassword);

        $this->assertNotNull($authenticatedUser);
        $this->assertEquals('Test User', $authenticatedUser->getName());
        $this->assertEquals('testuser', $authenticatedUser->getUsername());
    }

    public function testUserCannotLoginWithWrongPassword(): void
    {
        $dataset = new UsersDBDataset($this->db, $this->userDefinition, $this->propertyDefinition);

        // Add a user
        $dataset->addUser('Test User', 'testuser', 'test@example.com', 'correctPassword');

        // Try to login with wrong password
        $authenticatedUser = $dataset->isValidUser('testuser', 'wrongPassword');

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
