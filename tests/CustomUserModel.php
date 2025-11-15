<?php

namespace Tests;

use ByJG\Authenticate\MapperFunctions\PasswordSha1Mapper;
use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\MicroOrm\MapperFunctions\ReadOnlyMapper;

#[TableAttribute(tableName: 'mytable')]
class CustomUserModel extends UserModel
{
    #[FieldAttribute(fieldName: 'myuserid', primaryKey: true)]
    protected string|int|\ByJG\MicroOrm\Literal\HexUuidLiteral|null $userid = null;

    #[FieldAttribute(fieldName: 'myname')]
    protected ?string $name = null;

    #[FieldAttribute(fieldName: 'myemail')]
    protected ?string $email = null;

    #[FieldAttribute(fieldName: 'myusername')]
    protected ?string $username = null;

    #[FieldAttribute(fieldName: 'mypassword', updateFunction: PasswordSha1Mapper::class)]
    protected ?string $password = null;

    #[FieldAttribute(fieldName: 'mycreated', updateFunction: ReadOnlyMapper::class)]
    protected ?string $created = null;

    #[FieldAttribute(fieldName: 'myadmin')]
    protected ?string $admin = null;
}
