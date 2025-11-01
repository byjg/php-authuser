<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Core\Enum\Relation;
use ByJG\AnyDataset\Core\IteratorFilter;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Exception\NotAuthenticatedException;
use ByJG\Authenticate\Exception\UserExistsException;
use ByJG\Authenticate\Exception\UserNotFoundException;
use ByJG\Authenticate\Interfaces\UsersInterface;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\JwtWrapper\JwtWrapper;
use ByJG\JwtWrapper\JwtWrapperException;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\Serializer\Exception\InvalidArgumentException;

/**
 * Base implementation to search and handle users in XMLNuke.
 */
abstract class UsersBase implements UsersInterface
{

    /**
     * @var UserDefinition|null
     */
    protected ?UserDefinition $userTable = null;

    /**
     * @var UserPropertiesDefinition|null
     */
    protected ?UserPropertiesDefinition $propertiesTable = null;

    /**
     * @return UserDefinition
     */
    #[\Override]
    public function getUserDefinition(): UserDefinition
    {
        if ($this->userTable === null) {
            $this->userTable = new UserDefinition();
        }
        return $this->userTable;
    }

    /**
     * @return UserPropertiesDefinition
     */
    #[\Override]
    public function getUserPropertiesDefinition(): UserPropertiesDefinition
    {
        if ($this->propertiesTable === null) {
            $this->propertiesTable = new UserPropertiesDefinition();
        }
        return $this->propertiesTable;
    }

    /**
     * Save the current UsersAnyDataset
     *
     * @param UserModel $model
     */
    #[\Override]
    abstract public function save(UserModel $model): UserModel;

    /**
     * Add new user in database
     *
     * @param string $name
     * @param string $userName
     * @param string $email
     * @param string $password
     * @return UserModel
     */
    #[\Override]
    public function addUser(string $name, string $userName, string $email, string $password): UserModel
    {
        $model = $this->getUserDefinition()->modelInstance();
        $model->setName($name);
        $model->setEmail($email);
        $model->setUsername($userName);
        $model->setPassword($password);

        return $this->save($model);
    }

    /**
     * @param UserModel $model
     * @return bool
     * @throws UserExistsException
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function canAddUser(UserModel $model): bool
    {
        if ($this->getByEmail($model->getEmail()) !== null) {
            throw new UserExistsException('Email already exists');
        }
        $filter = new IteratorFilter();
        $filter->and($this->getUserDefinition()->getUsername(), Relation::EQUAL, $model->getUsername());
        if ($this->getUser($filter) !== null) {
            throw new UserExistsException('Username already exists');
        }

        return false;
    }

    /**
     * Get the user based on a filter.
     * Return Row if user was found; null, otherwise
     *
     * @param IteratorFilter $filter Filter to find user
     * @return UserModel|null
     * */
    #[\Override]
    abstract public function getUser(IteratorFilter $filter): UserModel|null;

    /**
     * Get the user based on his email.
     * Return Row if user was found; null, otherwise
     *
     * @param string $email
     * @return UserModel|null
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function getByEmail(string $email): UserModel|null
    {
        $filter = new IteratorFilter();
        $filter->and($this->getUserDefinition()->getEmail(), Relation::EQUAL, strtolower($email));
        return $this->getUser($filter);
    }

    /**
     * Get the user based on his username.
     * Return Row if user was found; null, otherwise
     *
     * @param string $username
     * @return UserModel|null
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function getByUsername(string $username): UserModel|null
    {
        $filter = new IteratorFilter();
        $filter->and($this->getUserDefinition()->getUsername(), Relation::EQUAL, $username);
        return $this->getUser($filter);
    }

    /**
     * Get the user based on his login.
     * Return Row if user was found; null, otherwise
     *
     * @param string $login
     * @return UserModel|null
     */
    #[\Override]
    public function getByLoginField(string $login): UserModel|null
    {
        $filter = new IteratorFilter();
        $filter->and($this->getUserDefinition()->loginField(), Relation::EQUAL, strtolower($login));

        return $this->getUser($filter);
    }

    /**
     * Get the user based on his id.
     * Return Row if user was found; null, otherwise
     *
     * @param string|HexUuidLiteral|int $userid
     * @return UserModel|null
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function getById(string|HexUuidLiteral|int $userid): UserModel|null
    {
        $filter = new IteratorFilter();
        $filter->and($this->getUserDefinition()->getUserid(), Relation::EQUAL, $userid);
        return $this->getUser($filter);
    }

    /**
     * Remove the user based on his login.
     *
     * @param string $login
     * @return bool
     * */
    #[\Override]
    abstract public function removeByLoginField(string $login): bool;

