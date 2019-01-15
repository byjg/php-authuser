<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Core\IteratorFilter;
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\IteratorFilterSqlFormatter;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\MicroOrm\Mapper;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Repository;
use ByJG\MicroOrm\Updatable;

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
     * @param string $connectionString
     * @param UserDefinition $userTable
     * @param UserPropertiesDefinition $propertiesTable
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     * @throws \ByJG\MicroOrm\Exception\OrmModelInvalidException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function __construct(
        $connectionString,
        UserDefinition $userTable = null,
        UserPropertiesDefinition $propertiesTable = null
    ) {
        if (empty($userTable)) {
            $userTable = new UserDefinition();
        }

        if (empty($propertiesTable)) {
            $propertiesTable = new UserPropertiesDefinition();
        }

        $provider = Factory::getDbRelationalInstance($connectionString);
        $userMapper = new Mapper(
            $userTable->model(),
            $userTable->table(),
            $userTable->getUserid()
        );

        $propertyDefinition = $userTable->toArray();

        foreach ($propertyDefinition as $property => $map) {
            $userMapper->addFieldMap(
                $property,
                $map,
                $userTable->getClosureForUpdate($property),
                $userTable->getClosureForSelect($property)
            );
        }
        $this->userRepository = new Repository($provider, $userMapper);

        $propertiesMapper = new Mapper(
            UserPropertiesModel::class,
            $propertiesTable->table(),
            $propertiesTable->getId()
        );
        $propertiesMapper->addFieldMap('id', $propertiesTable->getId());
        $propertiesMapper->addFieldMap('name', $propertiesTable->getName());
        $propertiesMapper->addFieldMap('value', $propertiesTable->getValue());
        $propertiesMapper->addFieldMap('userid', $propertiesTable->getUserid());
        $this->propertiesRepository = new Repository($provider, $propertiesMapper);

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
        $updtableProperties = Updatable::getInstance()
            ->table($this->getUserPropertiesDefinition()->table())
            ->where(
                "{$this->getUserPropertiesDefinition()->getUserid()} = :id",
                [
                    "id" => $userId
                ]
            );
        $this->propertiesRepository->deleteByQuery($updtableProperties);

        $this->userRepository->delete($userId);

        return true;
    }

    /**
     * @param int $userId
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

    /**
     * Remove a specific site from user
     * Return True or false
     *
     * @param int $userId User Id
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

    /**
     * Return all property's fields from this user
     *
     * @param UserModel $userRow
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    protected function setPropertiesInUser(UserModel $userRow)
    {
        $query = Query::getInstance()
            ->table($this->getUserPropertiesDefinition()->table())
            ->where("{$this->getUserPropertiesDefinition()->getUserid()} = :id", ['id' =>$userRow->getUserid()]);
        $userRow->setProperties($this->propertiesRepository->getByQuery($query));
    }
}
