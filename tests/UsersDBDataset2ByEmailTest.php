<?php

namespace Tests;

use ByJG\Authenticate\Service\UsersService;

class UsersDBDataset2ByEmailTest extends UsersDBDataset2ByUserNameTestUsersBase
{
    #[\Override]
    public function setUp(): void
    {
        $this->__setUp(UsersService::LOGIN_IS_EMAIL);
    }
}
