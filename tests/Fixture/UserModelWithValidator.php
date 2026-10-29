<?php

namespace Tests\Fixture;

use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\Attributes\TableAttribute;

#[TableAttribute(tableName: 'users', validator: BlockedNameValidator::class)]
class UserModelWithValidator extends UserModel
{
}
