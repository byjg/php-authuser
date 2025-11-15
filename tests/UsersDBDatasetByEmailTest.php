<?php

namespace Tests;

use ByJG\Authenticate\Service\UsersService;

class UsersDBDatasetByEmailTest extends UsersDBDatasetByUsernameTestUsersBase
{
    #[\Override]
    public function setUp(): void
    {
        $this->__setUp(UsersService::LOGIN_IS_EMAIL);
    }
}
