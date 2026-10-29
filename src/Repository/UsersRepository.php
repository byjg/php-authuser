<?php

namespace ByJG\Authenticate\Repository;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\Authenticate\Definition\PasswordDefinition;
use ByJG\Authenticate\Enum\UserField;
use ByJG\Authenticate\Interfaces\PasswordMapperInterface;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Validator\PasswordDefinitionValidator;
use ByJG\MicroOrm\Exception\InvalidArgumentException;
use ByJG\MicroOrm\Exception\OrmBeforeInvalidException;
use ByJG\MicroOrm\Exception\OrmInvalidFieldsException;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\MicroOrm\Exception\RepositoryReadOnlyException;
use ByJG\MicroOrm\Exception\UpdateConstraintException;
use ByJG\MicroOrm\Interface\EntityValidatorInterface;
use ByJG\MicroOrm\Literal\Literal;
use ByJG\MicroOrm\Mapper;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Repository;
use ByJG\XmlUtil\Exception\FileException;
use ByJG\XmlUtil\Exception\XmlUtilException;
use ReflectionException;

/**
 * Repository for User operations
 */
class UsersRepository
{
    protected Repository $repository;
    protected Mapper $mapper;

    protected ?PasswordDefinition $passwordDefinition;

    /**
     * @param DatabaseExecutor $executor
     * @param string $usersClass
     * @param PasswordDefinition|null $passwordDefinition The password rules every save checks
     * @throws OrmModelInvalidException
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function __construct(DatabaseExecutor $executor, string $usersClass, ?PasswordDefinition $passwordDefinition = null)
    {
        $this->mapper = new Mapper($usersClass);
        $this->repository = new Repository($executor, $usersClass);
        $this->passwordDefinition = $passwordDefinition;

        if ($passwordDefinition !== null) {
            // The definition tells a stored hash from a plain password with the model's mapper
            $passwordMapper = $this->mapper->getFieldMap(UserField::Password->value)?->getUpdateFunction();
            if ($passwordMapper === null || !is_subclass_of($passwordMapper, PasswordMapperInterface::class, true)) {
                throw new \InvalidArgumentException('Password update function must implement PasswordMapperInterface');
            }
            /** @var PasswordMapperInterface|string $passwordMapper */
            $passwordDefinition->setPasswordMapper($passwordMapper);

            // Every save checks a plain password against the definition; the validator the
            // model declares still runs
            $modelValidator = $this->repository->getMapper()->getValidator();
            if (is_string($modelValidator)) {
                $modelValidator = new $modelValidator();
            }
            /** @var EntityValidatorInterface|null $modelValidator The repository checked it */
            $this->repository->setValidator(new PasswordDefinitionValidator($passwordDefinition, $modelValidator));
        }
    }

    /**
     * The password rules every save checks, if any
     */
    public function getPasswordDefinition(): ?PasswordDefinition
    {
        return $this->passwordDefinition;
    }

    /**
     * Save a user
     *
     * @param UserModel $model
     * @return UserModel
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws XmlUtilException
     * @throws RepositoryReadOnlyException
     * @throws UpdateConstraintException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function save(UserModel $model): UserModel
    {
        $this->repository->save($model);
        return $model;
    }

    /**
     * Get user by ID
     *
     * @param string|Literal|int $userid
     * @return UserModel|null
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws OrmInvalidFieldsException
     * @throws XmlUtilException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getById(string|Literal|int $userid): ?UserModel
    {
        return $this->repository->get($userid);
    }

    /**
     * Get user by field value
     *
     * @param string $field Property name (e.g., 'username', 'email')
     * @param string|Literal|int $value
     * @return UserModel|null
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getByField(string $field, string|Literal|int $value): ?UserModel
    {
        // Map the property name to the actual database column name
        $fieldMapping = $this->mapper->getFieldMap($field);
        $dbColumnName = $fieldMapping ? $fieldMapping->getFieldName() : $field;

        $query = Query::getInstance()
            ->table($this->mapper->getTable())
            ->where("$dbColumnName = :value", ['value' => $value]);

        $result = $this->repository->getByQuery($query);
        return count($result) > 0 ? $result[0] : null;
    }

    /**
     * Get all users
     *
     * @return UserModel[]
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getAll(): array
    {
        $query = Query::getInstance()->table($this->mapper->getTable());
        return $this->repository->getByQuery($query);
    }

    /**
     * Delete user by ID
     *
     * @param string|Literal|int $userid
     * @return void
     * @throws \Exception
     */
    public function deleteById(string|Literal|int $userid): void
    {
        $this->repository->delete($userid);
    }

    /**
     * Get the table name from mapper
     *
     * @return string
     */
    public function getTableName(): string
    {
        return $this->mapper->getTable();
    }

    /**
     * Get the primary key field name from mapper
     *
     * @return string|array
     */
    public function getPrimaryKeyName(): string|array
    {
        return $this->mapper->getPrimaryKey();
    }

    /**
     * Get the mapper
     *
     * @return Mapper
     */
    public function getMapper(): Mapper
    {
        return $this->mapper;
    }

    /**
     * Get the underlying repository
     *
     * @return Repository
     */
    public function getRepository(): Repository
    {
        return $this->repository;
    }
}
