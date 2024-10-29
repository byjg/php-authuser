<?php

namespace Tests;

use ByJG\Authenticate\Definition\UserDefinition;

class UsersDBDatasetByEmailTest extends UsersAnyDatasetByUsernameTest
{
    public function setUp(): void
    {
        $this->__setUp(UserDefinition::LOGIN_IS_EMAIL);
    }
}
