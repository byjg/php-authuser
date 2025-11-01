<?php

namespace ByJG\Authenticate\Definition;

use ByJG\Authenticate\EntityProcessors\ClosureEntityProcessor;
use ByJG\Authenticate\EntityProcessors\PassThroughEntityProcessor;
use ByJG\Authenticate\MapperFunctions\ClosureMapper;
use ByJG\Authenticate\MapperFunctions\PasswordSha1Mapper;
use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\Interface\EntityProcessorInterface;
use ByJG\MicroOrm\Interface\MapperFunctionInterface;
use ByJG\MicroOrm\MapperFunctions\ReadOnlyMapper;
use ByJG\MicroOrm\MapperFunctions\StandardMapper;
use ByJG\Serializer\Serialize;
use Closure;
use InvalidArgumentException;

/**
 * Structure to represent the users
 */
class UserDefinition
{
    protected string $__table = 'users';
    protected array $__mappers = ["select" => [], "update" => [] ];
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

        $this->defineMapperForUpdate(UserDefinition::FIELD_PASSWORD, PasswordSha1Mapper::class);

        if ($loginField !== self::LOGIN_IS_USERNAME && $loginField !== self::LOGIN_IS_EMAIL) {
            throw new InvalidArgumentException('Login field is invalid. ');
        }
        $this->__loginField = $loginField;

        $this->beforeInsert = new PassThroughEntityProcessor();
        $this->beforeUpdate = new PassThroughEntityProcessor();
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
     * @param MapperFunctionInterface|string $mapper
     */
    private function updateMapperDef(string $event, string $property, MapperFunctionInterface|string $mapper): void
    {
        $this->checkProperty($property);
        $this->__mappers[$event][$property] = $mapper;
    }

    private function getMapperDef(string $event, string $property): MapperFunctionInterface|string
    {
        $this->checkProperty($property);

        if (!$this->existsMapper($event, $property)) {
            return StandardMapper::class;
        }

        return $this->__mappers[$event][$property];
    }

    public function existsMapper(string $event, string $property): bool
    {
        // Event not set
        if (!isset($this->__mappers[$event])) {
            return false;
        }

        // Event is set but there is no property
        if (!array_key_exists($property, $this->__mappers[$event])) {
            return false;
        }

        return true;
    }

    /**
     * @deprecated Use existsMapper instead
     */
    public function existsClosure(string $event, string $property): bool
    {
        return $this->existsMapper($event, $property);
    }

    public function markPropertyAsReadOnly(string $property): void
    {
        $this->updateMapperDef(self::UPDATE, $property, ReadOnlyMapper::class);
    }

    public function defineMapperForUpdate(string $property, MapperFunctionInterface|string $mapper): void
    {
        $this->updateMapperDef(self::UPDATE, $property, $mapper);
    }

    public function defineMapperForSelect(string $property, MapperFunctionInterface|string $mapper): void
    {
        $this->updateMapperDef(self::SELECT, $property, $mapper);
    }

    /**
     * @deprecated Use defineMapperForUpdate instead
     */
    public function defineClosureForUpdate(string $property, Closure $closure): void
    {
        $this->updateMapperDef(self::UPDATE, $property, new ClosureMapper($closure));
    }

    /**
     * @deprecated Use defineMapperForSelect instead
     */
    public function defineClosureForSelect(string $property, Closure $closure): void
    {
        $this->updateMapperDef(self::SELECT, $property, new ClosureMapper($closure));
    }

    public function getMapperForUpdate(string $property): MapperFunctionInterface|string
    {
        return $this->getMapperDef(self::UPDATE, $property);
    }

    /**
     * @deprecated Use getMapperForUpdate instead. Returns a Closure for backward compatibility.
     */
    public function getClosureForUpdate(string $property): Closure
    {
        $mapper = $this->getMapperDef(self::UPDATE, $property);

        // Return a closure that wraps the mapper
        return function($value, $instance = null) use ($mapper) {
            if (is_string($mapper)) {
                $mapper = new $mapper();
            }
            return $mapper->processedValue($value, $instance, null);
        };
    }

    public function defineGenerateKeyClosure(Closure $closure): void
    {
        $this->__generateKey = $closure;
    }

    public function getGenerateKeyClosure(): ?Closure
    {
        return $this->__generateKey;
    }

    public function getMapperForSelect(string $property): MapperFunctionInterface|string
    {
        return $this->getMapperDef(self::SELECT, $property);
    }

    /**
     * @deprecated Use getMapperForSelect instead. Returns a Closure for backward compatibility.
     * @param $property
     * @return Closure
     */
    public function getClosureForSelect($property): Closure
    {
        $mapper = $this->getMapperDef(self::SELECT, $property);

        // Return a closure that wraps the mapper
        return function($value, $instance = null) use ($mapper) {
            if (is_string($mapper)) {
                $mapper = new $mapper();
            }
            return $mapper->processedValue($value, $instance, null);
        };
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

    protected EntityProcessorInterface $beforeInsert;

    /**
     * @return EntityProcessorInterface
     */
    public function getBeforeInsert(): EntityProcessorInterface
    {
        return $this->beforeInsert;
    }

    /**
     * @param EntityProcessorInterface|Closure $beforeInsert
     */
    public function setBeforeInsert(EntityProcessorInterface|Closure $beforeInsert): void
    {
        if ($beforeInsert instanceof Closure) {
            $beforeInsert = new ClosureEntityProcessor($beforeInsert);
        }
        $this->beforeInsert = $beforeInsert;
    }

    protected EntityProcessorInterface $beforeUpdate;

    /**
     * @return EntityProcessorInterface
     */
    public function getBeforeUpdate(): EntityProcessorInterface
    {
        return $this->beforeUpdate;
    }

    /**
     * @param EntityProcessorInterface|Closure $beforeUpdate
     */
    public function setBeforeUpdate(EntityProcessorInterface|Closure $beforeUpdate): void
    {
        if ($beforeUpdate instanceof Closure) {
            $beforeUpdate = new ClosureEntityProcessor($beforeUpdate);
        }
        $this->beforeUpdate = $beforeUpdate;
    }
}
