<?php

namespace ByJG\Authenticate\Model;

use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\MicroOrm\Literal\Literal;

#[TableAttribute(tableName: 'users_property')]
class UserPropertiesModel
{
    #[FieldAttribute]
    protected string|int|Literal|null $userid = null;

    #[FieldAttribute(primaryKey: true)]
    protected ?string $id = null;

    #[FieldAttribute]
    protected ?string $name = null;

    #[FieldAttribute]
    protected ?string $value = null;

    /**
     * UserPropertiesModel constructor.
     *
     * @param string|null $name
     * @param string|null $value
     */
    public function __construct(?string $name = null, ?string $value = null)
    {
        $this->name = $name;
        $this->value = $value;
    }

    /**
     * @return string|int|Literal|null
     */
    public function getUserid(): string|int|Literal|null
    {
        return $this->userid;
    }

    /**
     * @param string|int|Literal|null $userid
     */
    public function setUserid(string|int|Literal|null $userid): void
    {
        $this->userid = $userid;
    }

    /**
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @param string|null $id
     */
    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     */
    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string|null
     */
    public function getValue(): ?string
    {
        return $this->value;
    }

    /**
     * @param string|null $value
     */
    public function setValue(?string $value): void
    {
        $this->value = $value;
    }
}
