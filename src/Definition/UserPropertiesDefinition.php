<?php

namespace ByJG\Authenticate\Definition;

class UserPropertiesDefinition
{

    protected $table;
    protected $id;
    protected $name;
    protected $value;
    protected $userid;

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
        $table = 'users_property',
        $id = 'id',
        $name = 'name',
        $value = 'value',
        $userid = 'userid'
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
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function getUserid()
    {
        return $this->userid;
    }
}
