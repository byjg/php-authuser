<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Core\IteratorFilter;
use ByJG\AnyDataset\Db\DbDriverInterface;
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\IteratorFilterSqlFormatter;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\MicroOrm\Exception\InvalidArgumentException as ExceptionInvalidArgumentException;
use ByJG\MicroOrm\FieldMapping;
use ByJG\MicroOrm\Mapper;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Repository;
use ByJG\MicroOrm\Updatable;
use ByJG\Serializer\Exception\InvalidArgumentException;

class UsersDBDataset extends UsersBase
{

    /**
     * @var \ByJG\MicroOrm\Repository
     */
    protected $userRepository;

    /**
     * @var \ByJG\MicroOrm\Repository
     */
    protected $propertiesRepository;

    /**
     * @var \ByJG\AnyDataset\Db\DbDriverInterface
     */
    protected $provider;

    /**
     * UsersDBDataset constructor
     *
     * @param \ByJG\AnyDataset\Db\DbDriverInterface $dbDriver
     * @param \ByJG\Authenticate\Definition\UserDefinition $userTable
     * @param \ByJG\Authenticate\Definition\UserPropertiesDefinition $propertiesTable
     *
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     * @throws \ByJG\MicroOrm\Exception\OrmModelInvalidException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
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
     * @param \ByJG\Authenticate\Model\UserModel $user
     * @return \ByJG\Authenticate\Model\UserModel
     * @throws \ByJG\Authenticate\Exception\UserExistsException
     * @throws \ByJG\MicroOrm\Exception\OrmBeforeInvalidException
     * @throws \ByJG\MicroOrm\Exception\OrmInvalidFieldsException
     * @throws \Exception
     */
    public function save(UserModel $user)
    {
        $newUser = false;
        if (empty($user->getUserid())) {
            $this->canAddUser($user);
            $newUser = true;
        }

        $this->userRepository->setBeforeUpdate($this->userTable->getBeforeUpdate());
        $this->userRepository->setBeforeInsert($this->userTable->getBeforeInsert());
        $this->userRepository->save($user);

        foreach ($user->getProperties() as $property) {
            $property->setUserid($user->getUserid());
            $this->propertiesRepository->save($property);
        }

        if ($newUser) {
            $user = $this->getByEmail($user->getEmail());
        }

        return $user;
    }

    /**
     * Get the users database information based on a filter.
     *
     * @param IteratorFilter $filter Filter to find user
     * @return UserModel[]
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function getIterator(IteratorFilter $filter = null)
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
     * @return UserModel
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function getUser($filter)
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
     * @throws \Exception
     */
    public function removeByLoginField($login)
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
     * @param mixed $userId
     * @return bool
     * @throws \Exception
     */
    public function removeUserById($userId)
    {
        $updateTableProperties = Updatable::getInstance()
            ->table($this->getUserPropertiesDefinition()->table())
            ->where(
                "{$this->getUserPropertiesDefinition()->getUserid()} = :id",
                [
                    "id" => $userId
                ]
            );
        $this->propertiesRepository->deleteByQuery($updateTableProperties);

        $this->userRepository->delete($userId);

        return true;
    }

    /**
     * Get the user based on his property.
     *
     * @param mixed $propertyName
     * @param mixed $value
     * @return array
     * @throws InvalidArgumentException
     * @throws ExceptionInvalidArgumentException
     */
    public function getUsersByProperty($propertyName, $value)
    {
        return $this->getUsersByPropertySet([$propertyName => $value]);
    }

    /**
     * Get the user based on his property and value. e.g. [ 'key' => 'value', 'key2' => 'value2' ].
     *
     * @param mixed $propertiesArray
     * @return array
     * @throws InvalidArgumentException
     * @throws ExceptionInvalidArgumentException
     */
    public function getUsersByPropertySet($propertiesArray)
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
     * @param int|string $userId
     * @param string $propertyName
     * @param string $value
     * @return bool
     * @throws \ByJG\Authenticate\Exception\UserNotFoundException
     * @throws \ByJG\MicroOrm\Exception\OrmBeforeInvalidException
     * @throws \ByJG\MicroOrm\Exception\OrmInvalidFieldsException
     * @throws \Exception
     */
    public function addProperty($userId, $propertyName, $value)
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

    public function setProperty($userId, $propertyName, $value)
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
    }

    /**
     * Remove a specific site from user
     * Return True or false
     *
     * @param int|string $userId User Id
     * @param string $propertyName Property name
     * @param string $value Property value with a site
     * @return bool
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function removeProperty($userId, $propertyName, $value = null)
    {
        $user = $this->getById($userId);
        if ($user !== null) {

            $updateable = Updatable::getInstance()
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
     * @param string $value Property value with a site
     * @return bool
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function removeAllProperties($propertyName, $value = null)
    {
        $updateable = Updatable::getInstance()
            ->table($this->getUserPropertiesDefinition()->table())
            ->where("{$this->getUserPropertiesDefinition()->getName()} = :name", ["name" => $propertyName]);

        if (!empty($value)) {
            $updateable->where("{$this->getUserPropertiesDefinition()->getValue()} = :value", ["value" => $value]);
        }

        $this->propertiesRepository->deleteByQuery($updateable);

        return true;
    }

    public function getProperty($userId, $propertyName)
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
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    protected function setPropertiesInUser(UserModel $userRow)
    {
        $value = $this->propertiesRepository->getMapper()->getFieldMap(UserDefinition::FIELD_USERID)->getUpdateFunctionValue($userRow->getUserid(), $userRow);
        $query = Query::getInstance()
            ->table($this->getUserPropertiesDefinition()->table())
            ->where("{$this->getUserPropertiesDefinition()->getUserid()} = :id", ['id' => $value]);
        $userRow->setProperties($this->propertiesRepository->getByQuery($query));
    }
}
