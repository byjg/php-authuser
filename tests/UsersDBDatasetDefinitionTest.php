<?php

namespace Tests;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;
use ByJG\Authenticate\Exception\UserExistsException;
use ByJG\Authenticate\Repository\UserPropertiesRepository;
use ByJG\Authenticate\Repository\UsersRepository;
use ByJG\Authenticate\Service\UsersService;
use ByJG\MicroOrm\Exception\OrmBeforeInvalidException;
use ByJG\MicroOrm\Exception\OrmInvalidFieldsException;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\Util\Uri;
use Override;
use ReflectionException;
use Tests\Fixture\MyUserModel;
use Tests\Fixture\MyUserPropertiesModel;

class UsersDBDatasetDefinitionTest extends UsersDBDatasetByUsernameTestUsersBase
{
    protected $db;

    /**
     * @param $loginField
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws OrmModelInvalidException
     * @throws UserExistsException
     * @throws ReflectionException
     */
    #[Override]
    public function __setUp($loginField)
    {
        $this->prefix = "";
        $this->loginField = $loginField;

        $this->db = Factory::getDbInstance(self::CONNECTION_STRING);
        $this->db->execute('create table mytable (
            myuserid integer primary key  autoincrement,
            myname varchar(45),
            myemail varchar(200),
            myusername varchar(20),
            mypassword varchar(40),
            myotherfield varchar(40),
            mycreated datetime default (datetime(\'2017-12-04\')),
            myadmin char(1));'
        );

        $this->db->execute('create table theirproperty (
            theirid integer primary key  autoincrement,
            theiruserid integer,
            theirname varchar(45),
            theirvalue varchar(45));'
        );

        $executor = DatabaseExecutor::using($this->db);
        $usersRepository = new UsersRepository($executor, MyUserModel::class);
        $propertiesRepository = new UserPropertiesRepository($executor, MyUserPropertiesModel::class);
        $this->object = new UsersService(
            $usersRepository,
            $propertiesRepository,
            $loginField
        );

        $this->object->save(
            new MyUserModel('User 1', 'user1@gmail.com', 'user1', 'pwd1', 'no', 'other 1')
        );
        $this->object->save(
            new MyUserModel('User 2', 'user2@gmail.com', 'user2', 'pwd2', 'no', 'other 2')
        );
        $this->object->save(
            new MyUserModel('User 3', 'user3@gmail.com', 'user3', 'pwd3', 'no', 'other 3')
        );
    }

    /**
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws OrmModelInvalidException
     * @throws ReflectionException
     * @throws UserExistsException
     */
    #[Override]
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
     * @throws UserExistsException
     * @throws DatabaseException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     *
     * @return void
     */
    #[Override]
    public function testAddUser()
    {
        $this->object->save(new MyUserModel('John Doe', 'johndoe@gmail.com', 'john', 'mypassword', 'no', 'other john'));

        $login = $this->__chooseValue('john', 'johndoe@gmail.com');

        $user = $this->object->getByLogin($login);
        $this->assertEquals('4', $user->getUserid());
        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('john', $user->getUsername());
        $this->assertEquals('johndoe@gmail.com', $user->getEmail());
        $this->assertEquals('91dfd9ddb4198affc5c194cd8ce6d338fde470e2', $user->getPassword());
        $this->assertEquals('no', $user->getAdmin());
        /** @psalm-suppress UndefinedMethod Check UserModel::__call */
        $this->assertEquals('other john', $user->getOtherfield());
        $this->assertEquals('2017-12-04 00:00:00', $user->getCreated()); // Database default value

        // Setting as Admin
        $user->setAdmin('y');
        $this->object->save($user);

        $user2 = $this->object->getByLogin($login);
        $this->assertEquals('y', $user2->getAdmin());
    }

    // TODO: These tests are currently disabled because the new architecture uses
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

    public function testDefineGenerateKeyWithInterface()
    {
        // This test was checking custom key generation via UserDefinition.
        // In the new architecture, custom key generation should be done via
        // MicroOrm's FieldAttribute primaryKey with a custom generator.
    }

    public function testDefineGenerateKeyWithString()
    {
        // This test was checking custom key generation via UserDefinition.
        // In the new architecture, custom key generation should be done via
        // MicroOrm's FieldAttribute primaryKey with a custom generator.
    }

    public function testDefineGenerateKeyClosureThrowsException()
    {
        // UserDefinition has been removed in the new architecture.
    }
    */
}
