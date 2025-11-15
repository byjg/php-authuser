<?php

namespace Tests;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\Authenticate\Repository\UserPropertiesRepository;
use ByJG\Authenticate\Repository\UsersRepository;
use ByJG\Authenticate\Service\UsersService;
use ByJG\Util\Uri;

class UsersDBDatasetByUsernameTestUsersBase extends TestUsersBase
{

    const CONNECTION_STRING='sqlite:///tmp/teste.db';

    protected $db;

    #[\Override]
    public function __setUp($loginField)
    {
        $this->prefix = "";
        $this->loginField = $loginField;

        $this->db = Factory::getDbInstance(self::CONNECTION_STRING);
        $this->db->execute('create table users (
            userid integer primary key  autoincrement,
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
            id integer primary key  autoincrement,
            userid integer,
            name varchar(45),
            value varchar(45));'
        );

        $executor = DatabaseExecutor::using($this->db);
        $usersRepository = new UsersRepository($executor, UserModel::class);
        $propertiesRepository = new UserPropertiesRepository($executor, UserPropertiesModel::class);
        $this->object = new UsersService(
            $usersRepository,
            $propertiesRepository,
            $loginField
        );

        $user = $this->object->addUser('User 1', 'user1', 'user1@gmail.com', 'pwd1');
        $this->assertEquals(1, $user->getUserid());
        $this->assertEquals('User 1', $user->getName());
        $this->assertEquals('user1', $user->getUsername());
        $this->assertEquals('a63d4b132a9a1d3430f9ae507825f572449e0d17', $user->getPassword());
        $this->assertEquals('', $user->getRole());
        $this->assertNotNull($user->getCreatedAt());
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $user->getCreatedAt());

        $this->object->addUser('User 2', 'user2', 'user2@gmail.com', 'pwd2');
        $this->object->addUser('User 3', 'user3', 'user3@gmail.com', 'pwd3');
    }

    #[\Override]
    public function setUp(): void
    {
        $this->__setUp(UsersService::LOGIN_IS_USERNAME);
    }

    #[\Override]
    public function tearDown(): void
    {
        $uri = new Uri(self::CONNECTION_STRING);
        unlink($uri->getPath());
        $this->object = null;
    }

    /**
     * @return void
     */
    #[\Override]
    public function testAddUser()
    {
        $this->object->addUser('John Doe', 'john', 'johndoe@gmail.com', 'mypassword');

        $login = $this->__chooseValue('john', 'johndoe@gmail.com');

        $user = $this->object->getByLogin($login);
        $this->assertEquals('4', $user->getUserid());
        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('john', $user->getUsername());
        $this->assertEquals('johndoe@gmail.com', $user->getEmail());
        $this->assertEquals('91dfd9ddb4198affc5c194cd8ce6d338fde470e2', $user->getPassword());
        $this->assertEquals('', $user->getRole());
        $this->assertNotNull($user->getCreatedAt());
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $user->getCreatedAt());

        // Setting role
        $user->setRole('admin');
        $this->object->save($user);

        $user2 = $this->object->getByLogin($login);
        $this->assertEquals('admin', $user2->getRole());
    }

    /**
     * @return void
     */
    #[\Override]
    public function testCreateAuthToken()
    {
        $login = $this->__chooseValue('user2', 'user2@gmail.com');
        $this->expectedToken('tokenValue', $login, 2);
    }

    /**
     * @return void
     */
    // TODO: This test is currently disabled because the new architecture uses
    // compile-time attributes instead of runtime mapper definitions.
    // To achieve custom mappers, users should create a custom UserModel subclass
    // with different mapper classes in the FieldAttribute annotations.
    /*
    public function testWithUpdateValue()
    {
        // This test was checking the old UserDefinition runtime mapper customization.
        // In the new architecture, mappers are defined in the model's #[FieldAttribute]
        // annotations at compile time, not modified at runtime.
    }
    */

    /**
     * @return void
     */
    #[\Override]
    public function testSaveAndSave()
    {
        $user = $this->object->getById("1");
        $this->object->save($user);

        $user2 = $this->object->getById("1");

        // Compare all fields except updated_at which changes on each save
        $this->assertEquals($user->getUserid(), $user2->getUserid());
        $this->assertEquals($user->getName(), $user2->getName());
        $this->assertEquals($user->getEmail(), $user2->getEmail());
        $this->assertEquals($user->getUsername(), $user2->getUsername());
        $this->assertEquals($user->getPassword(), $user2->getPassword());
        $this->assertEquals($user->getRole(), $user2->getRole());
        $this->assertEquals($user->getCreatedAt(), $user2->getCreatedAt());
        // updated_at is expected to be different due to the save operation
    }
}