<?php

namespace Tests;

use ByJG\Authenticate\Definition\UserDefinition;

class UsersDBDataset2ByEmailTest extends UsersDBDataset2ByUserNameTestUsersBase
{
    #[\Override]
    public function setUp(): void
    {
        $this->__setUp(UserDefinition::LOGIN_IS_EMAIL);
    }
}
