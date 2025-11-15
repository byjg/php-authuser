<?php

namespace Tests;

use ByJG\AnyDataset\Db\Factory;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\UsersDBDataset;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;

class UsersDBDataset2ByUserNameTestUsersBase extends TestUsersBase
{
    const CONNECTION_STRING='sqlite:///tmp/teste.db';

    protected $db;

    /**
     * @throws \ReflectionException
     * @throws OrmModelInvalidException
     */
    #[\Override]
    public function __setUp($loginField)
    {
        $this->prefix = "";

        $this->db = Factory::getDbRelationalInstance(self::CONNECTION_STRING);
        $this->db->execute('create table mytable (
            myuserid integer primary key  autoincrement, 
            myname varchar(45), 
            myemail varchar(200), 
            myusername varchar(20), 
            mypassword varchar(40), 
            mycreated datetime default (datetime(\'2017-12-04\')),
            myadmin char(1));'
        );

        $this->db->execute('create table theirproperty (
            theirid integer primary key  autoincrement, 
            theiruserid integer, 
            theirname varchar(45), 
            theirvalue varchar(45));'
        );

        $this->userDefinition = new UserDefinition(
            'mytable',
            UserModel::class,
            $loginField,
            [
                UserDefinition::FIELD_USERID => 'myuserid',
                UserDefinition::FIELD_NAME => 'myname',
                UserDefinition::FIELD_EMAIL => 'myemail',
                UserDefinition::FIELD_USERNAME => 'myusername',
                UserDefinition::FIELD_PASSWORD => 'mypassword',
                UserDefinition::FIELD_CREATED => 'mycreated',
                UserDefinition::FIELD_ADMIN => 'myadmin'
            ]
        );
        $this->userDefinition->markPropertyAsReadOnly(UserDefinition::FIELD_CREATED);

        $this->propertyDefinition = new UserPropertiesDefinition('theirproperty', 'theirid', 'theirname', 'theirvalue', 'theiruserid');

        $this->object = new UsersDBDataset(
            $this->db,
            $this->userDefinition,
            $this->propertyDefinition
        );

        $this->object->addUser('User 1', 'user1', 'user1@gmail.com', 'pwd1');
        $this->object->addUser('User 2', 'user2', 'user2@gmail.com', 'pwd2');
        $this->object->addUser('User 3', 'user3', 'user3@gmail.com', 'pwd3');
    }

    #[\Override]
    public function setUp(): void
    {
        $this->__setUp(UserDefinition::LOGIN_IS_USERNAME);
    }

    #[\Override]
    public function tearDown(): void
    {
        $uri = new \ByJG\Util\Uri(self::CONNECTION_STRING);
        unlink($uri->getPath());
        $this->object = null;
        $this->userDefinition = null;
        $this->propertyDefinition = null;
    }

    /**
     * @return void
     */
    #[\Override]
    public function testAddUser()
    {
        $this->object->addUser('John Doe', 'john', 'johndoe@gmail.com', 'mypassword');

        $login = $this->__chooseValue('john', 'johndoe@gmail.com');

        $user = $this->object->get($login, $this->object->getUserDefinition()->loginField());
        $this->assertEquals('4', $user->getUserid());
        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('john', $user->getUsername());
        $this->assertEquals('johndoe@gmail.com', $user->getEmail());
        $this->assertEquals('91dfd9ddb4198affc5c194cd8ce6d338fde470e2', $user->getPassword());
        $this->assertEquals('no', $user->getAdmin());
        $this->assertEquals('2017-12-04 00:00:00', $user->getCreated());

        // Setting as Admin
        $user->setAdmin('y');
        $this->object->save($user);

        $user2 = $this->object->get($login, $this->object->getUserDefinition()->loginField());
        $this->assertEquals('y', $user2->getAdmin());
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
    #[\Override]
    public function testSaveAndSave()
    {
        $user = $this->object->get("1");
        $this->object->save($user);

        $user2 = $this->object->get("1");

        $this->assertEquals($user, $user2);
    }
}
