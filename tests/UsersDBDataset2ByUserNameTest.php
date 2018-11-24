<?php

namespace ByJG\Authenticate;

require_once 'UsersDBDatasetByUsernameTest.php';

use ByJG\AnyDataset\Db\Factory;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Model\UserModel;

class UsersDBDataset2ByUserNameTest extends UsersDBDatasetByUsernameTest
{
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
            mycreated datetime default (datetime(\'2017-12-04\')),
            myadmin char(1));'
        );

        $db->execute('create table theirproperty (
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
                'userid' => 'myuserid',
                'name' => 'myname',
                'email' => 'myemail',
                'username' => 'myusername',
                'password' => 'mypassword',
                'created' => 'mycreated',
                'admin' => 'myadmin'
            ]
        );

        $this->propertyDefinition = new UserPropertiesDefinition('theirproperty', 'theirid', 'theirname', 'theirvalue', 'theiruserid');

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
}
