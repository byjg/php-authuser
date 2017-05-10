<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Dataset\IteratorFilterSqlFormatter;
use ByJG\AnyDataset\Factory;
use ByJG\AnyDataset\Dataset\IteratorFilter;
use ByJG\Authenticate\Definition\CustomTable;
use ByJG\Authenticate\Definition\UserTable;
use ByJG\Authenticate\Exception\UserExistsException;
use ByJG\Authenticate\Model\CustomModel;
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
    protected $_customRepository;

    /**
     * @var \ByJG\AnyDataset\DbDriverInterface
     */
    protected $_provider;

    /**
     * UsersDBDataset constructor
     *
     * @param string $connectionString
     * @param UserTable $userTable
     * @param CustomTable $customTable
     */
    public function __construct($connectionString, UserTable $userTable = null, CustomTable $customTable = null)
    {
        if (empty($userTable)) {
            $userTable = new UserTable();
        }

        if (empty($customTable)) {
            $customTable = new CustomTable();
        }

        $me = $this;

        $provider = Factory::getDbRelationalInstance($connectionString);
        $userMapper = new Mapper(
            UserModel::class,
            $userTable->getTable(),
            $userTable->getUserid()
        );
        $userMapper->addFieldMap('userid', $userTable->getUserid());
        $userMapper->addFieldMap('name', $userTable->getName());
        $userMapper->addFieldMap('email', $userTable->getEmail());
        $userMapper->addFieldMap('username', $userTable->getUsername());
        $userMapper->addFieldMap(
            'password',
            $userTable->getPassword(),
            function ($value, $instance) use ($me) {
                return $me->getPasswordHash($value);
            }
        );
        $userMapper->addFieldMap('created', $userTable->getCreated());
        $userMapper->addFieldMap('admin', $userTable->getAdmin());
        $this->_userRepository = new Repository($provider, $userMapper);

        $customMapper = new Mapper(
            CustomModel::class,
            $customTable->getTable(),
            $customTable->getCustomid()
        );
        $customMapper->addFieldMap('customid', $customTable->getCustomid());
        $customMapper->addFieldMap('name', $customTable->getName());
        $customMapper->addFieldMap('value', $customTable->getValue());
        $customMapper->addFieldMap('userid', $customTable->getUserid());
        $this->_customRepository = new Repository($provider, $customMapper);

        $this->_userTable = $userTable;
        $this->_customTable = $customTable;
    }

    /**
     * Save the current UsersAnyDataset
     *
     * @param \ByJG\Authenticate\Model\UserModel $user
     */
    public function save(UserModel $user)
    {
        $this->_userRepository->save($user);

        foreach ($user->getCustomProperties() as $custom) {
            $custom->setUserid($user->getUserid());
            $this->_customRepository->save($custom);
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
            ->table($this->getUserTable()->getTable())
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

        $this->setCustomFieldsInUser($model);

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
        $updtableCustom = Updatable::getInstance()
            ->table($this->getCustomTable()->getTable())
            ->where("{$this->getCustomTable()->getUserid()} = :id", ["id" => $this->getUserTable()->getUserid()]);
        $this->_customRepository->deleteByQuery($updtableCustom);

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
            $custom = new CustomModel($propertyName, $value);
            $custom->setUserid($userId);
            $this->_customRepository->save($custom);
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
                ->table($this->getCustomTable()->getTable())
                ->where("{$this->getCustomTable()->getUserid()} = :id", ["id" => $userId])
                ->where("{$this->getCustomTable()->getName()} = :name", ["name" => $propertyName]);

            if (!empty($value)) {
                $updateable->where("{$this->getCustomTable()->getValue()} = :value", ["value" => $value]);
            }

            $this->_customRepository->deleteByQuery($updateable);

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
            ->table($this->getCustomTable()->getTable())
            ->where("{$this->getCustomTable()->getName()} = :name}", ["name" => $propertyName]);

        if (!empty($value)) {
            $updateable->where("{$this->getCustomTable()->getValue()} = :value}", ["value" => $value]);
        }

        $this->_customRepository->deleteByQuery($updateable);

        return true;
    }

    /**
     * Return all custom's fields from this user
     *
     * @param UserModel $userRow
     */
    protected function setCustomFieldsInUser(UserModel $userRow)
    {
        $query = Query::getInstance()
            ->table($this->getCustomTable()->getTable())
            ->where("{$this->getCustomTable()->getUserid()} = :id", ['id'=>$userRow->getUserid()]);
        $userRow->setCustomProperties($this->_customRepository->getByQuery($query));
    }
}
