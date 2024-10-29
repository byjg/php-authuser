<?php

namespace Tests;

use ByJG\Authenticate\Definition\UserDefinition;

class UsersAnyDataset2EmailTest extends UsersAnyDatasetByUsernameTest
{
    public function setUp(): void
    {
        $this->__setUp(UserDefinition::LOGIN_IS_EMAIL);
    }
}
