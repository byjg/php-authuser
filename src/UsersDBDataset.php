<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Core\IteratorFilter;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\DbDriverInterface;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\IteratorFilterSqlFormatter;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Exception\UserExistsException;
use ByJG\Authenticate\Exception\UserNotFoundException;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\MicroOrm\DeleteQuery;
use ByJG\MicroOrm\Exception\InvalidArgumentException as ExceptionInvalidArgumentException;
use ByJG\MicroOrm\Exception\OrmBeforeInvalidException;
use ByJG\MicroOrm\Exception\OrmInvalidFieldsException;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\MicroOrm\Exception\RepositoryReadOnlyException;
use ByJG\MicroOrm\Exception\UpdateConstraintException;
use ByJG\MicroOrm\FieldMapping;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\MicroOrm\Mapper;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Repository;
use ByJG\Serializer\Exception\InvalidArgumentException;
use ByJG\XmlUtil\Exception\FileException;
use ByJG\XmlUtil\Exception\XmlUtilException;
use Exception;
use Override;
use ReflectionException;

class UsersDBDataset extends UsersBase
{

    /**
     * @var Repository
     */
    protected Repository $userRepository;

    /**
     * @var Repository
     */
    protected Repository $propertiesRepository;

    /**
     * @var DatabaseExecutor
     */
    protected DatabaseExecutor $executor;

    /**
     * UsersDBDataset constructor
     *
     * @param DbDriverInterface|DatabaseExecutor $dbDriver
     * @param UserDefinition|null $userTable
     * @param UserPropertiesDefinition|null $propertiesTable
     *
     * @throws OrmModelInvalidException
     * @throws ReflectionException
     * @throws ExceptionInvalidArgumentException
     * @throws ExceptionInvalidArgumentException
     */
    public function __construct(
        DatabaseExecutor|DbDriverInterface $dbDriver,
        UserDefinition|null $userTable = null,
        UserPropertiesDefinition|null $propertiesTable = null
    ) {
        // Convert DbDriverInterface to DatabaseExecutor if needed
        if ($dbDriver instanceof DbDriverInterface && !($dbDriver instanceof DatabaseExecutor)) {
            $dbDriver = new DatabaseExecutor($dbDriver);
        }
        $this->executor = $dbDriver;

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
        $seed = $userTable->getGenerateKey();
        if (!empty($seed)) {
            $userMapper->withPrimaryKeySeedFunction($seed);
        }

        $propertyDefinition = $userTable->toArray();

        foreach ($propertyDefinition as $property => $map) {
            $userMapper->addFieldMapping(FieldMapping::create($property)
                ->withFieldName($map)
                ->withUpdateFunction($userTable->getMapperForUpdate($property))
                ->withSelectFunction($userTable->getMapperForSelect($property))
            );
        }
        $this->userRepository = new Repository($this->executor, $userMapper);

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
            ->withUpdateFunction($userTable->getMapperForUpdate(UserDefinition::FIELD_USERID))
            ->withSelectFunction($userTable->getMapperForSelect(UserDefinition::FIELD_USERID))
        );
        $this->propertiesRepository = new Repository($this->executor, $propertiesMapper);

