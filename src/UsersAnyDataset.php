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
    protected $anyDataSet;

    /**
     * Internal Users file name
     *
     * @var string
     */
    protected $usersFile;

    /**
     * AnyDataset constructor
     *
     * @param string $file
     * @param UserDefinition $userTable
     * @param UserPropertiesDefinition $propertiesTable
     */
    public function __construct(
        $file,
        UserDefinition $userTable = null,
        UserPropertiesDefinition $propertiesTable = null
    ) {
        $this->usersFile = $file;
        $this->anyDataSet = new AnyDataset($this->usersFile);
        $this->userTable = $userTable;
        $this->propertiesTable = $propertiesTable;
    }

    /**
     * Save the current UsersAnyDataset
     *
     * @param \ByJG\Authenticate\Model\UserModel $model
     * @throws \ByJG\AnyDataset\Exception\DatabaseException
     * @throws \Exception
     */
    public function save(UserModel $model)
    {
        $values = BinderObject::toArrayFrom($model);
        $properties = $model->getProperties();

        $iteratorFilter = new IteratorFilter();
        $iteratorFilter->addRelation($this->getUserDefinition()->getUserid(), Relation::EQUAL, $model->getUserid());
        $iterator = $this->anyDataSet->getIterator($iteratorFilter);

        if ($iterator->hasNext()) {
            $oldRow = $iterator->moveNext();
            $this->anyDataSet->removeRow($oldRow);
        }

        $userTableProp = BinderObject::toArrayFrom($this->getUserDefinition());
        $row = new Row();
        foreach ($values as $key => $value) {
            $row->set($userTableProp[$key], $value);
        }
        foreach ($properties as $value) {
            $row->addField($value->getName(), $value->getValue());
        }

        $this->anyDataSet->appendRow($row);

        $this->anyDataSet->save($this->usersFile);
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
     * @throws \ByJG\AnyDataset\Exception\DatabaseException
     * @throws \Exception
     */
    public function addUser($name, $userName, $email, $password)
    {
        if ($this->getByEmail($email) !== null) {
            throw new UserExistsException('Email already exists');
        }
        $filter = new IteratorFilter();
        $filter->addRelation($this->getUserDefinition()->getUsername(), Relation::EQUAL, $userName);
        if ($this->getUser($filter) !== null) {
            throw new UserExistsException('Username already exists');
        }
        
        $userId = $this->generateUserId();
        $fixedUsername = preg_replace('/(?:([\w])|([\W]))/', '\1', strtolower($userName));
        if (is_null($userId)) {
            $userId = $fixedUsername;
        }
        
        $this->anyDataSet->appendRow();

        $passwordGenerator = $this->getUserDefinition()->getClosureForUpdate('password');
        $this->anyDataSet->addField($this->getUserDefinition()->getUserid(), $userId);
        $this->anyDataSet->addField($this->getUserDefinition()->getUsername(), $fixedUsername);
        $this->anyDataSet->addField($this->getUserDefinition()->getName(), $name);
        $this->anyDataSet->addField($this->getUserDefinition()->getEmail(), strtolower($email));
        $this->anyDataSet->addField($this->getUserDefinition()->getPassword(), $passwordGenerator($password, null));
        $this->anyDataSet->addField($this->getUserDefinition()->getAdmin(), "");
        $this->anyDataSet->addField($this->getUserDefinition()->getCreated(), date("Y-m-d H:i:s"));

        $this->anyDataSet->save($this->usersFile);

        return true;
    }

    /**
     * Get the user based on a filter.
     * Return Row if user was found; null, otherwise
     *
     * @param IteratorFilter $filter Filter to find user
     * @return UserModel
     * @throws \Exception
     */
    public function getUser($filter)
    {
        $iterator = $this->anyDataSet->getIterator($filter);
        if (!$iterator->hasNext()) {
            return null;
        }

        return $this->createUserModel($iterator->moveNext());
    }

    /**
     * Get the user based on his login.
     * Return Row if user was found; null, otherwise
     *
     * @param string $login
     * @return boolean
     * @throws \ByJG\AnyDataset\Exception\DatabaseException
     */
    public function removeByLoginField($login)
    {
        //anydataset.Row
        $iteratorFilter = new IteratorFilter();
        $iteratorFilter->addRelation($this->getUserDefinition()->getLoginField(), Relation::EQUAL, $login);
        $iterator = $this->anyDataSet->getIterator($iteratorFilter);

        if ($iterator->hasNext()) {
            $oldRow = $iterator->moveNext();
            $this->anyDataSet->removeRow($oldRow);
            $this->anyDataSet->save($this->usersFile);
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
        return $this->anyDataSet->getIterator($filter);
    }

    /**
     * @param int $userId
     * @param string $propertyName
     * @param string $value
     * @return boolean
     * @throws \ByJG\AnyDataset\Exception\DatabaseException
     * @throws \ByJG\Authenticate\Exception\UserNotFoundException
     * @throws \Exception
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
     * @param int $userId
     * @param string $propertyName
     * @param string $value
     * @return boolean
     * @throws \ByJG\AnyDataset\Exception\DatabaseException
     * @throws \Exception
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
     * @throws \ByJG\AnyDataset\Exception\DatabaseException
     * @throws \Exception
     */
    public function removeAllProperties($propertyName, $value = null)
    {
        $iterator = $this->getIterator(null);
        while ($iterator->hasNext()) {
            //anydataset.Row
            $user = $iterator->moveNext();
            $this->removeProperty($user->get($this->getUserDefinition()->getUserid()), $propertyName, $value);
        }
    }

    /**
     * @param \ByJG\AnyDataset\Dataset\Row $row
     * @return \ByJG\Authenticate\Model\UserModel
     * @throws \Exception
     */
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

        foreach (array_keys($allProp) as $property) {
            foreach ($row->getAsArray($property) as $eachValue) {
                $userModel->addProperty(new UserPropertiesModel($property, $eachValue));
            }
        }

        return $userModel;
    }
}
