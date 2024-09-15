<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Core\IteratorFilter;
use ByJG\AnyDataset\Db\DbDriverInterface;
use ByJG\AnyDataset\Db\IteratorFilterSqlFormatter;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Exception\UserExistsException;
use ByJG\Authenticate\Exception\UserNotFoundException;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\MicroOrm\DeleteQuery;
use ByJG\MicroOrm\Exception\InvalidArgumentException as ExceptionInvalidArgumentException;
use ByJG\MicroOrm\Exception\OrmBeforeInvalidException;
use ByJG\MicroOrm\Exception\OrmInvalidFieldsException;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\MicroOrm\Exception\RepositoryReadOnlyException;
use ByJG\MicroOrm\Exception\UpdateConstraintException;
use ByJG\MicroOrm\FieldMapping;
use ByJG\MicroOrm\Mapper;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Repository;
use ByJG\Serializer\Exception\InvalidArgumentException;
use Exception;
use ReflectionException;

class UsersDBDataset extends UsersBase
{

    /**
     * @var Repository
     */
    protected Repository $userRepository;

    /**
     * @var Repository
     */
    protected Repository $propertiesRepository;

    /**
     * @var DbDriverInterface
     */
    protected DbDriverInterface $provider;

    /**
     * UsersDBDataset constructor
     *
     * @param DbDriverInterface $dbDriver
     * @param UserDefinition|null $userTable
     * @param UserPropertiesDefinition|null $propertiesTable
     *
     * @throws OrmModelInvalidException
     * @throws ReflectionException
     */
    public function __construct(
        DbDriverInterface $dbDriver,
        UserDefinition $userTable = null,
        UserPropertiesDefinition $propertiesTable = null
    ) {
        if (empty($userTable)) {
            $userTable = new UserDefinition();
        }

        if (empty($propertiesTable)) {
            $propertiesTable = new UserPropertiesDefinition();
        }

        $userMapper = new Mapper(
            $userTable->model(),
            $userTable->table(),
            $userTable->getUserid()
        );
        $seed = $userTable->getGenerateKeyClosure();
        if (!empty($seed)) {
            $userMapper->withPrimaryKeySeedFunction($seed);
        }

        $propertyDefinition = $userTable->toArray();

        foreach ($propertyDefinition as $property => $map) {
            $userMapper->addFieldMapping(FieldMapping::create($property)
                ->withFieldName($map)
                ->withUpdateFunction($userTable->getClosureForUpdate($property))
                ->withSelectFunction($userTable->getClosureForSelect($property))
            );
        }
        $this->userRepository = new Repository($dbDriver, $userMapper);

        $propertiesMapper = new Mapper(
            UserPropertiesModel::class,
            $propertiesTable->table(),
            $propertiesTable->getId()
        );
        $propertiesMapper->addFieldMapping(FieldMapping::create('id')->withFieldName($propertiesTable->getId()));
        $propertiesMapper->addFieldMapping(FieldMapping::create('name')->withFieldName($propertiesTable->getName()));
        $propertiesMapper->addFieldMapping(FieldMapping::create('value')->withFieldName($propertiesTable->getValue()));
        $propertiesMapper->addFieldMapping(FieldMapping::create(UserDefinition::FIELD_USERID)
            ->withFieldName($propertiesTable->getUserid())
            ->withUpdateFunction($userTable->getClosureForUpdate(UserDefinition::FIELD_USERID))
            ->withSelectFunction($userTable->getClosureForSelect(UserDefinition::FIELD_USERID))
        );
        $this->propertiesRepository = new Repository($dbDriver, $propertiesMapper);

        $this->userTable = $userTable;
        $this->propertiesTable = $propertiesTable;
    }

    /**
     * Save the current UsersAnyDataset
     *
     * @param UserModel $model
     * @return UserModel
     * @throws UserExistsException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws Exception
     */
    public function save(UserModel $model): UserModel
    {
        $newUser = false;
        if (empty($model->getUserid())) {
            $this->canAddUser($model);
            $newUser = true;
        }

        $this->userRepository->setBeforeUpdate($this->userTable->getBeforeUpdate());
        $this->userRepository->setBeforeInsert($this->userTable->getBeforeInsert());
        $this->userRepository->save($model);

        foreach ($model->getProperties() as $property) {
            $property->setUserid($model->getUserid());
            $this->propertiesRepository->save($property);
        }

        if ($newUser) {
            $model = $this->getByEmail($model->getEmail());
        }

        if ($model === null) {
            throw new UserExistsException("User not found");
        }

        return $model;
    }

