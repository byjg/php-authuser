<?php

namespace Tests;

use ByJG\Authenticate\Enum\LoginField;

class UsersDBDatasetByEmailTest extends UsersDBDatasetByUsernameTestUsersBase
{
    #[\Override]
    public function setUp(): void
    {
        $this->__setUp(LoginField::Email);
    }
}
