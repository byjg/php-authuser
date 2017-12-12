<?php

namespace ByJG\Authenticate;

require_once 'UsersDBDatasetByUsernameTest.php';

use ByJG\AnyDataset\Factory;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Model\UserModel;

class MyUserDefinition extends UserDefinition
{
    protected $otherfield = 'otherfield';

    public function __construct(
        $table = 'users',
        $loginField = self::LOGIN_IS_USERNAME,
        array $fieldDef = []
    ) {
        parent::__construct($table, $loginField, $fieldDef);
        $this->model = MyUserModel::class;
    }

    public function getOtherfield()
    {
        return $this->otherfield;
    }
}

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
    /**
     * @param $loginField
     * @throws \ByJG\AnyDataset\Exception\NotFoundException
     * @throws \ByJG\AnyDataset\Exception\NotImplementedException
     * @throws \ByJG\Authenticate\UserExistsException
     * @throws \Exception
     */
    public function __setUp($loginField)
    {
        $this->prefix = "";

        $db = Factory::getDbRelationalInstance(self::CONNECTION_STRING);
        $db->execute('create table mytable (
            myuserid integer primary key  autoincrement, 
            myname varchar(45), 
            myemail varchar(200), 
            myusername varchar(20), 
            mypassword varchar(40), 
            myotherfield varchar(40), 
            mycreated datetime default (datetime(\'2017-12-04\')),
            myadmin char(1));'
        );

        $db->execute('create table theirproperty (
            theirid integer primary key  autoincrement, 
            theiruserid integer, 
            theirname varchar(45), 
            theirvalue varchar(45));'
        );

        $this->userDefinition = new MyUserDefinition(
            'mytable',
            $loginField,
            [
                'userid' => 'myuserid',
                'name' => 'myname',
                'email' => 'myemail',
                'username' => 'myusername',
                'password' => 'mypassword',
                'created' => 'mycreated',
                'admin' => 'myadmin',
                'otherfield' => 'myotherfield'
            ]
        );

        $this->propertyDefinition = new UserPropertiesDefinition('theirproperty', 'theirid', 'theirname', 'theirvalue', 'theiruserid');

        $this->object = new UsersDBDataset(
            self::CONNECTION_STRING,
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
     * @throws \ByJG\AnyDataset\Exception\NotFoundException
     * @throws \ByJG\AnyDataset\Exception\NotImplementedException
     * @throws \ByJG\Authenticate\UserExistsException
     * @throws \Exception
     */
    public function setUp()
    {
        $this->__setUp(UserDefinition::LOGIN_IS_USERNAME);
    }

    /**
     * @throws \ByJG\AnyDataset\Exception\DatabaseException
     * @throws \ByJG\Authenticate\Exception\UserExistsException
     * @throws \ByJG\Util\Exception\XmlUtilException
     * @throws \Exception
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
        $this->assertEquals('91DFD9DDB4198AFFC5C194CD8CE6D338FDE470E2', $user->getPassword());
        $this->assertEquals('no', $user->getAdmin());
        $this->assertEquals('other john', $user->getOtherfield());
        $this->assertEquals('', $user->getCreated()); // There is no default action for it

        // Setting as Admin
        $user->setAdmin('y');
        $this->object->save($user);

        $user2 = $this->object->getByLoginField($login);
        $this->assertEquals('y', $user2->getAdmin());
    }

    /**
     * @throws \Exception
     */
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
        $this->userDefinition->defineClosureForUpdate('otherfield', function ($value, $instance) {
            return "*" . $value . "*";
        });

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
        $this->userDefinition->defineClosureForSelect('otherfield', function ($value, $instance) {
            return ']'. $value . '[';
        });

        // Test it!
        $newObject = new UsersDBDataset(
            self::CONNECTION_STRING,
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
        $this->assertEquals(']*other john*[', $user->getOtherfield());
        $this->assertEquals('2017-12-04 00:00:00', $user->getCreated());
    }
}
