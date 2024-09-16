<?php

namespace Tests;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Db\Factory;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Exception\UserExistsException;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\UsersDBDataset;
use ByJG\MicroOrm\Exception\OrmBeforeInvalidException;
use ByJG\MicroOrm\Exception\OrmInvalidFieldsException;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use Exception;
use ReflectionException;

class MyUserModel extends UserModel
{
    protected $otherfield;

    public function __construct($name = "", $email = "", $username = "", $password = "", $admin = "no", $field = "")
    {
        parent::__construct($name, $email, $username, $password, $admin);
        $this->setOtherfield($field);
    }

    public function getOtherfield()
    {
        return $this->otherfield;
    }

    public function setOtherfield($otherfield)
    {
        $this->otherfield = $otherfield;
    }
}

class UsersDBDatasetDefinitionTest extends UsersDBDatasetByUsernameTest
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
    public function __setUp($loginField)
    {
        $this->prefix = "";

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

        $this->userDefinition = new UserDefinition(
            'mytable',
            MyUserModel::class,
            $loginField,
            [
                UserDefinition::FIELD_USERID => 'myuserid',
                UserDefinition::FIELD_NAME => 'myname',
                UserDefinition::FIELD_EMAIL => 'myemail',
                UserDefinition::FIELD_USERNAME => 'myusername',
                UserDefinition::FIELD_PASSWORD => 'mypassword',
                UserDefinition::FIELD_CREATED => 'mycreated',
                UserDefinition::FIELD_ADMIN => 'myadmin',
                'otherfield' => 'myotherfield'
            ]
        );

        $this->propertyDefinition = new UserPropertiesDefinition('theirproperty', 'theirid', 'theirname', 'theirvalue', 'theiruserid');

        $this->object = new UsersDBDataset(
            $this->db,
            $this->userDefinition,
            $this->propertyDefinition
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
    public function setUp(): void
    {
        $this->__setUp(UserDefinition::LOGIN_IS_USERNAME);
    }

    /**
     * @throws UserExistsException
     * @throws DatabaseException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function testAddUser()
    {
        $this->object->save(new MyUserModel('John Doe', 'johndoe@gmail.com', 'john', 'mypassword', 'no', 'other john'));

        $login = $this->__chooseValue('john', 'johndoe@gmail.com');

        $user = $this->object->getByLoginField($login);
        $this->assertEquals('4', $user->getUserid());
        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('john', $user->getUsername());
        $this->assertEquals('johndoe@gmail.com', $user->getEmail());
        $this->assertEquals('91dfd9ddb4198affc5c194cd8ce6d338fde470e2', $user->getPassword());
        $this->assertEquals('no', $user->getAdmin());
        /** @psalm-suppress UndefinedMethod Check UserModel::__call */
        $this->assertEquals('other john', $user->getOtherfield());
        $this->assertEquals('', $user->getCreated()); // There is no default action for it

        // Setting as Admin
        $user->setAdmin('y');
        $this->object->save($user);

        $user2 = $this->object->getByLoginField($login);
        $this->assertEquals('y', $user2->getAdmin());
    }

    /**
     * @throws Exception
     */
    public function testWithUpdateValue()
    {
        // For Update Definitions
        $this->userDefinition->defineClosureForUpdate(UserDefinition::FIELD_NAME, function ($value, $instance) {
            return '[' . $value . ']';
        });
        $this->userDefinition->defineClosureForUpdate(UserDefinition::FIELD_USERNAME, function ($value, $instance) {
            return ']' . $value . '[';
        });
        $this->userDefinition->defineClosureForUpdate(UserDefinition::FIELD_EMAIL, function ($value, $instance) {
            return '-' . $value . '-';
        });
        $this->userDefinition->defineClosureForUpdate(UserDefinition::FIELD_PASSWORD, function ($value, $instance) {
            return "@" . $value . "@";
        });
        $this->userDefinition->markPropertyAsReadOnly(UserDefinition::FIELD_CREATED);
        $this->userDefinition->defineClosureForUpdate('otherfield', function ($value, $instance) {
            return "*" . $value . "*";
        });

        // For Select Definitions
        $this->userDefinition->defineClosureForSelect(UserDefinition::FIELD_NAME, function ($value, $instance) {
            return '(' . $value . ')';
        });
        $this->userDefinition->defineClosureForSelect(UserDefinition::FIELD_USERNAME, function ($value, $instance) {
            return ')' . $value . '(';
        });
        $this->userDefinition->defineClosureForSelect(UserDefinition::FIELD_EMAIL, function ($value, $instance) {
            return '#' . $value . '#';
        });
        $this->userDefinition->defineClosureForSelect(UserDefinition::FIELD_PASSWORD, function ($value, $instance) {
            return '%' . $value . '%';
        });
        $this->userDefinition->defineClosureForSelect('otherfield', function ($value, $instance) {
            return ']' . $value . '[';
        });

        // Test it!
        $newObject = new UsersDBDataset(
            $this->db,
            $this->userDefinition,
            $this->propertyDefinition
        );

        $newObject->save(
            new MyUserModel('User 4', 'user4@gmail.com', 'user4', 'pwd4', 'no', 'other john')
        );

        $login = $this->__chooseValue(']user4[', '-user4@gmail.com-');

        $user = $newObject->getByLoginField($login);
        $this->assertEquals('4', $user->getUserid());
        $this->assertEquals('([User 4])', $user->getName());
        $this->assertEquals(')]user4[(', $user->getUsername());
        $this->assertEquals('#-user4@gmail.com-#', $user->getEmail());
        $this->assertEquals('%@pwd4@%', $user->getPassword());
        /** @psalm-suppress UndefinedMethod Check UserModel::__call */
        $this->assertEquals(']*other john*[', $user->getOtherfield());
        $this->assertEquals('2017-12-04 00:00:00', $user->getCreated());
    }
}
