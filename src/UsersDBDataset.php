<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Dataset\IteratorFilterSqlFormatter;
use ByJG\AnyDataset\Factory;
use ByJG\AnyDataset\Dataset\IteratorFilter;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Exception\UserExistsException;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\Mapper;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Repository;
use ByJG\MicroOrm\Updatable;

class UsersDBDataset extends UsersBase
{

    /**
     * @var \ByJG\MicroOrm\Repository
     */
    protected $_userRepository;

    /**
     * @var \ByJG\MicroOrm\Repository
     */
    protected $_propertiesRepository;

    /**
     * @var \ByJG\AnyDataset\DbDriverInterface
     */
    protected $_provider;

    /**
     * UsersDBDataset constructor
     *
     * @param string $connectionString
     * @param UserDefinition $userTable
     * @param UserPropertiesDefinition $propertiesTable
     */
    public function __construct($connectionString, UserDefinition $userTable = null, UserPropertiesDefinition $propertiesTable = null)
    {
        if (empty($userTable)) {
            $userTable = new UserDefinition();
        }

        if (empty($propertiesTable)) {
            $propertiesTable = new UserPropertiesDefinition();
        }

        $me = $this;

        $provider = Factory::getDbRelationalInstance($connectionString);
        $userMapper = new Mapper(
            UserModel::class,
            $userTable->getTable(),
            $userTable->getUserid()
        );
        $userMapper->addFieldMap(
            'userid',
            $userTable->getUserid(),
            $userTable->getClosureForUpdate('userid'),
            $userTable->getClosureForSelect('userid')
        );
        $userMapper->addFieldMap(
            'name',
            $userTable->getName(),
            $userTable->getClosureForUpdate('name'),
            $userTable->getClosureForSelect('name')
        );
        $userMapper->addFieldMap(
            'email',
            $userTable->getEmail(),
            $userTable->getClosureForUpdate('email'),
            $userTable->getClosureForSelect('email')
        );
        $userMapper->addFieldMap(
            'username',
            $userTable->getUsername(),
            $userTable->getClosureForUpdate('username'),
            $userTable->getClosureForSelect('username')
        );
        $userMapper->addFieldMap(
            'password',
            $userTable->getPassword(),
            $userTable->getClosureForUpdate('password'),
            $userTable->getClosureForSelect('password')
        );
        $userMapper->addFieldMap(
            'created',
            $userTable->getCreated(),
            $userTable->getClosureForUpdate('created'),
            $userTable->getClosureForSelect('created')
        );
        $userMapper->addFieldMap(
            'admin',
            $userTable->getAdmin(),
            $userTable->getClosureForUpdate('admin'),
            $userTable->getClosureForSelect('admin')
        );
        $this->_userRepository = new Repository($provider, $userMapper);

        $propertiesMapper = new Mapper(
            UserPropertiesModel::class,
            $propertiesTable->getTable(),
            $propertiesTable->getId()
        );
        $propertiesMapper->addFieldMap('id', $propertiesTable->getId());
        $propertiesMapper->addFieldMap('name', $propertiesTable->getName());
        $propertiesMapper->addFieldMap('value', $propertiesTable->getValue());
        $propertiesMapper->addFieldMap('userid', $propertiesTable->getUserid());
        $this->_propertiesRepository = new Repository($provider, $propertiesMapper);

        $this->_userTable = $userTable;
        $this->_propertiesTable = $propertiesTable;
    }

    /**
     * Save the current UsersAnyDataset
     *
     * @param \ByJG\Authenticate\Model\UserModel $user
     */
    public function save(UserModel $user)
    {
        $this->_userRepository->save($user);

        foreach ($user->getProperties() as $property) {
            $property->setUserid($user->getUserid());
            $this->_propertiesRepository->save($property);
        }
    }

    /**
     * Add new user in database
     *
     * @param string $name
     * @param string $userName
     * @param string $email
     * @param string $password
     * @return bool
     * @throws UserExistsException
     */
    public function addUser($name, $userName, $email, $password)
    {
        if ($this->getByEmail($email) !== null) {
            throw new UserExistsException('Email already exists');
        }
        if ($this->getByUsername($userName) !== null) {
            throw new UserExistsException('Username already exists');
        }

        $model = new UserModel($name, $email, $userName, $password);
        $this->_userRepository->save($model);

        return true;
    }

    /**
     * Get the users database information based on a filter.
     *
     * @param IteratorFilter $filter Filter to find user
     * @return UserModel[]
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
            ->table($this->getUserDefinition()->getTable())
            ->where($sql, $param);

        return $this->_userRepository->getByQuery($query);
    }

    /**
     * Get the user based on a filter.
     * Return Row if user was found; null, otherwise
     *
     * @param IteratorFilter $filter Filter to find user
     * @return UserModel
     * */
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
     * */
    public function removeUserName($login)
    {
        $user = $this->getByUsername($login);

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
     * */
    public function removeUserById($userId)
    {
        $updtableProperties = Updatable::getInstance()
            ->table($this->getUserPropertiesDefinition()->getTable())
            ->where("{$this->getUserPropertiesDefinition()->getUserid()} = :id", ["id" => $this->getUserDefinition()->getUserid()]);
        $this->_propertiesRepository->deleteByQuery($updtableProperties);

        $this->_userRepository->delete($userId);

        return true;
    }

    /**
     *
     * @param int $userId
     * @param string $propertyName
     * @param string $value
     * @return bool
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
            $this->_propertiesRepository->save($propertiesModel);
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
     * */
    public function removeProperty($userId, $propertyName, $value = null)
    {
        $user = $this->getById($userId);
        if ($user !== null) {

            $updateable = Updatable::getInstance()
                ->table($this->getUserPropertiesDefinition()->getTable())
                ->where("{$this->getUserPropertiesDefinition()->getUserid()} = :id", ["id" => $userId])
                ->where("{$this->getUserPropertiesDefinition()->getName()} = :name", ["name" => $propertyName]);

            if (!empty($value)) {
                $updateable->where("{$this->getUserPropertiesDefinition()->getValue()} = :value", ["value" => $value]);
            }

            $this->_propertiesRepository->deleteByQuery($updateable);

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
     * */
    public function removeAllProperties($propertyName, $value = null)
    {
        $updateable = Updatable::getInstance()
            ->table($this->getUserPropertiesDefinition()->getTable())
            ->where("{$this->getUserPropertiesDefinition()->getName()} = :name", ["name" => $propertyName]);

        if (!empty($value)) {
            $updateable->where("{$this->getUserPropertiesDefinition()->getValue()} = :value", ["value" => $value]);
        }

        $this->_propertiesRepository->deleteByQuery($updateable);

        return true;
    }

    /**
     * Return all property's fields from this user
     *
     * @param UserModel $userRow
     */
    protected function setPropertiesInUser(UserModel $userRow)
    {
        $query = Query::getInstance()
            ->table($this->getUserPropertiesDefinition()->getTable())
            ->where("{$this->getUserPropertiesDefinition()->getUserid()} = :id", ['id' =>$userRow->getUserid()]);
        $userRow->setProperties($this->_propertiesRepository->getByQuery($query));
    }
}
