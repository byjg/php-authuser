<?php

namespace ByJG\Authenticate;

use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Definition\UserDefinition;

require_once 'UsersAnyDataset2ByUsernameTest.php';

class UsersAnyDataset2EmailTest extends UsersAnyDatasetByUsernameTest
{
    public function setUp()
    {
        $this->__setUp(UserDefinition::LOGIN_IS_EMAIL);
    }
}
