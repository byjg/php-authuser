<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Core\AnyDataset;
use ByJG\AnyDataset\Core\Enum\Relation;
use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Core\IteratorFilter;
use ByJG\AnyDataset\Core\IteratorInterface;
use ByJG\AnyDataset\Core\Row;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Exception\UserExistsException;
use ByJG\Authenticate\Exception\UserNotFoundException;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\Serializer\Exception\InvalidArgumentException;
use ByJG\XmlUtil\Exception\FileException;

class UsersAnyDataset extends UsersBase
{

    /**
     * Internal AnyDataset structure to store the Users
     * @var AnyDataset
     */
    protected AnyDataset $anyDataSet;

    /**
     * AnyDataset constructor
     *
     * @param AnyDataset $anyDataset
     * @param UserDefinition|null $userTable
     * @param UserPropertiesDefinition|null $propertiesTable
     * @throws DatabaseException
     * @throws FileException
     */
    public function __construct(
        AnyDataset $anyDataset,
        UserDefinition $userTable = null,
        UserPropertiesDefinition $propertiesTable = null
    ) {
        $this->anyDataSet = $anyDataset;
        $this->anyDataSet->save();
        $this->userTable = $userTable;
        if (!$userTable->existsClosure('update', UserDefinition::FIELD_USERID)) {
            $userTable->defineClosureForUpdate(UserDefinition::FIELD_USERID, function ($value, $instance) {
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
     * @param UserModel $model
     * @return UserModel
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws UserExistsException
     * @throws FileException
     */
    public function save(UserModel $model): UserModel
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

        $this->anyDataSet->save();

        return $model;
    }

    /**
     * Get the user based on a filter.
     * Return Row if user was found; null, otherwise
     *
     * @param IteratorFilter $filter Filter to find user
     * @return UserModel|null
     * @throws InvalidArgumentException
     */
    public function getUser(IteratorFilter $filter): UserModel|null
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
     * @throws DatabaseException
     * @throws FileException
     * @throws InvalidArgumentException
     */
    public function removeByLoginField(string $login): bool
    {
        //anydataset.Row
        $iteratorFilter = new IteratorFilter();
        $iteratorFilter->and($this->getUserDefinition()->loginField(), Relation::EQUAL, $login);
        $iterator = $this->anyDataSet->getIterator($iteratorFilter);

        if ($iterator->hasNext()) {
            $oldRow = $iterator->moveNext();
            $this->anyDataSet->removeRow($oldRow);
            $this->anyDataSet->save();
            return true;
        }

        return false;
    }

    /**
     * Get an Iterator based on a filter
     *
     * @param IteratorFilter|null $filter
     * @return IteratorInterface
     */
    public function getIterator(IteratorFilter $filter = null): IteratorInterface
    {
        return $this->anyDataSet->getIterator($filter);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getUsersByProperty(string $propertyName, string $value): array
    {
        return $this->getUsersByPropertySet([$propertyName => $value]);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getUsersByPropertySet(array $propertiesArray): array
    {
        $filter = new IteratorFilter();
        foreach ($propertiesArray as $propertyName => $value) {
            $filter->and($propertyName, Relation::EQUAL, $value);
        }
        $result = [];
        foreach ($this->getIterator($filter) as $model) {
            $result[] = $this->createUserModel($model);
        }
        return $result;
    }

    /**
     * @param string|int|HexUuidLiteral $userId
     * @param string $propertyName
     * @param string|null $value
     * @return boolean
     * @throws DatabaseException
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws UserExistsException
     * @throws UserNotFoundException
     */
    public function addProperty(string|HexUuidLiteral|int $userId, string $propertyName, string|null $value): bool
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
     * @throws InvalidArgumentException
     * @throws DatabaseException
     * @throws UserExistsException
     * @throws FileException
     */
    public function setProperty(string|HexUuidLiteral|int $userId, string $propertyName, string|null $value): bool
    {
        $user = $this->getById($userId);
        if ($user !== null) {
            $user->set($propertyName, $value);
            $this->save($user);
            return true;
        }
        return false;
    }

    /**
     * @param string|int|HexUuidLiteral $userId
     * @param string $propertyName
     * @param string|null $value
     * @return boolean
     * @throws DatabaseException
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws UserExistsException
     */
    public function removeProperty(string|HexUuidLiteral|int $userId, string $propertyName, string|null $value = null): bool
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
     * @param string|null $value Property value with a site
     * @return void
     * @throws DatabaseException
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws UserExistsException
     */
    public function removeAllProperties(string $propertyName, string|null $value = null): void
    {
        $iterator = $this->getIterator(null);
        while ($iterator->hasNext()) {
            //anydataset.Row
            $user = $iterator->moveNext();
            $this->removeProperty($user->get($this->getUserDefinition()->getUserid()), $propertyName, $value);
        }
    }

    /**
     * @param Row $row
     * @return UserModel
     * @throws InvalidArgumentException
     */
    private function createUserModel(Row $row): UserModel
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
     * @param string|HexUuidLiteral|int $userid
     * @return bool
     * @throws InvalidArgumentException
     */
    public function removeUserById(string|HexUuidLiteral|int $userid): bool
    {
        $iteratorFilter = new IteratorFilter();
        $iteratorFilter->and($this->getUserDefinition()->getUserid(), Relation::EQUAL, $userid);
        $iterator = $this->anyDataSet->getIterator($iteratorFilter);

        if ($iterator->hasNext()) {
            $oldRow = $iterator->moveNext();
            $this->anyDataSet->removeRow($oldRow);
            return true;
        }

        return false;
    }
}
