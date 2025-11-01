<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Core\AnyDataset;
use ByJG\AnyDataset\Core\Enum\Relation;
use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Core\IteratorFilter;
use ByJG\AnyDataset\Core\IteratorInterface;
use ByJG\AnyDataset\Core\RowInterface;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Exception\UserExistsException;
use ByJG\Authenticate\Exception\UserNotFoundException;
use ByJG\Authenticate\MapperFunctions\UserIdGeneratorMapper;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\Serializer\Exception\InvalidArgumentException;
use ByJG\XmlUtil\Exception\FileException;
use Override;

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
        UserDefinition|null $userTable = null,
        UserPropertiesDefinition|null $propertiesTable = null
    ) {
        $this->anyDataSet = $anyDataset;
        $this->anyDataSet->save();
        $this->userTable = $userTable;
        if (!$userTable->existsMapper('update', UserDefinition::FIELD_USERID)) {
            $userTable->defineMapperForUpdate(UserDefinition::FIELD_USERID, UserIdGeneratorMapper::class);
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
    #[Override]
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
            $mapper = $this->getUserDefinition()->getMapperForUpdate($property);
            if (is_string($mapper)) {
                $mapper = new $mapper();
            }
            $value = $mapper->processedValue($model->{"get$property"}(), $model);
            if ($value !== false) {
                $this->anyDataSet->addField($map, $value);
            }
        }

        // Group properties by name to handle multiple values
        $propertiesByName = [];
        foreach ($model->getProperties() as $property) {
            $name = $property->getName();
            if (!isset($propertiesByName[$name])) {
                $propertiesByName[$name] = [];
            }
            $propertiesByName[$name][] = $property->getValue();
        }

        // Add properties, using array if multiple values exist
        foreach ($propertiesByName as $name => $values) {
            if (count($values) === 1) {
                $this->anyDataSet->addField($name, $values[0]);
            } else {
                $this->anyDataSet->addField($name, $values);
            }
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
     */
    #[Override]
    public function getUser(IteratorFilter $filter): UserModel|null
    {
        $iterator = $this->anyDataSet->getIterator($filter);
        if (!$iterator->valid()) {
            return null;
        }

        return $this->createUserModel($iterator->current());
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
    #[Override]
    public function removeByLoginField(string $login): bool
    {
        //anydataset.Row
        $iteratorFilter = new IteratorFilter();
        $iteratorFilter->and($this->getUserDefinition()->loginField(), Relation::EQUAL, $login);
        $iterator = $this->anyDataSet->getIterator($iteratorFilter);

        if ($iterator->valid()) {
            $oldRow = $iterator->current();
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
    public function getIterator(IteratorFilter|null $filter = null): IteratorInterface
    {
        return $this->anyDataSet->getIterator($filter);
    }

    /**
     * @param string $propertyName
     * @param string $value
     * @return array
     */
    #[Override]
    public function getUsersByProperty(string $propertyName, string $value): array
    {
        return $this->getUsersByPropertySet([$propertyName => $value]);
    }

    /**
     * @param array $propertiesArray
     * @return array
     */
    #[Override]
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
    #[Override]
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
    #[Override]
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
    #[Override]
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
    #[Override]
    public function removeAllProperties(string $propertyName, string|null $value = null): void
    {
        $iterator = $this->getIterator();
        foreach ($iterator as $user) {
            //anydataset.Row
            $this->removeProperty($user->get($this->getUserDefinition()->getUserid()), $propertyName, $value);
        }
    }

    /**
     * @param RowInterface $row
     * @return UserModel
     */
    private function createUserModel(RowInterface $row): UserModel
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
            $values = $row->get($property);

            // Handle both single values and arrays
            if (!is_array($values)) {
                if ($values !== null) {
                    $userModel->addProperty(new UserPropertiesModel($property, $values));
                }
            } else {
                foreach ($values as $eachValue) {
                    $userModel->addProperty(new UserPropertiesModel($property, $eachValue));
                }
            }
        }

        return $userModel;
    }

    /**
     * @param string|HexUuidLiteral|int $userid
     * @return bool
     * @throws InvalidArgumentException
     */
    #[Override]
    public function removeUserById(string|HexUuidLiteral|int $userid): bool
    {
        $iteratorFilter = new IteratorFilter();
        $iteratorFilter->and($this->getUserDefinition()->getUserid(), Relation::EQUAL, $userid);
        $iterator = $this->anyDataSet->getIterator($iteratorFilter);

        if ($iterator->valid()) {
            $oldRow = $iterator->current();
            $this->anyDataSet->removeRow($oldRow);
            return true;
        }

        return false;
    }
}
