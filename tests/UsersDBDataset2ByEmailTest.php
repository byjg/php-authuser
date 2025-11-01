<?php

namespace Tests;

use ByJG\Authenticate\Definition\UserDefinition;

class UsersDBDataset2ByEmailTest extends UsersDBDatasetByUsernameTest
{
    #[\Override]
    public function setUp(): void
    {
        $this->__setUp(UserDefinition::LOGIN_IS_EMAIL);
    }
}
