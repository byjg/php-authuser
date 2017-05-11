<?php

namespace ByJG\Authenticate;

use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Definition\UserDefinition;

require_once 'UsersAnyDatasetByUsernameTest.php';

class UsersAnyDataset2ByUsernameTest extends UsersAnyDatasetByUsernameTest
{

    public function __setUp($loginField)
    {
        $this->prefix = "user";

        $this->userDefinition = new UserDefinition(
            'mytable',
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

        $this->propertyDefinition = new UserPropertiesDefinition(
            'theirproperty',
            'theirid',
            'theirname',
            'theirvalue',
            'theiruserid'
        );

        $this->object = new UsersAnyDataset(
            'php://memory',
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
