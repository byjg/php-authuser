<?php

namespace ByJG\Authenticate\Repository;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\Authenticate\Enum\UserPropertyField;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\MicroOrm\DeleteQuery;
use ByJG\MicroOrm\Exception\OrmBeforeInvalidException;
use ByJG\MicroOrm\Exception\OrmInvalidFieldsException;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\MicroOrm\Exception\RepositoryReadOnlyException;
use ByJG\MicroOrm\Exception\UpdateConstraintException;
use ByJG\MicroOrm\Literal\Literal;
use ByJG\MicroOrm\Mapper;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Repository;
use ByJG\XmlUtil\Exception\FileException;
use ByJG\XmlUtil\Exception\XmlUtilException;
use Psr\SimpleCache\InvalidArgumentException;
use ReflectionException;

/**
 * Repository for User Properties operations
 */
class UserPropertiesRepository
{
    protected Repository $repository;
    protected Mapper $mapper;

    /**
     * @param DatabaseExecutor $executor
     * @param string $propertiesClass
     * @throws OrmModelInvalidException
     * @throws ReflectionException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function __construct(DatabaseExecutor $executor, string $propertiesClass)
    {
        $this->mapper = new Mapper($propertiesClass);
        $this->repository = new Repository($executor, $propertiesClass);
    }

    /**
     * Save a property
     *
     * @param UserPropertiesModel $model
     * @return UserPropertiesModel
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     * @throws UpdateConstraintException
     */
    public function save(UserPropertiesModel $model): UserPropertiesModel
    {
        $this->repository->save($model);
        return $model;
    }

    /**
     * Get properties by user ID
     *
     * @param string|Literal|int $userid
     * @return UserPropertiesModel[]
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     * @throws InvalidArgumentException
     */
    public function getByUserId(string|Literal|int $userid): array
    {
        $userIdMapping = $this->mapper->getFieldMap(UserPropertyField::Userid->value);
        if ($userIdMapping === null) {
            throw new \InvalidArgumentException('User ID field mapping not found');
        }
        $userIdField = $userIdMapping->getFieldName();
        $query = Query::getInstance()
            ->table($this->mapper->getTable())
            ->where("$userIdField = :userid", ['userid' => $userIdMapping->getUpdateFunctionValue($userid, null)]);

        return $this->repository->getByQuery($query);
    }

    /**
     * Get specific property by user ID and name
     *
     * @param string|Literal|int $userid
     * @param string $propertyName
     * @return UserPropertiesModel[]
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     * @throws InvalidArgumentException
     */
    public function getByUserIdAndName(string|Literal|int $userid, string $propertyName): array
    {
        $userIdMapping = $this->mapper->getFieldMap(UserPropertyField::Userid->value);
        $nameMapping = $this->mapper->getFieldMap(UserPropertyField::Name->value);

        if ($userIdMapping === null || $nameMapping === null) {
            throw new \InvalidArgumentException('Required field mapping not found');
        }

        $userIdField = $userIdMapping->getFieldName();
        $nameField = $nameMapping->getFieldName();

        $query = Query::getInstance()
            ->table($this->mapper->getTable())
            ->where("$userIdField = :userid", ['userid' => $userIdMapping->getUpdateFunctionValue($userid, null)])
            ->where("$nameField = :name", ['name' => $propertyName]);

        return $this->repository->getByQuery($query);
    }

    /**
     * Delete all properties for a user
     *
     * @param string|Literal|int $userid
     * @return void
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws RepositoryReadOnlyException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function deleteByUserId(string|Literal|int $userid): void
    {
        $userIdMapping = $this->mapper->getFieldMap(UserPropertyField::Userid->value);
        if ($userIdMapping === null) {
            throw new \InvalidArgumentException('User ID field mapping not found');
        }
        $userIdField = $userIdMapping->getFieldName();

        $deleteQuery = DeleteQuery::getInstance()
            ->table($this->mapper->getTable())
            ->where("$userIdField = :userid", ['userid' => $userIdMapping->getUpdateFunctionValue($userid, null)]);

        $this->repository->deleteByQuery($deleteQuery);
    }

    /**
     * Delete specific property by user ID and name
     *
     * @param string|Literal|int $userid
     * @param string $propertyName
     * @param string|null $value
     * @return void
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws RepositoryReadOnlyException
     */
    public function deleteByUserIdAndName(string|Literal|int $userid, string $propertyName, ?string $value = null): void
    {
        $userIdMapping = $this->mapper->getFieldMap(UserPropertyField::Userid->value);
        $nameMapping = $this->mapper->getFieldMap(UserPropertyField::Name->value);
        $valueMapping = $this->mapper->getFieldMap(UserPropertyField::Value->value);

        if ($userIdMapping === null || $nameMapping === null || $valueMapping === null) {
            throw new \InvalidArgumentException('Required field mapping not found');
        }

        $userIdField = $userIdMapping->getFieldName();
        $nameField = $nameMapping->getFieldName();
        $valueField = $valueMapping->getFieldName();

        $deleteQuery = DeleteQuery::getInstance()
            ->table($this->mapper->getTable())
            ->where("$userIdField = :userid", ['userid' => $userIdMapping->getUpdateFunctionValue($userid, null)])
            ->where("$nameField = :name", ['name' => $propertyName]);

        if ($value !== null) {
            $deleteQuery->where("$valueField = :value", ['value' => $value]);
        }

        $this->repository->deleteByQuery($deleteQuery);
    }

    /**
     * Delete properties by name (all users)
     *
     * @param string $propertyName
     * @param string|null $value
     * @return void
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws RepositoryReadOnlyException
     */
    public function deleteByName(string $propertyName, ?string $value = null): void
    {
        $nameMapping = $this->mapper->getFieldMap(UserPropertyField::Name->value);
        $valueMapping = $this->mapper->getFieldMap(UserPropertyField::Value->value);

        if ($nameMapping === null || $valueMapping === null) {
            throw new \InvalidArgumentException('Required field mapping not found');
        }

        $nameField = $nameMapping->getFieldName();
        $valueField = $valueMapping->getFieldName();

        $deleteQuery = DeleteQuery::getInstance()
            ->table($this->mapper->getTable())
            ->where("$nameField = :name", ['name' => $propertyName]);

        if ($value !== null) {
            $deleteQuery->where("$valueField = :value", ['value' => $value]);
        }

        $this->repository->deleteByQuery($deleteQuery);
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
