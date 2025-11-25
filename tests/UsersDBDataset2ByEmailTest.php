<?php

namespace Tests;

use ByJG\Authenticate\Enum\LoginField;

class UsersDBDataset2ByEmailTest extends UsersDBDataset2ByUserNameTestUsersBase
{
    #[\Override]
    public function setUp(): void
    {
        $this->__setUp(LoginField::Email);
    }
}