    /**
     * Get the users database information based on a filter.
     *
     * @param IteratorFilter|null $filter Filter to find user
     * @return UserModel[]
     */
    public function getIterator(IteratorFilter $filter = null): array
    {
        if (is_null($filter)) {
            $filter = new IteratorFilter();
        }

        $param = [];
        $formatter = new IteratorFilterSqlFormatter();
        $sql = $formatter->getFilter($filter->getRawFilters(), $param);

        $query = Query::getInstance()
            ->table($this->getUserDefinition()->table())
            ->where($sql, $param);

        return $this->userRepository->getByQuery($query);
    }

    /**
     * Get the user based on a filter.
     * Return Row if user was found; null, otherwise
     *
     * @param IteratorFilter $filter Filter to find user
     * @return UserModel|null
     */
    public function getUser(IteratorFilter $filter): UserModel|null
    {
        $result = $this->getIterator($filter);
        if (count($result) === 0) {
            return null;
        }

        $model = $result[0];

        $this->setPropertiesInUser($model);

        return $model;
    }

    /**
     * Remove the user based on his user login.
     *
     * @param string $login
     * @return bool
     * @throws Exception
     */
    public function removeByLoginField(string $login): bool
    {
        $user = $this->getByLoginField($login);

        if ($user !== null) {
            return $this->removeUserById($user->getUserid());
        }

        return false;
    }

    /**
     * Remove the user based on his user id.
     *
     * @param string $userid
     * @return bool
     * @throws Exception
     */
    public function removeUserById(string $userid): bool
    {
        $updateTableProperties = DeleteQuery::getInstance()
            ->table($this->getUserPropertiesDefinition()->table())
            ->where(
                "{$this->getUserPropertiesDefinition()->getUserid()} = :id",
                [
                    "id" => $userid
                ]
            );
        $this->propertiesRepository->deleteByQuery($updateTableProperties);

        $this->userRepository->delete($userid);

        return true;
    }

    /**
     * Get the user based on his property.
     *
     * @param string $propertyName
     * @param string $value
     * @return array
     * @throws InvalidArgumentException
     * @throws ExceptionInvalidArgumentException
     */
    public function getUsersByProperty(string $propertyName, string $value): array
    {
        return $this->getUsersByPropertySet([$propertyName => $value]);
    }

    /**
     * Get the user based on his property and value. e.g. [ 'key' => 'value', 'key2' => 'value2' ].
     *
     * @param array $propertiesArray
     * @return array
     * @throws InvalidArgumentException
     * @throws ExceptionInvalidArgumentException
     */
    public function getUsersByPropertySet(array $propertiesArray): array
    {
        $query = Query::getInstance()
            ->field("u.*")
            ->table($this->getUserDefinition()->table(),  "u");

        $count = 0;
        foreach ($propertiesArray as $propertyName => $value) {
            $count++;
            $query->join($this->getUserPropertiesDefinition()->table(), "p$count.{$this->getUserPropertiesDefinition()->getUserid()} = u.{$this->getUserDefinition()->getUserid()}", "p$count")
                ->where("p$count.{$this->getUserPropertiesDefinition()->getName()} = :name$count", ["name$count" => $propertyName])
                ->where("p$count.{$this->getUserPropertiesDefinition()->getValue()} = :value$count", ["value$count" => $value]);
        }

        return $this->userRepository->getByQuery($query);
    }

    /**
     * @param string $userId
     * @param string $propertyName
     * @param string|null $value
     * @return bool
     * @throws UserNotFoundException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws Exception
     */
    public function addProperty(string $userId, string $propertyName, string|null $value): bool
    {
        //anydataset.Row
        $user = $this->getById($userId);
        if (empty($user)) {
            return false;
        }

        if (!$this->hasProperty($userId, $propertyName, $value)) {
            $propertiesModel = new UserPropertiesModel($propertyName, $value);
            $propertiesModel->setUserid($userId);
            $this->propertiesRepository->save($propertiesModel);
        }

        return true;
    }

