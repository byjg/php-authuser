<?php

namespace ByJG\Authenticate;

use ByJG\Authenticate\Definition\UserDefinition;

require_once 'UsersAnyDataset2ByUsernameTest.php';

class UsersAnyDataset2EmailTest extends UsersAnyDatasetByUsernameTest
{
    public function setUp(): void
    {
        $this->__setUp(UserDefinition::LOGIN_IS_EMAIL);
    }
}
