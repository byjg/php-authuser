<?php

namespace ByJG\Authenticate\Definition;

use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\MapperClosure;
use ByJG\Serializer\Serialize;
use Closure;
use InvalidArgumentException;

/**
 * Structure to represent the users
 */
class UserDefinition
{
    protected string $__table = 'users';
    protected array $__closures = ["select" => [], "update" => [] ];
    protected string $__loginField;
    protected string $__model;
    protected array $__properties = [];
    protected Closure|null $__generateKey = null;

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
     */
    public function __construct(
        string $table = 'users',
        string $model = UserModel::class,
        string $loginField = self::LOGIN_IS_USERNAME,
        array $fieldDef = []
    ) {
        $this->__table = $table;
        $this->__model = $model;

        // Set Default User Definition
        $modelInstance = $this->modelInstance();
        $modelProperties = Serialize::from($modelInstance)->toArray();
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
            throw new InvalidArgumentException('Login field is invalid. ');
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
    public function table(): string
    {
        return $this->__table;
    }


    public function __get(string $name)
    {
        $this->checkProperty($name);
        return $this->__properties[$name];
    }

    public function __call(string $name, array $arguments)
    {
        if (str_starts_with($name, 'get')) {
            $name = strtolower(substr($name, 3));
            return $this->{$name};
        }
        throw new InvalidArgumentException("Method '$name' does not exists'");
    }

    public function toArray(): array
    {
        return $this->__properties;
    }

    /**
     * @return string
     */
    public function loginField(): string
    {
        return $this->{$this->__loginField};
    }

    private function checkProperty(string $property): void
    {
        if (!isset($this->__properties[$property])) {
            throw new InvalidArgumentException("Property '$property' does not exists'");
        }
    }

    /**
     * @param string $event
     * @param string $property
     * @param Closure $closure
     */
    private function updateClosureDef(string $event, string $property, Closure $closure): void
    {
        $this->checkProperty($property);
        $this->__closures[$event][$property] = $closure;
    }

    private function getClosureDef(string $event, string $property): Closure
    {
        $this->checkProperty($property);

        if (!$this->existsClosure($event, $property)) {
            return MapperClosure::standard();
        }

        return $this->__closures[$event][$property];
    }

    public function existsClosure(string $event, string $property): bool
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

    public function markPropertyAsReadOnly(string $property): void
    {
        $this->updateClosureDef(self::UPDATE, $property, MapperClosure::readOnly());
    }

    public function defineClosureForUpdate(string $property, Closure $closure): void
    {
        $this->updateClosureDef(self::UPDATE, $property, $closure);
    }

    public function defineClosureForSelect(string $property, Closure $closure): void
    {
        $this->updateClosureDef(self::SELECT, $property, $closure);
    }

    public function getClosureForUpdate(string $property): Closure
    {
        return $this->getClosureDef(self::UPDATE, $property);
    }

    public function defineGenerateKeyClosure(Closure $closure): void
    {
        $this->__generateKey = $closure;
    }

    public function getGenerateKeyClosure(): ?Closure
    {
        return $this->__generateKey;
    }

    /**
     * @param $property
     * @return Closure
     */
    public function getClosureForSelect($property): Closure
    {
        return $this->getClosureDef(self::SELECT, $property);
    }

    public function model(): string
    {
        return $this->__model;
    }

    public function modelInstance(): UserModel
    {
        $model = $this->__model;
        return new $model();
    }

    protected Closure $beforeInsert;

    /**
     * @return Closure
     */
    public function getBeforeInsert(): Closure
    {
        return $this->beforeInsert;
    }

    /**
     * @param Closure $beforeInsert
     */
    public function setBeforeInsert(Closure $beforeInsert): void
    {
        $this->beforeInsert = $beforeInsert;
    }

    protected Closure $beforeUpdate;

    /**
     * @return Closure
     */
    public function getBeforeUpdate(): Closure
    {
        return $this->beforeUpdate;
    }

    /**
     * @param mixed $beforeUpdate
     */
    public function setBeforeUpdate(Closure $beforeUpdate): void
    {
        $this->beforeUpdate = $beforeUpdate;
    }
}
