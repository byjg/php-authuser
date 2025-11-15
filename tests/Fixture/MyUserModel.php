<?php

namespace Tests\Fixture;

use ByJG\Authenticate\MapperFunctions\PasswordSha1Mapper;
use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\MicroOrm\MapperFunctions\ReadOnlyMapper;

#[TableAttribute(tableName: 'mytable')]
class MyUserModel extends UserModel
{
    #[FieldAttribute(fieldName: 'myuserid', primaryKey: true)]
    protected string|int|HexUuidLiteral|null $userid = null;

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

    #[FieldAttribute(fieldName: 'myrole')]
    protected ?string $role = null;

    #[FieldAttribute(fieldName: 'myotherfield')]
    protected $otherfield;

    public function __construct($name = "", $email = "", $username = "", $password = "", $role = "", $field = "")
    {
        parent::__construct($name, $email, $username, $password, $role);
        $this->setOtherfield($field);
    }

    public function getOtherfield()
    {
        return $this->otherfield;
    }

    public function setOtherfield($otherfield): void
    {
        $this->otherfield = $otherfield;
    }
}
