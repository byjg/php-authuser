<?php

namespace Tests;

use ByJG\Authenticate\MapperFunctions\PasswordSha1Mapper;
use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\MicroOrm\MapperFunctions\NowUtcMapper;
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

    #[FieldAttribute(fieldName: 'mycreated_at', updateFunction: ReadOnlyMapper::class, insertFunction: NowUtcMapper::class)]
    protected ?string $createdAt = null;

    #[FieldAttribute(fieldName: 'myupdated_at', updateFunction: NowUtcMapper::class)]
    protected ?string $updatedAt = null;

    #[FieldAttribute(fieldName: 'mydeleted_at', syncWithDb: false)]
    protected ?string $deletedAt = null;

    #[FieldAttribute(fieldName: 'myrole')]
    protected ?string $role = null;
}
