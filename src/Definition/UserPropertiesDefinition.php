<?php

namespace ByJG\Authenticate\Definition;

class UserPropertiesDefinition
{
    protected string $table;
    protected string $id;
    protected string $name;
    protected string $value;
    protected string $userid;

    /**
     * Define the name of fields and table to store and retrieve info from database
     * Table "CUSTOM" must have [$this->_UserTable->Id = "userid"].
     *
     * @param string $table
     * @param string $id
     * @param string $name
     * @param string $value
     * @param string $userid
     */
    public function __construct(
        string $table = 'users_property',
        string $id = 'id',
        string $name = 'name',
        string $value = 'value',
        string $userid = UserDefinition::FIELD_USERID
    ) {
        $this->table = $table;
        $this->id = $id;
        $this->name = $name;
        $this->value = $value;
        $this->userid = $userid;
    }

    /**
     * @return string
     */
    public function table(): string
    {
        return $this->table;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function getUserid(): string
    {
        return $this->userid;
    }
}
