<?php

namespace ByJG\Authenticate\Service;

use ByJG\Authenticate\Definition\PasswordDefinition;
use ByJG\Authenticate\Exception\NotAuthenticatedException;
use ByJG\Authenticate\Exception\UserExistsException;
use ByJG\Authenticate\Exception\UserNotFoundException;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\Authenticate\Repository\UserPropertiesRepository;
use ByJG\Authenticate\Repository\UsersRepository;
use ByJG\JwtWrapper\JwtWrapper;
use ByJG\JwtWrapper\JwtWrapperException;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\MicroOrm\Query;
use Exception;

/**
 * Service for User operations - Business Logic Layer
 */
class UsersService implements UsersServiceInterface
{
    protected UsersRepository $usersRepository;
    protected UserPropertiesRepository $propertiesRepository;
    protected ?PasswordDefinition $passwordDefinition;
    protected string $loginField;

    const LOGIN_IS_EMAIL = 'email';
    const LOGIN_IS_USERNAME = 'username';

    public function __construct(
        UsersRepository $usersRepository,
        UserPropertiesRepository $propertiesRepository,
        string $loginField = self::LOGIN_IS_USERNAME,
        ?PasswordDefinition $passwordDefinition = null
    ) {
        $this->usersRepository = $usersRepository;
        $this->propertiesRepository = $propertiesRepository;
        $this->loginField = $loginField;
        $this->passwordDefinition = $passwordDefinition;
    }

    /**
     * Get the users repository
     *
     * @return UsersRepository
     */
    public function getUsersRepository(): UsersRepository
    {
        return $this->usersRepository;
    }

    /**
     * Get the properties repository
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
    public function addUser(string $name, string $userName, string $email, string $password): UserModel
    {

        $model = $this->usersRepository->getMapper()->getEntity([
            'name' => $name,
            'email' => $email,
            'username' => $userName,
        ]);
        if ($this->passwordDefinition !== null) {
            $model->withPasswordDefinition($this->passwordDefinition);
        }
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
    public function getById(string|HexUuidLiteral|int $userid): ?UserModel
    {
        $user = $this->usersRepository->getById($userid);
        if ($user !== null) {
            $this->loadUserProperties($user);
        }
        return $user;
    }

    /**
     * @inheritDoc
     */
    public function getByEmail(string $email): ?UserModel
    {
        $user = $this->usersRepository->getByField('email', $email);
        if ($user !== null) {
            $this->loadUserProperties($user);
        }
        return $user;
    }

    /**
     * @inheritDoc
     */
    public function getByUsername(string $username): ?UserModel
    {
        $user = $this->usersRepository->getByField('username', $username);
        if ($user !== null) {
            $this->loadUserProperties($user);
        }
        return $user;
    }

    /**
     * @inheritDoc
     */
    public function getByLogin(string $login): ?UserModel
    {
        return $this->loginField === self::LOGIN_IS_EMAIL
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

    /**
     * @inheritDoc
     */
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
    public function removeById(string|HexUuidLiteral|int $userid): bool
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
    public function isValidUser(string $login, string $password): ?UserModel
    {
        $user = $this->getByLogin($login);

        if ($user === null) {
            return null;
        }

        // Hash the password for comparison using the model's configured password mapper
        $passwordFieldMapping = $this->usersRepository->getMapper()->getFieldMap('password');
        $hashedPassword = $passwordFieldMapping->getUpdateFunctionValue($password, null);

        if ($user->getPassword() === $hashedPassword) {
            return $user;
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function hasProperty(string|int|HexUuidLiteral $userId, string $propertyName, ?string $value = null): bool
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
    public function getProperty(string|HexUuidLiteral|int $userId, string $propertyName): array|string|UserPropertiesModel|null
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
    public function addProperty(string|HexUuidLiteral|int $userId, string $propertyName, ?string $value): bool
    {
        $user = $this->getById($userId);
        if (empty($user)) {
            return false;
        }

        if (!$this->hasProperty($userId, $propertyName, $value)) {
            $property = $this->propertiesRepository->getMapper()->getEntity([
                'userid' => $userId,
                'name' => $propertyName,
                'value' => $value
            ]);
            $this->propertiesRepository->save($property);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function setProperty(string|HexUuidLiteral|int $userId, string $propertyName, ?string $value): bool
    {
        $properties = $this->propertiesRepository->getByUserIdAndName($userId, $propertyName);

        if (empty($properties)) {
            $property = $this->propertiesRepository->getMapper()->getEntity([
                'userid' => $userId,
                'name' => $propertyName,
                'value' => $value
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
    public function removeProperty(string|HexUuidLiteral|int $userId, string $propertyName, ?string $value = null): bool
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
    public function removeAllProperties(string $propertyName, ?string $value = null): void
    {
        $this->propertiesRepository->deleteByName($propertyName, $value);
    }

    /**
     * @inheritDoc
     */
    public function getUsersByProperty(string $propertyName, string $value): array
    {
        return $this->getUsersByPropertySet([$propertyName => $value]);
    }

    /**
     * @inheritDoc
     */
    public function getUsersByPropertySet(array $propertiesArray): array
    {
        $userTable = $this->usersRepository->getTableName();
        $propTable = $this->propertiesRepository->getTableName();
        $userPk = $this->usersRepository->getPrimaryKeyName();

        // Handle composite keys - use first key if array
        if (is_array($userPk)) {
            $userPk = $userPk[0];
        }

        $propUserIdField = $this->propertiesRepository->getMapper()->getFieldMap('userid')->getFieldName();
        $propNameField = $this->propertiesRepository->getMapper()->getFieldMap('name')->getFieldName();
        $propValueField = $this->propertiesRepository->getMapper()->getFieldMap('value')->getFieldName();

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

        return $this->usersRepository->getRepository()->getByQuery($query);
    }

    /**
     * @inheritDoc
     */
    public function createAuthToken(
        string     $login,
        string     $password,
        JwtWrapper $jwtWrapper,
        int        $expires = 1200,
        array      $updateUserInfo = [],
        array      $updateTokenInfo = []
    ): ?string {
        $user = $this->isValidUser($login, $password);
        if (is_null($user)) {
            throw new UserNotFoundException('User not found');
        }

        foreach ($updateUserInfo as $key => $value) {
            $user->set($key, $value);
        }

        $updateTokenInfo['login'] = $login;
        $updateTokenInfo['userid'] = $user->getUserid();
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

    public function getUsersEntity(array $fields): UserModel
    {
        return $this->getUsersRepository()->getMapper()->getEntity($fields);
    }
}
