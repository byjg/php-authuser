<?php

namespace ByJG\Authenticate\Service;

use ByJG\Authenticate\Definition\PasswordDefinition;
use ByJG\Authenticate\Enum\LoginField;
use ByJG\Authenticate\Enum\UserField;
use ByJG\Authenticate\Enum\UserPropertyField;
use ByJG\Authenticate\Exception\NotAuthenticatedException;
use ByJG\Authenticate\Exception\UserExistsException;
use ByJG\Authenticate\Exception\UserNotFoundException;
use ByJG\Authenticate\Interfaces\PasswordMapperInterface;
use ByJG\Authenticate\Interfaces\UsersServiceInterface;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\Authenticate\Model\UserToken;
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
        $passwordFieldMapping = $userMapper->getFieldMap(UserField::Password->value);
        $userCheck = ($userMapper->getFieldMap(UserField::Userid->value) !== null) &&
            ($userMapper->getFieldMap(UserField::Name->value) !== null) &&
            ($userMapper->getFieldMap(UserField::Email->value) !== null) &&
            ($userMapper->getFieldMap(UserField::Username->value) !== null) &&
            ($passwordFieldMapping !== null) &&
            ($userMapper->getFieldMap(UserField::Role->value) !== null);

        if (!$userCheck) {
            throw new InvalidArgumentException('Invalid user repository field mappings');
        }

        // Validate password mapper implements PasswordMapperInterface (handles both string class name and instance)
        $passwordUpdateFunction = $passwordFieldMapping->getUpdateFunction();
        if (!is_subclass_of($passwordUpdateFunction, PasswordMapperInterface::class, true)) {
            throw new InvalidArgumentException('Password update function must implement PasswordMapperInterface');
        }

        // Type assertion for Psalm - we've verified it's a PasswordMapperInterface or string
        /** @var PasswordMapperInterface|string $passwordUpdateFunction */
        $this->passwordDefinition?->setPasswordMapper($passwordUpdateFunction);

        $propertyMapper = $propertiesRepository->getRepository()->getMapper();
        $propertyCheck = ($propertyMapper->getFieldMap(UserPropertyField::Userid->value) !== null) &&
            ($propertyMapper->getFieldMap(UserPropertyField::Name->value) !== null) &&
            ($propertyMapper->getFieldMap(UserPropertyField::Value->value) !== null);

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
            $userId = $model->getUserid();
            if ($userId === null) {
                throw new UserNotFoundException("User ID not set after save");
            }
            $model = $this->getById($userId);
        }

        if ($model === null) {
            throw new UserNotFoundException("User not found");
        }

        return $model;
    }

    /**
     * @param string|null $role
     * @inheritDoc
     */
    #[\Override]
    public function addUser(string $name, string $userName, string $email, string $password, ?string $role = null): UserModel
    {

        $mapper = $this->usersRepository->getMapper();
        $nameMapping = $mapper->getFieldMap(UserField::Name->value);
        $emailMapping = $mapper->getFieldMap(UserField::Email->value);
        $usernameMapping = $mapper->getFieldMap(UserField::Username->value);
        $roleMapping = $mapper->getFieldMap(UserField::Role->value);

        if ($nameMapping === null || $emailMapping === null || $usernameMapping === null || $roleMapping === null) {
            throw new InvalidArgumentException('Required field mapping not found');
        }

        $model = $mapper->getEntity([
            $nameMapping->getFieldName() => $name,
            $emailMapping->getFieldName() => $email,
            $usernameMapping->getFieldName() => $userName,
            $roleMapping->getFieldName() => $role
        ]);

        if (!$model instanceof UserModel) {
            throw new InvalidArgumentException('Entity must be an instance of UserModel');
        }

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
        $userId = $model->getUserid();
        if (!empty($userId) && $this->getById($userId) !== null) {
            throw new UserExistsException('User ID already exists');
        }

        $username = $model->getUsername();
        if ($username !== null && $this->getByUsername($username) !== null) {
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
        $fieldMap = $this->usersRepository->getMapper()->getFieldMap(UserField::Email->value);
        if ($fieldMap === null) {
            throw new InvalidArgumentException('Email field mapping not found');
        }
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
        $fieldMap = $this->usersRepository->getMapper()->getFieldMap(UserField::Username->value);
        if ($fieldMap === null) {
            throw new InvalidArgumentException('Username field mapping not found');
        }
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
        $userId = $user->getUserid();
        if ($userId === null) {
            return;
        }
        $properties = $this->propertiesRepository->getByUserId($userId);
        $user->setProperties($properties);
    }

    protected function applyPasswordDefinition(UserModel $user): void
    {
        if ($this->passwordDefinition !== null) {
            $user->withPasswordDefinition($this->passwordDefinition);
        }
    }

    protected function resolveUserFieldValue(UserModel $user, string $fieldName, ?string $default = null): mixed
    {
        $camel = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ' , $fieldName)));
        $method = 'get' . $camel;
        if (method_exists($user, $method)) {
            $value = $user->$method();
        } else {
            $value = $user->get($fieldName);
        }

        return empty($value) ? $default : $value;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function removeByLogin(string $login): bool
    {
        $user = $this->getByLogin($login);
        if ($user !== null) {
            $userId = $user->getUserid();
            if ($userId !== null) {
                return $this->removeById($userId);
            }
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
        $passwordFieldMapping = $this->usersRepository->getMapper()->getFieldMap(UserField::Password->value);
        if ($passwordFieldMapping === null) {
            throw new InvalidArgumentException('Password field mapping not found');
        }
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
            $userIdMapping = $propMapper->getFieldMap(UserPropertyField::Userid->value);
            $nameMapping = $propMapper->getFieldMap(UserPropertyField::Name->value);
            $valueMapping = $propMapper->getFieldMap(UserPropertyField::Value->value);

            if ($userIdMapping === null || $nameMapping === null || $valueMapping === null) {
                throw new InvalidArgumentException('Required field mapping not found');
            }

            $property = $propMapper->getEntity([
                $userIdMapping->getFieldName() => $userId,
                $nameMapping->getFieldName() => $propertyName,
                $valueMapping->getFieldName() => $value
            ]);

            if (!$property instanceof UserPropertiesModel) {
                throw new InvalidArgumentException('Entity must be an instance of UserPropertiesModel');
            }

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
            $userIdMapping = $propMapper->getFieldMap(UserPropertyField::Userid->value);
            $nameMapping = $propMapper->getFieldMap(UserPropertyField::Name->value);
            $valueMapping = $propMapper->getFieldMap(UserPropertyField::Value->value);

            if ($userIdMapping === null || $nameMapping === null || $valueMapping === null) {
                throw new InvalidArgumentException('Required field mapping not found');
            }

            $property = $propMapper->getEntity([
                $userIdMapping->getFieldName() => $userId,
                $nameMapping->getFieldName() => $propertyName,
                $valueMapping->getFieldName() => $value
            ]);

            if (!$property instanceof UserPropertiesModel) {
                throw new InvalidArgumentException('Entity must be an instance of UserPropertiesModel');
            }
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

        $propMapper = $this->propertiesRepository->getMapper();
        $userIdMapping = $propMapper->getFieldMap(UserPropertyField::Userid->value);
        $nameMapping = $propMapper->getFieldMap(UserPropertyField::Name->value);
        $valueMapping = $propMapper->getFieldMap(UserPropertyField::Value->value);

        if ($userIdMapping === null || $nameMapping === null || $valueMapping === null) {
            throw new InvalidArgumentException('Required field mapping not found');
        }

        $propUserIdField = $userIdMapping->getFieldName();
        $propNameField = $nameMapping->getFieldName();
        $propValueField = $valueMapping->getFieldName();

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

    #[\Override]
    public function createInsecureAuthToken(
        string|UserModel $login,
        JwtWrapper       $jwtWrapper,
        int              $expires = 1200,
        array            $updateUserInfo = [],
        array            $updateTokenInfo = [],
        array            $tokenUserFields = [UserField::Userid, UserField::Name, UserField::Role]
    ): ?UserToken
    {
        if (is_string($login)) {
            $user = $this->getByLogin($login);
        } else {
            $user = $login;
        }

        if (is_null($user)) {
            throw new UserNotFoundException('User not found');
        }

        foreach ($updateUserInfo as $key => $value) {
            $user->set($key, $value);
        }

        foreach ($tokenUserFields as $key => $field) {
            $default = null;
            if (!is_numeric($key)) {
                $default = $field;
                $field = $key;
            }
            $fieldName = $field instanceof UserField ? $field->value : $field;
            if (!is_string($fieldName) || $fieldName === '') {
                continue;
            }

            $value = $this->resolveUserFieldValue($user, $fieldName, $default);
            if ($value !== null) {
                $updateTokenInfo[$fieldName] = $value;
            }
        }

        $jwtData = $jwtWrapper->createJwtData($updateTokenInfo, $expires);

        $token = $jwtWrapper->generateToken($jwtData);

        $user->set('TOKEN_HASH', sha1($token));
        $this->save($user);

        return new UserToken(
            user: $user,
            token: $token,
            data: $updateTokenInfo
        );
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
        array      $tokenUserFields = [UserField::Userid, UserField::Name, UserField::Role]
    ): ?UserToken
    {
        $user = $this->isValidUser($login, $password);
        if (is_null($user)) {
            throw new UserNotFoundException('User not found');
        }

        return $this->createInsecureAuthToken(
            $user,
            $jwtWrapper,
            $expires,
            $updateUserInfo,
            $updateTokenInfo,
            $tokenUserFields
        );
    }

    /**
     * @inheritDoc
     * @throws JwtWrapperException
     * @throws NotAuthenticatedException
     * @throws UserNotFoundException
     */
    #[\Override]
    public function isValidToken(string $login, JwtWrapper $jwtWrapper, string $token): UserToken
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

        return new UserToken(
            user: $user,
            token: $token,
            data: (array)$data->data
        );
    }

    #[\Override]
    public function getUsersEntity(array $fields): UserModel
    {
        $entity = $this->getUsersRepository()->getMapper()->getEntity($fields);
        if (!$entity instanceof UserModel) {
            throw new InvalidArgumentException('Entity must be an instance of UserModel');
        }
        $this->applyPasswordDefinition($entity);
        return $entity;
    }
}
