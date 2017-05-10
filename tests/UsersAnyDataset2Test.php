<?php

namespace ByJG\Authenticate;

use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Definition\UserDefinition;

require_once 'UsersAnyDatasetTest.php';

class UsersAnyDataset2Test extends UsersAnyDatasetTest
{

    public function setUp()
    {
        $this->prefix = "user";

        $this->object = new UsersAnyDataset(
            'php://memory',
            new UserDefinition('mytable', 'myuserid', 'myname', 'myemail', 'myusername', 'mypassword', 'mycreated', 'myadmin'),
            new UserPropertiesDefinition('theirproperty', 'theirid', 'theirname', 'theirvalue', 'theiruserid')
        );
        $this->object->addUser('User 1', 'user1', 'user1@gmail.com', 'pwd1');
        $this->object->addUser('User 2', 'user2', 'user2@gmail.com', 'pwd2');
        $this->object->addUser('User 3', 'user3', 'user3@gmail.com', 'pwd3');
    }
}
