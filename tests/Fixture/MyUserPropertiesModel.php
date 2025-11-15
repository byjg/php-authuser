<?php

namespace Tests\Fixture;

use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;

#[TableAttribute(tableName: 'theirproperty')]
class MyUserPropertiesModel extends UserPropertiesModel
{
    #[FieldAttribute(fieldName: 'theiruserid')]
    protected string|int|\ByJG\MicroOrm\Literal\HexUuidLiteral|null $userid = null;

    #[FieldAttribute(fieldName: 'theirid', primaryKey: true)]
    protected ?string $id = null;

    #[FieldAttribute(fieldName: 'theirname')]
    protected ?string $name = null;

    #[FieldAttribute(fieldName: 'theirvalue')]
    protected ?string $value = null;
}
