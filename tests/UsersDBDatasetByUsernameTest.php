<?php

namespace ByJG\Authenticate;

require_once 'UsersAnyDatasetByUsernameTest.php';

use ByJG\AnyDataset\Factory;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Util\Uri;

class UsersDBDatasetByUsernameTest extends UsersAnyDatasetByUsernameTest
{

    const CONNECTION_STRING='sqlite:///tmp/teste.db';

    public function __setUp($loginField)
    {
        $this->prefix = "";

        $db = Factory::getDbRelationalInstance(self::CONNECTION_STRING);
        $db->execute('create table users (
            userid integer primary key  autoincrement, 
            name varchar(45), 
            email varchar(200), 
            username varchar(20), 
            password varchar(40), 
            created datetime default (datetime(\'2017-12-04\')), 
            admin char(1));'
        );

        $db->execute('create table users_property (
            id integer primary key  autoincrement, 
            userid integer, 
            name varchar(45), 
            value varchar(45));'
        );

        $this->userDefinition = new UserDefinition('users', UserModel::class, $loginField);
        $this->propertyDefinition = new UserPropertiesDefinition();
        $this->object = new UsersDBDataset(
            self::CONNECTION_STRING,
            $this->userDefinition,
            $this->propertyDefinition
        );

        $this->object->addUser('User 1', 'user1', 'user1@gmail.com', 'pwd1');
        $this->object->addUser('User 2', 'user2', 'user2@gmail.com', 'pwd2');
        $this->object->addUser('User 3', 'user3', 'user3@gmail.com', 'pwd3');
    }

    public function setUp()
    {
        $this->__setUp(UserDefinition::LOGIN_IS_USERNAME);
    }

    public function tearDown()
    {
        $uri = new Uri(self::CONNECTION_STRING);
        unlink($uri->getPath());
        $this->object = null;
        $this->userDefinition = null;
        $this->propertyDefinition = null;
    }

    public function testAddUser()
    {
        $this->object->addUser('John Doe', 'john', 'johndoe@gmail.com', 'mypassword');

        $login = $this->__chooseValue('john', 'johndoe@gmail.com');

        $user = $this->object->getByLoginField($login);
        $this->assertEquals('4', $user->getUserid());
        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('john', $user->getUsername());
        $this->assertEquals('johndoe@gmail.com', $user->getEmail());
        $this->assertEquals('91dfd9ddb4198affc5c194cd8ce6d338fde470e2', $user->getPassword());
        $this->assertEquals('no', $user->getAdmin());
        $this->assertEquals('', $user->getCreated()); // There is no default action for it

        // Setting as Admin
        $user->setAdmin('y');
        $this->object->save($user);

        $user2 = $this->object->getByLoginField($login);
        $this->assertEquals('y', $user2->getAdmin());
    }

    public function testCreateAuthToken()
    {
        $login = $this->__chooseValue('user2', 'user2@gmail.com');
        $this->expectedToken('tokenValue', $login, 2);
    }

    public function testWithUpdateValue()
    {
        // For Update Definitions
        $this->userDefinition->defineClosureForUpdate('name', function ($value, $instance) {
            return '[' . $value . ']';
        });
        $this->userDefinition->defineClosureForUpdate('username', function ($value, $instance) {
            return ']' . $value . '[';
        });
        $this->userDefinition->defineClosureForUpdate('email', function ($value, $instance) {
            return '-' . $value . '-';
        });
        $this->userDefinition->defineClosureForUpdate('password', function ($value, $instance) {
            return "@" . $value . "@";
        });
        $this->userDefinition->markPropertyAsReadOnly('created');

        // For Select Definitions
        $this->userDefinition->defineClosureForSelect('name', function ($value, $instance) {
            return '(' . $value . ')';
        });
        $this->userDefinition->defineClosureForSelect('username', function ($value, $instance) {
            return ')' . $value . '(';
        });
        $this->userDefinition->defineClosureForSelect('email', function ($value, $instance) {
            return '#' . $value . '#';
        });
        $this->userDefinition->defineClosureForSelect('password', function ($value, $instance) {
            return '%'. $value . '%';
        });

        // Test it!
        $newObject = new UsersDBDataset(
            self::CONNECTION_STRING,
            $this->userDefinition,
            $this->propertyDefinition
        );

        $newObject->addUser('User 4', 'user4', 'user4@gmail.com', 'pwd4');

        $login = $this->__chooseValue(']user4[', '-user4@gmail.com-');

        $user = $newObject->getByLoginField($login);
        $this->assertEquals('4', $user->getUserid());
        $this->assertEquals('([User 4])', $user->getName());
        $this->assertEquals(')]user4[(', $user->getUsername());
        $this->assertEquals('#-user4@gmail.com-#', $user->getEmail());
        $this->assertEquals('%@pwd4@%', $user->getPassword());
        $this->assertEquals('2017-12-04 00:00:00', $user->getCreated());
    }

    public function testSaveAndSave()
    {
        $user = $this->object->getById(1);
        $this->object->save($user);

        $user2 = $this->object->getById(1);

        $this->assertEquals($user, $user2);
    }
}