<?php

namespace ByJG\Authenticate;

require_once 'UsersDBDataset2ByUserNameTest.php';

use ByJG\Authenticate\Definition\UserDefinition;

class UsersDBDataset2ByEmailTest extends UsersDBDatasetByUsernameTest
{
    public function setUp(): void
    {
        $this->__setUp(UserDefinition::LOGIN_IS_EMAIL);
    }
}
