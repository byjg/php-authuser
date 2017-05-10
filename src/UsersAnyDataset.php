<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Dataset\AnyDataset;
use ByJG\AnyDataset\Dataset\IteratorFilter;
use ByJG\AnyDataset\Enum\Relation;
use ByJG\AnyDataset\IteratorInterface;
use ByJG\AnyDataset\Dataset\Row;
use ByJG\Authenticate\Definition\CustomTable;
use ByJG\Authenticate\Definition\UserTable;
use ByJG\Authenticate\Exception\UserExistsException;
use ByJG\Authenticate\Model\CustomModel;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Serializer\BinderObject;

class UsersAnyDataset extends UsersBase
{

    /**
     * Internal AnyDataset structure to store the Users
     * @var AnyDataset
     */
    protected $_anyDataSet;

    /**
     * Internal Users file name
     *
     * @var string
     */
    protected $_usersFile;

    /**
     * AnyDataset constructor
     * @param string $file
     * @param UserTable $userTable
     * @param CustomTable $customTable
     */
    public function __construct($file, UserTable $userTable = null, CustomTable $customTable = null)
    {
        $this->_usersFile = $file;
        $this->_anyDataSet = new AnyDataset($this->_usersFile);
        $this->_userTable = $userTable;
        $this->_customTable = $customTable;
    }

    /**
     * Save the current UsersAnyDataset
     *
     * @param \ByJG\Authenticate\Model\UserModel $model
     */
    public function save(UserModel $model)
    {
        $values = BinderObject::toArrayFrom($model);
        $customProperties = $model->getCustomProperties();

        $iteratorFilter = new IteratorFilter();
        $iteratorFilter->addRelation($this->getUserTable()->id, Relation::EQUAL, $model->getUserid());
        $iterator = $this->_anyDataSet->getIterator($iteratorFilter);

        if ($iterator->hasNext()) {
            $oldRow = $iterator->moveNext();
            $this->_anyDataSet->removeRow($oldRow);
        }

        $row = new Row();
        foreach ($values as $key => $value) {
            $row->set($key, $value);
        }
        foreach ($customProperties as $value) {
            $row->addField($value->getName(), $value->getValue());
        }

        $this->_anyDataSet->appendRow($row);

        $this->_anyDataSet->save($this->_usersFile);
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
        
        $userId = $this->generateUserId();
        $fixedUsername = preg_replace('/(?:([\w])|([\W]))/', '\1', strtolower($userName)); 
        if (is_null($userId)) {
            $userId = $fixedUsername;
        }
        
        $this->_anyDataSet->appendRow();

        $this->_anyDataSet->addField($this->getUserTable()->id, $userId);
        $this->_anyDataSet->addField($this->getUserTable()->username, $fixedUsername);
        $this->_anyDataSet->addField($this->getUserTable()->name, $name);
        $this->_anyDataSet->addField($this->getUserTable()->email, strtolower($email));
        $this->_anyDataSet->addField($this->getUserTable()->password, $this->getPasswordHash($password));
        $this->_anyDataSet->addField($this->getUserTable()->admin, "");
        $this->_anyDataSet->addField($this->getUserTable()->created, date("Y-m-d H:i:s"));

        $this->_anyDataSet->save($this->_usersFile);

        return true;
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
        $it = $this->_anyDataSet->getIterator($filter);
        if (!$it->hasNext()) {
            return null;
        }

        return $this->createUserModel($it->moveNext());
    }

    /**
     * Get the user based on his login.
     * Return Row if user was found; null, otherwise
     *
     * @param string $username
     * @return boolean
     * */
    public function removeUserName($username)
    {
        //anydataset.Row
        $iteratorFilter = new IteratorFilter();
        $iteratorFilter->addRelation($this->getUserTable()->username, Relation::EQUAL, $username);
        $iterator = $this->_anyDataSet->getIterator($iteratorFilter);

        if ($iterator->hasNext()) {
            $oldRow = $iterator->moveNext();
            $this->_anyDataSet->removeRow($oldRow);
            $this->_anyDataSet->save($this->_usersFile);
            return true;
        }

        return false;
    }

    /**
     * Get an Iterator based on a filter
     *
     * @param IteratorFilter $filter
     * @return IteratorInterface
     */
    public function getIterator($filter = null)
    {
        return $this->_anyDataSet->getIterator($filter);
    }

    /**
     *
     * @param int $userId
     * @param string $propertyName
     * @param string $value
     * @return boolean
     */
    public function addProperty($userId, $propertyName, $value)
    {
        //anydataset.Row
        $user = $this->getById($userId);
        if ($user !== null) {
            if (!$this->hasProperty($user->getUserid(), $propertyName, $value)) {
                $user->addCustomProperty(new CustomModel($propertyName, $value));
                $this->save($user);
            }
            return true;
        }

        return false;
    }

    /**
     *
     * @param int $userId
     * @param string $propertyName
     * @param string $value
     * @return boolean
     */
    public function removeProperty($userId, $propertyName, $value = null)
    {
        $user = $this->getById($userId);
        if (!empty($user)) {
            $properties = $user->getCustomProperties();
            foreach ($properties as $key => $custom) {
                if ($custom->getName() == $propertyName && (empty($value) || $custom->getValue() == $value)) {
                    unset($properties[$key]);
                }
            }
            $user->setCustomProperties($properties);
            $this->save($user);
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
     * @return bool|void
     */
    public function removeAllProperties($propertyName, $value)
    {
        $it = $this->getIterator(null);
        while ($it->hasNext()) {
            //anydataset.Row
            $user = $it->moveNext();
            $this->removeProperty($user->get($this->getUserTable()->username), $propertyName, $value);
        }
    }

    private function createUserModel(Row $row)
    {
        $allProp = $row->toArray();
        $userModel = new UserModel();
        BinderObject::bindObject($allProp, $userModel);

        $modelProperties = BinderObject::toArrayFrom($userModel);
        foreach ($row->getFieldNames() as $property) {
            if (!isset($modelProperties[$property])) {
                foreach ($row->getAsArray($property) as $value) {
                    $userModel->addCustomProperty(new CustomModel($property, $value));
                }
            }
        }

        return $userModel;
    }
}