    /**
     * @throws UpdateConstraintException
     * @throws RepositoryReadOnlyException
     * @throws InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws ExceptionInvalidArgumentException
     * @throws OrmInvalidFieldsException
     */
    public function setProperty(string $userId, string $propertyName, string|null $value): bool
    {
        $query = Query::getInstance()
            ->table($this->getUserPropertiesDefinition()->table())
            ->where("{$this->getUserPropertiesDefinition()->getUserid()} = :id", ["id" => $userId])
            ->where("{$this->getUserPropertiesDefinition()->getName()} = :name", ["name" => $propertyName]);

        $userProperty = $this->propertiesRepository->getByQuery($query);
        if (empty($userProperty)) {
            $userProperty = new UserPropertiesModel($propertyName, $value);
            $userProperty->setUserid($userId);
        } else {
            $userProperty = $userProperty[0];
            $userProperty->setValue($value);
        }

        $this->propertiesRepository->save($userProperty);

        return true;
    }

    /**
     * Remove a specific site from user
     * Return True or false
     *
     * @param string $userId User Id
     * @param string $propertyName Property name
     * @param string|null $value Property value with a site
     * @return bool
     * @throws ExceptionInvalidArgumentException
     * @throws InvalidArgumentException
     * @throws RepositoryReadOnlyException
     */
    public function removeProperty(string $userId, string $propertyName, string|null $value = null): bool
    {
        $user = $this->getById($userId);
        if ($user !== null) {

            $updateable = DeleteQuery::getInstance()
                ->table($this->getUserPropertiesDefinition()->table())
                ->where("{$this->getUserPropertiesDefinition()->getUserid()} = :id", ["id" => $userId])
                ->where("{$this->getUserPropertiesDefinition()->getName()} = :name", ["name" => $propertyName]);

            if (!empty($value)) {
                $updateable->where("{$this->getUserPropertiesDefinition()->getValue()} = :value", ["value" => $value]);
            }

            $this->propertiesRepository->deleteByQuery($updateable);

            return true;
        }

        return false;
    }

    /**
     * Remove a specific site from all users
     * Return True or false
     *
     * @param string $propertyName Property name
     * @param string|null $value Property value with a site
     * @return void
     * @throws ExceptionInvalidArgumentException
     * @throws RepositoryReadOnlyException
     */
    public function removeAllProperties(string $propertyName, string|null $value = null): void
    {
        $updateable = DeleteQuery::getInstance()
            ->table($this->getUserPropertiesDefinition()->table())
            ->where("{$this->getUserPropertiesDefinition()->getName()} = :name", ["name" => $propertyName]);

        if (!empty($value)) {
            $updateable->where("{$this->getUserPropertiesDefinition()->getValue()} = :value", ["value" => $value]);
        }

        $this->propertiesRepository->deleteByQuery($updateable);
    }

    public function getProperty(string $userId, string $propertyName): array|string|null
    {
        $query = Query::getInstance()
            ->table($this->getUserPropertiesDefinition()->table())
            ->where("{$this->getUserPropertiesDefinition()->getUserid()} = :id", ['id' =>$userId])
            ->where("{$this->getUserPropertiesDefinition()->getName()} = :name", ['name' =>$propertyName]);

        $result = [];
        foreach ($this->propertiesRepository->getByQuery($query) as $model) {
            $result[] = $model->getValue();
        }

        if (count($result) === 0) {
            return null;
        }

        if (count($result) === 1) {
            return $result[0];
        }

        return $result;
    }

    /**
     * Return all property's fields from this user
     *
     * @param UserModel $userRow
     */
    protected function setPropertiesInUser(UserModel $userRow): void
    {
        $value = $this->propertiesRepository->getMapper()->getFieldMap(UserDefinition::FIELD_USERID)->getUpdateFunctionValue($userRow->getUserid(), $userRow);
        $query = Query::getInstance()
            ->table($this->getUserPropertiesDefinition()->table())
            ->where("{$this->getUserPropertiesDefinition()->getUserid()} = :id", ['id' => $value]);
        $userRow->setProperties($this->propertiesRepository->getByQuery($query));
    }
}
