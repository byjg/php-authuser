<?php

namespace ByJG\Authenticate\Definition;

use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\Mapper;
use ByJG\Serializer\SerializerObject;

/**
 * Structure to represent the users
 */
class UserDefinition
{
    protected $__table = 'users';
    protected $__closures = ["select" => [], "update" => [] ];
    protected $__loginField;
    protected $__model;
    protected $__properties = [];
    protected $__generateKey = null;

    const FIELD_USERID = 'userid';
    const FIELD_NAME = 'name';
    const FIELD_EMAIL = 'email';
    const FIELD_USERNAME = 'username';
    const FIELD_PASSWORD = 'password';
    const FIELD_CREATED = 'created';
    const FIELD_ADMIN = 'admin';

    const UPDATE="update";
    const SELECT="select";


    const LOGIN_IS_EMAIL="email";
    const LOGIN_IS_USERNAME="username";

    /**
     * Define the name of fields and table to store and retrieve info from database
     *
     * @param string $table
     * @param string $model
     * @param string $loginField
     * @param array $fieldDef
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function __construct(
        $table = 'users',
        $model = UserModel::class,
        $loginField = self::LOGIN_IS_USERNAME,
        $fieldDef = []
    ) {
        $this->__table = $table;
        $this->__model = $model;

        // Set Default User Definition
        $modelInstance = $this->modelInstance();
        $modelProperties = SerializerObject::instance($modelInstance)->serialize();
        foreach (array_keys($modelProperties) as $property) {
            $this->__properties[$property] = $property;
        }

        // Set custom Properties
        foreach ($fieldDef as $property => $value) {
            $this->checkProperty($property);
            $this->__properties[$property] = $value;
        }

        $this->defineClosureForUpdate(UserDefinition::FIELD_PASSWORD, function ($value) {
            // Already have a SHA1 password
            if (strlen($value) === 40) {
                return $value;
            }

            // Leave null
            if (empty($value)) {
                return null;
            }

            // Return the hash password
            return strtolower(sha1($value));
        });

        if ($loginField !== self::LOGIN_IS_USERNAME && $loginField !== self::LOGIN_IS_EMAIL) {
            throw new \InvalidArgumentException('Login field is invalid. ');
        }
        $this->__loginField = $loginField;

        $this->beforeInsert = function ($instance) {
            return $instance;
        };
        $this->beforeUpdate = function ($instance) {
            return $instance;
        };
    }

    /**
     * @return string
     */
    public function table()
    {
        return $this->__table;
    }


    public function __get($name)
    {
        $this->checkProperty($name);
        return $this->__properties[$name];
    }

    public function __call($name, $arguments)
    {
        if (strpos($name, 'get') === 0) {
            $name = strtolower(substr($name, 3));
            return $this->{$name};
        }
        throw new \InvalidArgumentException("Method '$name' does not exists'");
    }

    public function toArray()
    {
        return $this->__properties;
    }

    /**
     * @return string
     */
    public function loginField()
    {
        return $this->{$this->__loginField};
    }

    private function checkProperty($property)
    {
        if (!isset($this->__properties[$property])) {
            throw new \InvalidArgumentException("Property '$property' does not exists'");
        }
    }

    /**
     * @param $event
     * @param $property
     * @param \Closure $closure
     */
    private function updateClosureDef($event, $property, $closure)
    {
        $this->checkProperty($property);
        $this->__closures[$event][$property] = $closure;
    }

    private function getClosureDef($event, $property)
    {
        $this->checkProperty($property);

        if (!$this->existsClosure($event, $property)) {
            return Mapper::defaultClosure();
        }

        return $this->__closures[$event][$property];
    }

    public function existsClosure($event, $property)
    {
        // Event not set
        if (!isset($this->__closures[$event])) {
            return false;
        }

        // Event is set but there is no property
        if (!array_key_exists($property, $this->__closures[$event])) {
            return false;
        }

        return true;
    }

    public function markPropertyAsReadOnly($property)
    {
        $this->updateClosureDef(self::UPDATE, $property, Mapper::doNotUpdateClosure());
    }

    public function defineClosureForUpdate($property, \Closure $closure)
    {
        $this->updateClosureDef(self::UPDATE, $property, $closure);
    }

    public function defineClosureForSelect($property, \Closure $closure)
    {
        $this->updateClosureDef(self::SELECT, $property, $closure);
    }

    public function getClosureForUpdate($property)
    {
        return $this->getClosureDef(self::UPDATE, $property);
    }

    public function defineGenerateKeyClosure(\Closure $closure)
    {
        $this->__generateKey = $closure;
    }

    public function getGenerateKeyClosure()
    {
        return $this->__generateKey;
    }

    /**
     * @param $property
     * @return \Closure
     */
    public function getClosureForSelect($property)
    {
        return $this->getClosureDef(self::SELECT, $property);
    }

    public function model()
    {
        return $this->__model;
    }

    public function modelInstance()
    {
        $model = $this->__model;
        return new $model();
    }

    protected $beforeInsert;

    /**
     * @return mixed
     */
    public function getBeforeInsert()
    {
        return $this->beforeInsert;
    }

    /**
     * @param mixed $beforeInsert
     */
    public function setBeforeInsert($beforeInsert)
    {
        $this->beforeInsert = $beforeInsert;
    }

    protected $beforeUpdate;

    /**
     * @return mixed
     */
    public function getBeforeUpdate()
    {
        return $this->beforeUpdate;
    }

    /**
     * @param mixed $beforeUpdate
     */
    public function setBeforeUpdate($beforeUpdate)
    {
        $this->beforeUpdate = $beforeUpdate;
    }
}
