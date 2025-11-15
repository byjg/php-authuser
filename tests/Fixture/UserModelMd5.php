<?php

namespace Tests\Fixture;

use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\MicroOrm\MapperFunctions\ReadOnlyMapper;

#[TableAttribute(tableName: 'users')]
class UserModelMd5 extends UserModel
{
    #[FieldAttribute(primaryKey: true)]
    protected string|int|\ByJG\MicroOrm\Literal\HexUuidLiteral|null $userid = null;

    #[FieldAttribute]
    protected ?string $name = null;

    #[FieldAttribute]
    protected ?string $email = null;

    #[FieldAttribute]
    protected ?string $username = null;

    #[FieldAttribute(updateFunction: PasswordMd5Mapper::class)]
    protected ?string $password = null;

    #[FieldAttribute(updateFunction: ReadOnlyMapper::class)]
    protected ?string $created = null;

    #[FieldAttribute]
    protected ?string $admin = null;
}
