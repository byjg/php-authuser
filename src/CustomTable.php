<?php

namespace ByJG\Authenticate;

class CustomTable
{
	public $table;
	public $id;
	public $name;
	public $value;

    /**
     * Define the name of fields and table to store and retrieve info from database
	 * Table "CUSTOM" must have [$this->_UserTable->Id = "userid"].
     *
     * @param string $table
     * @param string $id
     * @param string $name
     * @param string $value
     */
    public function __construct($table = 'users_property', $id = 'customid', $name = 'name', $value = 'value')
    {
        $this->table = $table;
        $this->id = $id;
        $this->name = $name;
        $this->value = $value;
    }
}
