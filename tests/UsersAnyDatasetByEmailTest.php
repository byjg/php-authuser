<?php

namespace ByJG\Authenticate;

use ByJG\Authenticate\Definition\UserDefinition;

require_once "UsersAnyDatasetByUsernameTest.php";

class UsersAnyDatasetByEmailTest extends UsersAnyDatasetByUsernameTest
{
    public function setUp(): void
    {
        $this->__setUp(UserDefinition::LOGIN_IS_EMAIL);
    }
}
