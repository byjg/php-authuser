<?php

namespace ByJG\Authenticate\Repository;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\Exception\OrmBeforeInvalidException;
use ByJG\MicroOrm\Exception\OrmInvalidFieldsException;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
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

    /**
     * @throws OrmModelInvalidException
     * @throws ReflectionException
     */
    public function __construct(DatabaseExecutor $executor, string $usersClass)
    {
        $this->mapper = new Mapper($usersClass);
        $this->repository = new Repository($executor, $usersClass);
    }

    /**
     * Save a user
     *
     * @param UserModel $model
     * @return UserModel
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     */
    public function save(UserModel $model): UserModel
    {
        $this->repository->save($model);
        return $model;
    }

    /**
     * Get user by ID
     *
     * @param string|HexUuidLiteral|int $userid
     * @return UserModel|null
     */
    public function getById(string|HexUuidLiteral|int $userid): ?UserModel
    {
        return $this->repository->get($userid);
    }

    /**
     * Get user by field value
     *
     * @param string $field Property name (e.g., 'username', 'email')
     * @param string|HexUuidLiteral|int $value
     * @return UserModel|null
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getByField(string $field, string|HexUuidLiteral|int $value): ?UserModel
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
     * @param string|HexUuidLiteral|int $userid
     * @return void
     * @throws \Exception
     */
    public function deleteById(string|HexUuidLiteral|int $userid): void
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
