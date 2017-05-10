<?php

namespace ByJG\Authenticate;

require_once 'UsersDBDatasetTest.php';

use ByJG\AnyDataset\Factory;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Definition\UserDefinition;

/**
 * Created by PhpStorm.
 * User: jg
 * Date: 24/04/16
 * Time: 20:21
 */
class UsersDBDataset2Test extends UsersDBDatasetTest
{
    /**
     * @var UsersDBDataset
     */
    protected $object;

    public function setUp()
    {
        $this->prefix = "";

        $db = Factory::getDbRelationalInstance(self::CONNECTION_STRING);
        $db->execute('create table mytable (
            myuserid integer primary key  autoincrement, 
            myname varchar(45), 
            myemail varchar(200), 
            myusername varchar(20), 
            mypassword varchar(40), 
            mycreated datetime, 
            myadmin char(1));'
        );

        $db->execute('create table theirproperty (
            theirid integer primary key  autoincrement, 
            theiruserid integer, 
            theirname varchar(45), 
            theirvalue varchar(45));'
        );

        $this->userDefinition = new UserDefinition('mytable', 'myuserid', 'myname', 'myemail', 'myusername', 'mypassword', 'mycreated', 'myadmin');
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
}
