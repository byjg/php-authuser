<?php

namespace ByJG\Authenticate\Definition;

/**
 * Structure to represent the users
 */
class UserTable
{

    public $table;
    public $id;
    public $name;
    public $email;
    public $username;
    public $password;
    public $created;
    public $admin;

    /**
     * Define the name of fields and table to store and retrieve info from database
     *
     * @param string $table
     * @param string $id
     * @param string $name
     * @param string $email
     * @param string $username
     * @param string $password
     * @param string $created
     * @param string $admin
     */
    public function __construct(
    $table = 'users', $id = 'userid', $name = 'name', $email = 'email', $username = 'username', $password = 'password',
        $created = 'created', $admin = 'admin'
    )
    {
        $this->table = $table;
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->username = $username;
        $this->password = $password;
        $this->created = $created;
        $this->admin = $admin;
    }
}
