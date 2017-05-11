<?php

namespace ByJG\Authenticate;

use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;

require_once "UsersAnyDatasetByUsernameTest.php";

class UsersAnyDatasetByEmailTest extends UsersAnyDatasetByUsernameTest
{
    public function setUp()
    {
        $this->__setUp(UserDefinition::LOGIN_IS_EMAIL);
    }
}
