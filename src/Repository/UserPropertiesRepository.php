<?php

namespace ByJG\Authenticate\Repository;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\MicroOrm\DeleteQuery;
use ByJG\MicroOrm\Exception\OrmBeforeInvalidException;
use ByJG\MicroOrm\Exception\OrmInvalidFieldsException;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\MicroOrm\Exception\RepositoryReadOnlyException;
use ByJG\MicroOrm\Literal\Literal;
use ByJG\MicroOrm\Mapper;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Repository;
use ByJG\XmlUtil\Exception\FileException;
use ByJG\XmlUtil\Exception\XmlUtilException;
use ReflectionException;

/**
 * Repository for User Properties operations
 */
class UserPropertiesRepository
{
    protected Repository $repository;
    protected Mapper $mapper;

    /**
     * @throws OrmModelInvalidException
     * @throws ReflectionException
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
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
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
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getByUserId(string|Literal|int $userid): array
    {
        $useridField = $this->mapper->getFieldMap('userid')->getFieldName();
        $query = Query::getInstance()
            ->table($this->mapper->getTable())
            ->where("$useridField = :userid", ['userid' => $userid]);

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
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getByUserIdAndName(string|Literal|int $userid, string $propertyName): array
    {
        $useridField = $this->mapper->getFieldMap('userid')->getFieldName();
        $nameField = $this->mapper->getFieldMap('name')->getFieldName();

        $query = Query::getInstance()
            ->table($this->mapper->getTable())
            ->where("$useridField = :userid", ['userid' => $userid])
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
     */
    public function deleteByUserId(string|Literal|int $userid): void
    {
        $useridField = $this->mapper->getFieldMap('userid')->getFieldName();

        $deleteQuery = DeleteQuery::getInstance()
            ->table($this->mapper->getTable())
            ->where("$useridField = :userid", ['userid' => $userid]);

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
        $useridField = $this->mapper->getFieldMap('userid')->getFieldName();
        $nameField = $this->mapper->getFieldMap('name')->getFieldName();
        $valueField = $this->mapper->getFieldMap('value')->getFieldName();

        $deleteQuery = DeleteQuery::getInstance()
            ->table($this->mapper->getTable())
            ->where("$useridField = :userid", ['userid' => $userid])
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
        $nameField = $this->mapper->getFieldMap('name')->getFieldName();
        $valueField = $this->mapper->getFieldMap('value')->getFieldName();

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
