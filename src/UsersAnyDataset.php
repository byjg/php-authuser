<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Dataset\AnyDataset;
use ByJG\AnyDataset\Dataset\IteratorFilter;
use ByJG\AnyDataset\Enum\Relation;
use ByJG\AnyDataset\IteratorInterface;
use ByJG\AnyDataset\Dataset\Row;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Exception\UserExistsException;
use ByJG\Authenticate\Model\UserPropertiesModel;
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
     *
     * @param string $file
     * @param UserDefinition $userTable
     * @param UserPropertiesDefinition $propertiesTable
     */
    public function __construct($file, UserDefinition $userTable = null, UserPropertiesDefinition $propertiesTable = null)
    {
        $this->_usersFile = $file;
        $this->_anyDataSet = new AnyDataset($this->_usersFile);
        $this->_userTable = $userTable;
        $this->_propertiesTable = $propertiesTable;
    }

    /**
     * Save the current UsersAnyDataset
     *
     * @param \ByJG\Authenticate\Model\UserModel $model
     */
    public function save(UserModel $model)
    {
        $values = BinderObject::toArrayFrom($model);
        $properties = $model->getProperties();

        $iteratorFilter = new IteratorFilter();
        $iteratorFilter->addRelation($this->getUserDefinition()->getUserid(), Relation::EQUAL, $model->getUserid());
        $iterator = $this->_anyDataSet->getIterator($iteratorFilter);

        if ($iterator->hasNext()) {
            $oldRow = $iterator->moveNext();
            $this->_anyDataSet->removeRow($oldRow);
        }

        $userTableProp = BinderObject::toArrayFrom($this->getUserDefinition());
        $row = new Row();
        foreach ($values as $key => $value) {
            $row->set($userTableProp[$key], $value);
        }
        foreach ($properties as $value) {
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
        if ($this->getByLoginField($userName) !== null) {
            throw new UserExistsException('Login already exists');
        }
        
        $userId = $this->generateUserId();
        $fixedUsername = preg_replace('/(?:([\w])|([\W]))/', '\1', strtolower($userName)); 
        if (is_null($userId)) {
            $userId = $fixedUsername;
        }
        
        $this->_anyDataSet->appendRow();

        $passwordGenerator = $this->getUserDefinition()->getClosureForUpdate('password');
        $this->_anyDataSet->addField($this->getUserDefinition()->getUserid(), $userId);
        $this->_anyDataSet->addField($this->getUserDefinition()->getUsername(), $fixedUsername);
        $this->_anyDataSet->addField($this->getUserDefinition()->getName(), $name);
        $this->_anyDataSet->addField($this->getUserDefinition()->getEmail(), strtolower($email));
        $this->_anyDataSet->addField($this->getUserDefinition()->getPassword(), $passwordGenerator($password, null));
        $this->_anyDataSet->addField($this->getUserDefinition()->getAdmin(), "");
        $this->_anyDataSet->addField($this->getUserDefinition()->getCreated(), date("Y-m-d H:i:s"));

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
    public function removeByLoginField($username)
    {
        //anydataset.Row
        $iteratorFilter = new IteratorFilter();
        $iteratorFilter->addRelation($this->getUserDefinition()->getLoginField(), Relation::EQUAL, $username);
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
                $user->addProperty(new UserPropertiesModel($propertyName, $value));
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
            $properties = $user->getProperties();
            foreach ($properties as $key => $property) {
                if ($property->getName() == $propertyName && (empty($value) || $property->getValue() == $value)) {
                    unset($properties[$key]);
                }
            }
            $user->setProperties($properties);
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
    public function removeAllProperties($propertyName, $value = null)
    {
        $it = $this->getIterator(null);
        while ($it->hasNext()) {
            //anydataset.Row
            $user = $it->moveNext();
            $this->removeProperty($user->get($this->getUserDefinition()->getLoginField()), $propertyName, $value);
        }
    }

    private function createUserModel(Row $row)
    {
        $allProp = $row->toArray();
        $userModel = new UserModel();

        $userTableProp = BinderObject::toArrayFrom($this->getUserDefinition());
        foreach ($userTableProp as $prop => $mapped) {
            if (isset($allProp[$mapped])) {
                $userModel->{"set" . ucfirst($prop)}($allProp[$mapped]);
                unset($allProp[$mapped]);
            }
        }

        foreach ($allProp as $property => $value) {
            foreach ($row->getAsArray($property) as $eachValue) {
                $userModel->addProperty(new UserPropertiesModel($property, $eachValue));
            }
        }

        return $userModel;
    }
}