        $this->userTable = $userTable;
        $this->propertiesTable = $propertiesTable;
    }

    /**
     * Save the current UsersAnyDataset
     *
     * @param UserModel $model
     * @return UserModel
     * @throws UserExistsException
     * @throws UserNotFoundException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws Exception
     */
    #[Override]
    public function save(UserModel $model): UserModel
    {
        $newUser = false;
        if (empty($model->getUserid())) {
            $this->canAddUser($model);
            $newUser = true;
        }

        $this->userRepository->setBeforeUpdate($this->userTable->getBeforeUpdate());
        $this->userRepository->setBeforeInsert($this->userTable->getBeforeInsert());
        $this->userRepository->save($model);

        foreach ($model->getProperties() as $property) {
            $property->setUserid($model->getUserid());
            $this->propertiesRepository->save($property);
        }

        if ($newUser) {
            $model = $this->get($model->getUserid());
        }

        if ($model === null) {
            throw new UserNotFoundException("User not found");
        }

        return $model;
    }

    #[\Override]
    public function get(string|HexUuidLiteral|int $value, ?string $field = null): ?UserModel
    {
        if (empty($field)) {
            $field = $this->getUserDefinition()->getUserid();
        }

        $function = match ($field) {
            $this->getUserDefinition()->getEmail() => $this->getUserDefinition()->getMapperForUpdate(UserDefinition::FIELD_EMAIL),
            $this->getUserDefinition()->getUsername() => $this->getUserDefinition()->getMapperForUpdate(UserDefinition::FIELD_USERNAME),
            default => $this->getUserDefinition()->getMapperForUpdate(UserDefinition::FIELD_USERID),
        };

        if (!empty($function)) {
            if (is_string($function)) {
                $function = new $function();
            }
            $value = $function->processedValue($value, null);
        }

        return parent::get($value, $field);
    }

    /**
     * Get the users database information based on a filter.
     *
     * @param IteratorFilter|null $filter Filter to find user
     * @return UserModel[]
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getIterator(IteratorFilter|null $filter = null): array
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
     * @return UserModel|null
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws RepositoryReadOnlyException
     * @throws XmlUtilException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    #[Override]
    public function getUser(IteratorFilter $filter): UserModel|null
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
     * @throws Exception
     */
    #[Override]
    public function removeByLoginField(string $login): bool
    {
        $user = $this->get($login, $this->getUserDefinition()->loginField());

        if ($user !== null) {
            return $this->removeUserById($user->getUserid());
        }

        return false;
    }

    /**
     * Remove the user based on his user id.
     *
     * @param string|HexUuidLiteral|int $userid
     * @return bool
     * @throws Exception
     */
    #[Override]
    public function removeUserById(string|HexUuidLiteral|int $userid): bool
    {
        $updateTableProperties = DeleteQuery::getInstance()
            ->table($this->getUserPropertiesDefinition()->table())
            ->where(
                "{$this->getUserPropertiesDefinition()->getUserid()} = :id",
                [
                    "id" => $userid
                ]
            );
        $this->propertiesRepository->deleteByQuery($updateTableProperties);

        $this->userRepository->delete($userid);

        return true;
    }

    /**
     * Get the user based on his property.
     *
     * @param string $propertyName
     * @param string $value
     * @return array
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws ExceptionInvalidArgumentException
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws XmlUtilException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    #[Override]
    public function getUsersByProperty(string $propertyName, string $value): array
    {
        return $this->getUsersByPropertySet([$propertyName => $value]);
    }

    /**
     * Get the user based on his property and value. e.g. [ 'key' => 'value', 'key2' => 'value2' ].
     *
     * @param array $propertiesArray
     * @return array
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws ExceptionInvalidArgumentException
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws XmlUtilException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    #[Override]
    public function getUsersByPropertySet(array $propertiesArray): array
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
     * @param string|int|HexUuidLiteral $userId
     * @param string $propertyName
     * @param string|null $value
     * @return bool
     * @throws UserNotFoundException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws Exception
     */
    #[Override]
    public function addProperty(string|HexUuidLiteral|int $userId, string $propertyName, string|null $value): bool
    {
        //anydataset.Row
        $user = $this->get($userId);
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
     * @param string|HexUuidLiteral|int $userId
     * @param string $propertyName
     * @param string|null $value
     * @return bool
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws ExceptionInvalidArgumentException
     * @throws FileException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws UpdateConstraintException
     * @throws XmlUtilException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    #[Override]
    public function setProperty(string|HexUuidLiteral|int $userId, string $propertyName, string|null $value): bool
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

        return true;
    }

    /**
     * Remove a specific site from user
     * Return True or false
     *
     * @param string|int|HexUuidLiteral $userId User Id
     * @param string $propertyName Property name
     * @param string|null $value Property value with a site
     * @return bool
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws ExceptionInvalidArgumentException
     * @throws InvalidArgumentException
     * @throws RepositoryReadOnlyException
     */
    #[Override]
    public function removeProperty(string|HexUuidLiteral|int $userId, string $propertyName, string|null $value = null): bool
    {
        $user = $this->get($userId);
        if ($user !== null) {

            $updateable = DeleteQuery::getInstance()
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
     * @param string|null $value Property value with a site
     * @return void
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws ExceptionInvalidArgumentException
     * @throws RepositoryReadOnlyException
     */
    #[Override]
    public function removeAllProperties(string $propertyName, string|null $value = null): void
    {
        $updateable = DeleteQuery::getInstance()
            ->table($this->getUserPropertiesDefinition()->table())
            ->where("{$this->getUserPropertiesDefinition()->getName()} = :name", ["name" => $propertyName]);

        if (!empty($value)) {
            $updateable->where("{$this->getUserPropertiesDefinition()->getValue()} = :value", ["value" => $value]);
        }

        $this->propertiesRepository->deleteByQuery($updateable);
    }

    /**
     * @throws XmlUtilException
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    #[Override]
    public function getProperty(string|HexUuidLiteral|int $userId, string $propertyName): array|string|UserPropertiesModel|null
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
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws RepositoryReadOnlyException
     * @throws XmlUtilException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function setPropertiesInUser(UserModel $userRow): void
    {
        $value = $this->propertiesRepository
            ->getMapper()
            ->getFieldMap(UserDefinition::FIELD_USERID)
            ->getUpdateFunctionValue($userRow->getUserid(), $userRow, $this->propertiesRepository->getExecutorWrite());
        $query = Query::getInstance()
            ->table($this->getUserPropertiesDefinition()->table())
            ->where("{$this->getUserPropertiesDefinition()->getUserid()} = :id", ['id' => $value]);
        $userRow->setProperties($this->propertiesRepository->getByQuery($query));
    }
}
