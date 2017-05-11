<?php

namespace ByJG\Authenticate\Definition;

use ByJG\MicroOrm\Mapper;

/**
 * Structure to represent the users
 */
class UserDefinition
{

    protected $table;
    protected $userid;
    protected $name;
    protected $email;
    protected $username;
    protected $password;
    protected $created;
    protected $admin;

    protected $closures = [ "select" => [], "update" => [] ];

    /**
     * Define the name of fields and table to store and retrieve info from database
     *
     * @param string $table
     * @param string $userid
     * @param string $name
     * @param string $email
     * @param string $username
     * @param string $password
     * @param string $created
     * @param string $admin
     */
    public function __construct(
    $table = 'users', $userid = 'userid', $name = 'name', $email = 'email', $username = 'username', $password = 'password',
        $created = 'created', $admin = 'admin'
    )
    {
        $this->table = $table;
        $this->userid = $userid;
        $this->name = $name;
        $this->email = $email;
        $this->username = $username;
        $this->password = $password;
        $this->created = $created;
        $this->admin = $admin;

        $this->defineClosureForUpdate('password', function ($value, $instance) {
            return strtoupper(sha1($value));
        });
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
    public function getUserid()
    {
        return $this->userid;
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
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @return string
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * @return string
     */
    public function getAdmin()
    {
        return $this->admin;
    }

    private function checkProperty($property)
    {
        if (!isset($this->{$property})) {
            throw new \InvalidArgumentException('Invalid property');
        }
    }

    private function updateClosureDef($event, $property, \Closure $closure)
    {
        $this->checkProperty($property);
        $this->closures[$event][$property] = $closure;
    }

    private function getClosureDef($event, $property)
    {
        $this->checkProperty($property);

        if (!isset($this->closures[$event][$property])) {
            return Mapper::defaultClosure();
        }

        return $this->closures[$event][$property];
    }

    public function defineClosureForUpdate($property, \Closure $closure)
    {
        $this->updateClosureDef('update', $property, $closure);
    }

    public function defineClosureForSelect($property, \Closure $closure)
    {
        $this->updateClosureDef('select', $property, $closure);
    }

    public function getClosureForUpdate($property)
    {
        return $this->getClosureDef('update', $property);
    }

    /**
     * @param $property
     * @return \Closure
     */
    public function getClosureForSelect($property)
    {
        return $this->getClosureDef('select', $property);
    }

}