    /**
     * Validate if the user and password exists in the file
     * Return Row if user exists; null, otherwise
     *
     * @param string $userName User login
     * @param string $password Plain text password
     * @return UserModel|null
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function isValidUser(string $userName, string $password): UserModel|null
    {
        $filter = new IteratorFilter();
        $passwordGenerator = $this->getUserDefinition()->getClosureForUpdate(UserDefinition::FIELD_PASSWORD);
        $filter->and($this->getUserDefinition()->loginField(), Relation::EQUAL, strtolower($userName));
        $filter->and(
            $this->getUserDefinition()->getPassword(),
            Relation::EQUAL,
            $passwordGenerator($password, null)
        );
        return $this->getUser($filter);
    }

    /**
     * Check if the user have a property and it has a specific value.
     * Return True if you have rights; false, otherwise
     *
     * @param string|int|HexUuidLiteral|null $userId User identification
     * @param string $propertyName
     * @param string|null $value Property value
     * @return bool
     * @throws UserNotFoundException
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function hasProperty(string|int|HexUuidLiteral|null $userId, string $propertyName, string|null $value = null): bool
    {
        //anydataset.Row
        $user = $this->getById($userId);

        if (empty($user)) {
            return false;
        }

        if ($this->isAdmin($userId)) {
            return true;
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
     * Return all sites from a specific user
     * Return String vector with all sites
     *
     * @param string|int|HexUuidLiteral $userId User ID
     * @param string $propertyName Property name
     * @return array|string|UserPropertiesModel|null
     * @throws UserNotFoundException
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function getProperty(string|HexUuidLiteral|int $userId, string $propertyName): array|string|UserPropertiesModel|null
    {
        $user = $this->getById($userId);
        if ($user !== null) {
            $values = $user->get($propertyName);

            if ($this->isAdmin($userId)) {
                return array(UserDefinition::FIELD_ADMIN => "admin");
            }

            return $values;
        }

        return null;
    }

    abstract public function getUsersByProperty(string $propertyName, string $value): array;

    abstract public function getUsersByPropertySet(array $propertiesArray): array;

    /**
     *
     * @param string|int|HexUuidLiteral $userId
     * @param string $propertyName
     * @param string|null $value
     */
    #[\Override]
    abstract public function addProperty(string|HexUuidLiteral|int $userId, string $propertyName, string|null $value): bool;

    /**
     * Remove a specific site from user
     * Return True or false
     *
     * @param string|int|HexUuidLiteral $userId User login
     * @param string $propertyName Property name
     * @param string|null $value Property value with a site
     * @return bool
     * */
    #[\Override]
    abstract public function removeProperty(string|HexUuidLiteral|int $userId, string $propertyName, string|null $value = null): bool;

    /**
     * Remove a specific site from all users
     * Return True or false
     *
     * @param string $propertyName Property name
     * @param string|null $value Property value with a site
     * @return void
     * */
    #[\Override]
    abstract public function removeAllProperties(string $propertyName, string|null $value = null): void;

    /**
     * @param string|int|HexUuidLiteral $userId
     * @return bool
     * @throws UserNotFoundException
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function isAdmin(string|HexUuidLiteral|int $userId): bool
    {
        $user = $this->getById($userId);

        if (is_null($user)) {
            throw new UserNotFoundException("Cannot find the user");
        }

        return
            preg_match('/^(yes|YES|[yY]|true|TRUE|[tT]|1|[sS])$/', $user->getAdmin()) === 1
        ;
    }

    /**
     * Authenticate a user and create a token if it is valid
     *
     * @param string $login
     * @param string $password
     * @param JwtWrapper $jwtWrapper
     * @param int $expires
     * @param array $updateUserInfo
     * @param array $updateTokenInfo
     * @return string|null the TOKEN or false if you don't.
     * @throws UserNotFoundException
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function createAuthToken(
        string     $login,
        string     $password,
        JwtWrapper $jwtWrapper,
        int        $expires = 1200,
        array      $updateUserInfo = [],
        array      $updateTokenInfo = []
    ): string|null
    {
        $user = $this->isValidUser($login, $password);
        if (is_null($user)) {
            throw new UserNotFoundException('User not found');
        }

        foreach ($updateUserInfo as $key => $value) {
            $user->set($key, $value);
        }

        $updateTokenInfo['login'] = $login;
        $updateTokenInfo[UserDefinition::FIELD_USERID] = $user->getUserid();
        $jwtData = $jwtWrapper->createJwtData(
            $updateTokenInfo,
            $expires
        );

        $token = $jwtWrapper->generateToken($jwtData);

        $user->set('TOKEN_HASH', sha1($token));
        $this->save($user);

        return $token;
    }

    /**
     * Check if the Auth Token is valid
     *
     * @param string $login
     * @param JwtWrapper $jwtWrapper
     * @param string $token
     * @return array|null
     * @throws JwtWrapperException
     * @throws NotAuthenticatedException
     * @throws UserNotFoundException
     */
    #[\Override]
    public function isValidToken(string $login, JwtWrapper $jwtWrapper, string $token): array|null
    {
        $user = $this->getByLoginField($login);

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

    /**
     * @param string|int|HexUuidLiteral $userid
     */
    #[\Override]
    abstract public function removeUserById(string|HexUuidLiteral|int $userid): bool;
}
