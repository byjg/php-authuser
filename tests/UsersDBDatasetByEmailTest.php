<?php

namespace ByJG\Authenticate;

require_once 'UsersDBDatasetByUsernameTest.php';

use ByJG\Authenticate\Definition\UserDefinition;

class UsersDBDatasetByEmailTest extends UsersAnyDatasetByUsernameTest
{
    public function setUp()
    {
        $this->__setUp(UserDefinition::LOGIN_IS_EMAIL);
    }
}
