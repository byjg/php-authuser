<?php

namespace ByJG\Authenticate\Service;

use ByJG\Authenticate\Definition\PasswordDefinition;
use ByJG\Authenticate\Enum\LoginField;
use ByJG\Authenticate\Enum\User;
use ByJG\Authenticate\Enum\UserProperty;
use ByJG\Authenticate\Exception\NotAuthenticatedException;
use ByJG\Authenticate\Exception\UserExistsException;
use ByJG\Authenticate\Exception\UserNotFoundException;
use ByJG\Authenticate\Interfaces\PasswordMapperInterface;
use ByJG\Authenticate\Interfaces\UsersServiceInterface;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\Authenticate\Repository\UserPropertiesRepository;
use ByJG\Authenticate\Repository\UsersRepository;
use ByJG\JwtWrapper\JwtWrapper;
use ByJG\JwtWrapper\JwtWrapperException;
use ByJG\MicroOrm\Literal\Literal;
use ByJG\MicroOrm\Query;
use Exception;
use InvalidArgumentException;

/**
 * Service for User operations - Business Logic Layer
 */
class UsersService implements UsersServiceInterface
{
    protected UsersRepository $usersRepository;
    protected UserPropertiesRepository $propertiesRepository;
    protected ?PasswordDefinition $passwordDefinition;
    protected LoginField $loginField;


    public function __construct(
        UsersRepository $usersRepository,
        UserPropertiesRepository $propertiesRepository,
        LoginField $loginField = LoginField::Username,
        ?PasswordDefinition $passwordDefinition = null
    ) {
        $this->usersRepository = $usersRepository;
        $this->propertiesRepository = $propertiesRepository;
        $this->passwordDefinition = $passwordDefinition;
        $this->loginField = $loginField;

        $userMapper = $usersRepository->getRepository()->getMapper();
        $userCheck = ($userMapper->getFieldMap(User::Userid->value) !== null) &&
            ($userMapper->getFieldMap(User::Name->value) !== null) &&
            ($userMapper->getFieldMap(User::Email->value) !== null) &&
            ($userMapper->getFieldMap(User::Username->value) !== null) &&
            ($userMapper->getFieldMap(User::Password->value) !== null) &&
            ($userMapper->getFieldMap(User::Role->value) !== null);

        if (!$userCheck) {
            throw new InvalidArgumentException('Invalid user repository field mappings');
        }

        // Validate password mapper implements PasswordMapperInterface (handles both string class name and instance)
        $passwordUpdateFunction = $userMapper->getFieldMap(User::Password->value)->getUpdateFunction();
        if (!is_subclass_of($passwordUpdateFunction, PasswordMapperInterface::class, true)) {
            throw new InvalidArgumentException('Password update function must implement PasswordMapperInterface');
        }

        $this->passwordDefinition?->setPasswordMapper($passwordUpdateFunction);

        $propertyMapper = $propertiesRepository->getRepository()->getMapper();
        $propertyCheck = ($propertyMapper->getFieldMap(UserProperty::Userid->value) !== null) &&
            ($propertyMapper->getFieldMap(UserProperty::Name->value) !== null) &&
            ($propertyMapper->getFieldMap(UserProperty::Value->value) !== null);

        if (!$propertyCheck) {
            throw new InvalidArgumentException('Invalid property repository field mappings');
        }
    }

    /**
     * Get the user's repository
     *
     * @return UsersRepository
     */
    public function getUsersRepository(): UsersRepository
    {
        return $this->usersRepository;
    }

