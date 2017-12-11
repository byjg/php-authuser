<?php

namespace ByJG\Authenticate;

require_once 'UsersDBDatasetByUsernameTest.php';

use ByJG\AnyDataset\Factory;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Util\Uri;

class UsersDBDatasetByEmailTest extends UsersAnyDatasetByUsernameTest
{
    public function setUp()
    {
        $this->__setUp(UserDefinition::LOGIN_IS_EMAIL);
    }
}
