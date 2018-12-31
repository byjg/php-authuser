<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Core\AnyDataset;
use ByJG\AnyDataset\Core\Enum\Relation;
use ByJG\AnyDataset\Core\IteratorFilter;
use ByJG\AnyDataset\Core\IteratorInterface;
use ByJG\AnyDataset\Core\Row;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;

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
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     * @throws \ByJG\Util\Exception\XmlUtilException
     */
    public function __construct(
        $file,
        UserDefinition $userTable = null,
        UserPropertiesDefinition $propertiesTable = null
    ) {
        $this->usersFile = $file;
        $this->anyDataSet = new AnyDataset($this->usersFile);
        $this->userTable = $userTable;
        if (!$userTable->existsClosure('update', 'userid')) {
            $userTable->defineClosureForUpdate('userid', function ($value, $instance) {
                if (empty($value)) {
                    return preg_replace('/(?:([\w])|([\W]))/', '\1', strtolower($instance->getUsername()));
                }
                return $value;
            });
        }
        $this->propertiesTable = $propertiesTable;
    }

    /**
     * Save the current UsersAnyDataset
     *
     * @param \ByJG\Authenticate\Model\UserModel $model
     * @throws \ByJG\AnyDataset\Core\Exception\DatabaseException
     * @throws \ByJG\Authenticate\Exception\UserExistsException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     * @throws \ByJG\Util\Exception\XmlUtilException
     */
    public function save(UserModel $model)
    {
        $new = true;
        if (!empty($model->getUserid())) {
            $new = !$this->removeUserById($model->getUserid());
        }

        $new && $this->canAddUser($model);

        $this->anyDataSet->appendRow();

        $propertyDefinition = $this->getUserDefinition()->toArray();
        foreach ($propertyDefinition as $property => $map) {
            $closure = $this->getUserDefinition()->getClosureForUpdate($property);
            $value = $closure($model->{"get$property"}(), $model);
            if ($value !== false) {
                $this->anyDataSet->addField($map, $value);
            }
        }

        $properties = $model->getProperties();
        foreach ($properties as $value) {
            $this->anyDataSet->addField($value->getName(), $value->getValue());
        }

        $this->anyDataSet->save($this->usersFile);
    }

    /**
     * Get the user based on a filter.
     * Return Row if user was found; null, otherwise
     *
     * @param IteratorFilter $filter Filter to find user
     * @return UserModel
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
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
     * @throws \ByJG\AnyDataset\Core\Exception\DatabaseException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     * @throws \ByJG\Util\Exception\XmlUtilException
     */
    public function removeByLoginField($login)
    {
        //anydataset.Row
        $iteratorFilter = new IteratorFilter();
        $iteratorFilter->addRelation($this->getUserDefinition()->loginField(), Relation::EQUAL, $login);
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
     * @throws \ByJG\AnyDataset\Core\Exception\DatabaseException
     * @throws \ByJG\Authenticate\Exception\UserExistsException
     * @throws \ByJG\Authenticate\Exception\UserNotFoundException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     * @throws \ByJG\Util\Exception\XmlUtilException
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
     * @throws \ByJG\AnyDataset\Core\Exception\DatabaseException
     * @throws \ByJG\Authenticate\Exception\UserExistsException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     * @throws \ByJG\Util\Exception\XmlUtilException
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
     * @throws \ByJG\AnyDataset\Core\Exception\DatabaseException
     * @throws \ByJG\Authenticate\Exception\UserExistsException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     * @throws \ByJG\Util\Exception\XmlUtilException
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
     * @param \ByJG\AnyDataset\Core\Row $row
     * @return \ByJG\Authenticate\Model\UserModel
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    private function createUserModel(Row $row)
    {
        $allProp = $row->toArray();
        $userModel = new UserModel();

        $userTableProp = $this->getUserDefinition()->toArray();
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

    /**
     * @param $userid
     * @return bool
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function removeUserById($userid)
    {
        $iteratorFilter = new IteratorFilter();
        $iteratorFilter->addRelation($this->getUserDefinition()->getUserid(), Relation::EQUAL, $userid);
        $iterator = $this->anyDataSet->getIterator($iteratorFilter);

        if ($iterator->hasNext()) {
            $oldRow = $iterator->moveNext();
            $this->anyDataSet->removeRow($oldRow);
            return true;
        }

        return false;
    }
}