    /**
     * Get the property repository
     *
     * @return UserPropertiesRepository
     */
    public function getPropertiesRepository(): UserPropertiesRepository
    {
        return $this->propertiesRepository;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function save(UserModel $model): UserModel
    {
        $newUser = false;
        if (empty($model->getUserid())) {
            $this->canAddUser($model);
            $newUser = true;
        }

        $this->usersRepository->save($model);

        // Save properties
        foreach ($model->getProperties() as $property) {
            $property->setUserid($model->getUserid());
            $this->propertiesRepository->save($property);
        }

        if ($newUser) {
            $model = $this->getById($model->getUserid());
        }

        if ($model === null) {
            throw new UserNotFoundException("User not found");
        }

        return $model;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function addUser(string $name, string $userName, string $email, string $password): UserModel
    {

        $mapper = $this->usersRepository->getMapper();
        $model = $mapper->getEntity([
            $mapper->getFieldMap(User::Name->value)->getFieldName() => $name,
            $mapper->getFieldMap(User::Email->value)->getFieldName() => $email,
            $mapper->getFieldMap(User::Username->value)->getFieldName() => $userName,
        ]);
        $this->applyPasswordDefinition($model);
        $model->setPassword($password);

        return $this->save($model);
    }

    /**
     * Check if user can be added (doesn't exist already)
     *
     * @param UserModel $model
     * @return bool
     * @throws UserExistsException
     */
    protected function canAddUser(UserModel $model): bool
    {
        if (!empty($model->getUserid()) && $this->getById($model->getUserid()) !== null) {
            throw new UserExistsException('User ID already exists');
        }

        if ($this->getByUsername($model->getUsername()) !== null) {
            throw new UserExistsException('Username already exists');
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getById(string|Literal|int $userid): ?UserModel
    {
        $user = $this->usersRepository->getById($userid);
        if ($user !== null) {
            $this->loadUserProperties($user);
            $this->applyPasswordDefinition($user);
        }
        return $user;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getByEmail(string $email): ?UserModel
    {
        $fieldMap = $this->usersRepository->getMapper()->getFieldMap(User::Email->value);
        $user = $this->usersRepository->getByField($fieldMap->getFieldName(), $email);
        if ($user !== null) {
            $this->loadUserProperties($user);
            $this->applyPasswordDefinition($user);
        }
        return $user;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getByUsername(string $username): ?UserModel
    {
        $fieldMap = $this->usersRepository->getMapper()->getFieldMap(User::Username->value);
        $user = $this->usersRepository->getByField($fieldMap->getFieldName(), $username);
        if ($user !== null) {
            $this->loadUserProperties($user);
            $this->applyPasswordDefinition($user);
        }
        return $user;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getByLogin(string $login): ?UserModel
    {
        return $this->loginField === LoginField::Email
            ? $this->getByEmail($login)
            : $this->getByUsername($login);
    }

    /**
     * Load user properties into user model
     *
     * @param UserModel $user
     * @return void
     */
    protected function loadUserProperties(UserModel $user): void
    {
        $properties = $this->propertiesRepository->getByUserId($user->getUserid());
        $user->setProperties($properties);
    }

    protected function applyPasswordDefinition(UserModel $user): void
    {
        if ($this->passwordDefinition !== null) {
            $user->withPasswordDefinition($this->passwordDefinition);
        }
    }

    protected function resolveUserFieldValue(UserModel $user, string $fieldName): mixed
    {
        $camel = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ' , $fieldName)));
        $method = 'get' . $camel;
        if (method_exists($user, $method)) {
            return $user->$method();
        }

        $value = $user->get($fieldName);
        if ($value !== null) {
            return $value;
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function removeByLogin(string $login): bool
    {
        $user = $this->getByLogin($login);
        if ($user !== null) {
            return $this->removeById($user->getUserid());
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function removeById(string|Literal|int $userid): bool
    {
        try {
            // Delete properties first
            $this->propertiesRepository->deleteByUserId($userid);
            // Delete user
            $this->usersRepository->deleteById($userid);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function isValidUser(string $login, string $password): ?UserModel
    {
        $user = $this->getByLogin($login);

        if ($user === null) {
            return null;
        }

        // Hash the password for comparison using the model's configured password mapper
        $passwordFieldMapping = $this->usersRepository->getMapper()->getFieldMap(User::Password->value);
        $hashedPassword = $passwordFieldMapping->getUpdateFunctionValue($password, null);

        if ($user->getPassword() === $hashedPassword) {
            return $user;
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function hasProperty(string|int|Literal $userId, string $propertyName, ?string $value = null): bool
    {
        $user = $this->getById($userId);

        if (empty($user)) {
            return false;
        }

        $values = $user->get($propertyName);

        if ($values === null) {
            return false;
        }

        if ($value === null) {
            return true;
        }

        return in_array($value, (array)$values);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getProperty(string|Literal|int $userId, string $propertyName): array|string|UserPropertiesModel|null
    {
        $properties = $this->propertiesRepository->getByUserIdAndName($userId, $propertyName);

        if (count($properties) === 0) {
            return null;
        }

        $result = [];
        foreach ($properties as $property) {
            $result[] = $property->getValue();
        }

        if (count($result) === 1) {
            return $result[0];
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function addProperty(string|Literal|int $userId, string $propertyName, ?string $value): bool
    {
        $user = $this->getById($userId);
        if (empty($user)) {
            return false;
        }

        if (!$this->hasProperty($userId, $propertyName, $value)) {
            $propMapper = $this->propertiesRepository->getMapper();
            $property = $propMapper->getEntity([
                $propMapper->getFieldMap(UserProperty::Userid->value)->getFieldName() => $userId,
                $propMapper->getFieldMap(UserProperty::Name->value)->getFieldName() => $propertyName,
                $propMapper->getFieldMap(UserProperty::Value->value)->getFieldName() => $value
            ]);
            $this->propertiesRepository->save($property);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function setProperty(string|Literal|int $userId, string $propertyName, ?string $value): bool
    {
        $properties = $this->propertiesRepository->getByUserIdAndName($userId, $propertyName);

        if (empty($properties)) {
            $propMapper = $this->propertiesRepository->getMapper();
            $property = $propMapper->getEntity([
                $propMapper->getFieldMap(UserProperty::Userid->value)->getFieldName() => $userId,
                $propMapper->getFieldMap(UserProperty::Name->value)->getFieldName() => $propertyName,
                $propMapper->getFieldMap(UserProperty::Value->value)->getFieldName() => $value
            ]);
        } else {
            $property = $properties[0];
            $property->setValue($value);
        }

        $this->propertiesRepository->save($property);
        return true;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function removeProperty(string|Literal|int $userId, string $propertyName, ?string $value = null): bool
    {
        $user = $this->getById($userId);
        if ($user !== null) {
            $this->propertiesRepository->deleteByUserIdAndName($userId, $propertyName, $value);
            return true;
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function removeAllProperties(string $propertyName, ?string $value = null): void
    {
        $this->propertiesRepository->deleteByName($propertyName, $value);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getUsersByProperty(string $propertyName, string $value): array
    {
        return $this->getUsersByPropertySet([$propertyName => $value]);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getUsersByPropertySet(array $propertiesArray): array
    {
        $userTable = $this->usersRepository->getTableName();
        $propTable = $this->propertiesRepository->getTableName();
        $userPk = $this->usersRepository->getPrimaryKeyName();

        // Handle composite keys - use first key if array
        if (is_array($userPk)) {
            $userPk = $userPk[0];
        }

        $propUserIdField = $this->propertiesRepository->getMapper()->getFieldMap(UserProperty::Userid->value)->getFieldName();
        $propNameField = $this->propertiesRepository->getMapper()->getFieldMap(UserProperty::Name->value)->getFieldName();
        $propValueField = $this->propertiesRepository->getMapper()->getFieldMap(UserProperty::Value->value)->getFieldName();

        $query = Query::getInstance()
            ->field("u.*")
            ->table($userTable, "u");

        $count = 0;
        foreach ($propertiesArray as $propertyName => $value) {
            $count++;
            $query->join($propTable, "p$count.$propUserIdField = u.$userPk", "p$count")
                ->where("p$count.$propNameField = :name$count", ["name$count" => $propertyName])
                ->where("p$count.$propValueField = :value$count", ["value$count" => $value]);
        }

        $users = $this->usersRepository->getRepository()->getByQuery($query);
        if ($this->passwordDefinition !== null) {
            foreach ($users as $user) {
                $this->applyPasswordDefinition($user);
            }
        }
        return $users;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function createAuthToken(
        string     $login,
        string     $password,
        JwtWrapper $jwtWrapper,
        int        $expires = 1200,
        array      $updateUserInfo = [],
        array      $updateTokenInfo = [],
        array      $tokenUserFields = [User::Userid, User::Name, User::Role]
    ): ?string {
        $user = $this->isValidUser($login, $password);
        if (is_null($user)) {
            throw new UserNotFoundException('User not found');
        }

        foreach ($updateUserInfo as $key => $value) {
            $user->set($key, $value);
        }

        foreach ($tokenUserFields as $field) {
            $fieldName = $field instanceof User ? $field->value : $field;
            if (!is_string($fieldName) || $fieldName === '') {
                continue;
            }

            $value = $this->resolveUserFieldValue($user, $fieldName);
            if ($value !== null) {
                $updateTokenInfo[$fieldName] = $value;
            }
        }

        $jwtData = $jwtWrapper->createJwtData($updateTokenInfo, $expires);

        $token = $jwtWrapper->generateToken($jwtData);

        $user->set('TOKEN_HASH', sha1($token));
        $this->save($user);

        return $token;
    }

    /**
     * @inheritDoc
     * @throws JwtWrapperException
     * @throws NotAuthenticatedException
     * @throws UserNotFoundException
     */
    #[\Override]
    public function isValidToken(string $login, JwtWrapper $jwtWrapper, string $token): ?array
    {
        $user = $this->getByLogin($login);

        if (is_null($user)) {
            throw new UserNotFoundException('User not found!');
        }

        if ($user->get('TOKEN_HASH') !== sha1($token)) {
            throw new NotAuthenticatedException('Token does not match');
        }

        $data = $jwtWrapper->extractData($token);

        $this->save($user);

        return [
            'user' => $user,
            'data' => $data->data
        ];
    }

    #[\Override]
    public function getUsersEntity(array $fields): UserModel
    {
        $entity = $this->getUsersRepository()->getMapper()->getEntity($fields);
        $this->applyPasswordDefinition($entity);
        return $entity;
    }
}
