<?php

namespace Tests\Fixture;

use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\MicroOrm\Literal\Literal;
use ByJG\MicroOrm\MapperFunctions\NowUtcMapper;
use ByJG\MicroOrm\MapperFunctions\ReadOnlyMapper;

#[TableAttribute(tableName: 'users')]
class UserModelMd5 extends UserModel
{
    #[FieldAttribute(primaryKey: true)]
    protected string|int|Literal|null $userid = null;

    #[FieldAttribute]
    protected ?string $name = null;

    #[FieldAttribute]
    protected ?string $email = null;

    #[FieldAttribute]
    protected ?string $username = null;

    #[FieldAttribute(updateFunction: PasswordMd5Mapper::class)]
    protected ?string $password = null;

    #[FieldAttribute(fieldName: 'created_at', updateFunction: ReadOnlyMapper::class, insertFunction: NowUtcMapper::class)]
    protected ?string $createdAt = null;

    #[FieldAttribute(fieldName: 'updated_at', updateFunction: NowUtcMapper::class)]
    protected ?string $updatedAt = null;

    #[FieldAttribute(fieldName: 'deleted_at', syncWithDb: false)]
    protected ?string $deletedAt = null;

    #[FieldAttribute]
    protected ?string $role = null;
}
