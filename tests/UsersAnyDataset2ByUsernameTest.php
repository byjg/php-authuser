<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Core\AnyDataset;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Model\UserModel;

require_once 'UsersAnyDatasetByUsernameTest.php';

class UsersAnyDataset2ByUsernameTest extends UsersAnyDatasetByUsernameTest
{

    public function __setUp($loginField)
    {
        $this->prefix = "user";

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

        $this->propertyDefinition = new UserPropertiesDefinition(
            'theirproperty',
            'theirid',
            'theirname',
            'theirvalue',
            'theiruserid'
        );

        $anydataset = new AnyDataset('php://memory');
        $this->object = new UsersAnyDataset(
            $anydataset,
            $this->userDefinition,
            $this->propertyDefinition
        );
        $this->object->addUser('User 1', 'user1', 'user1@gmail.com', 'pwd1');
        $this->object->addUser('User 2', 'user2', 'user2@gmail.com', 'pwd2');
        $this->object->addUser('User 3', 'user3', 'user3@gmail.com', 'pwd3');
    }

    public function setUp(): void
    {
        $this->__setUp(UserDefinition::LOGIN_IS_USERNAME);
    }
}
