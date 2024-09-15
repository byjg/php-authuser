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
use ByJG\Util\JwtWrapper;
use ByJG\Util\JwtWrapperException;
use InvalidArgumentException;

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
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
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
    abstract public function getUser(IteratorFilter $filter): UserModel|null;

    /**
     * Get the user based on his email.
     * Return Row if user was found; null, otherwise
     *
     * @param string $email
     * @return UserModel|null
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
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
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
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
     * @param string $userid
     * @return UserModel|null
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function getById(string $userid): UserModel|null
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
    abstract public function removeByLoginField(string $login): bool;

    /**
     * Validate if the user and password exists in the file
     * Return Row if user exists; null, otherwise
     *
     * @param string $userName User login
     * @param string $password Plain text password
     * @return UserModel|null
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
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
     * @param string $userId User identification
     * @param string $propertyName
     * @param string|null $value Property value
     * @return bool
     * @throws UserNotFoundException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function hasProperty(string $userId, string $propertyName, string $value = null): bool
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
     * @param string $userId User ID
     * @param string $propertyName Property name
     * @return array|string|null
     * @throws UserNotFoundException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function getProperty(string $userId, string $propertyName): array|string|null
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
     * @param string $userId
     * @param string $propertyName
     * @param string|null $value
     */
    abstract public function addProperty(string $userId, string $propertyName, string|null $value): bool;

    /**
     * Remove a specific site from user
     * Return True or false
     *
     * @param string $userId User login
     * @param string $propertyName Property name
     * @param string|null $value Property value with a site
     * @return bool
     * */
    abstract public function removeProperty(string $userId, string $propertyName, string|null $value = null): bool;

    /**
     * Remove a specific site from all users
     * Return True or false
     *
     * @param string $propertyName Property name
     * @param string|null $value Property value with a site
     * @return void
     * */
    abstract public function removeAllProperties(string $propertyName, string|null $value = null): void;

    /**
     * @param string $userId
     * @return bool
     * @throws UserNotFoundException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function isAdmin(string $userId): bool
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
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
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
     * @param string $userid
     */
    abstract public function removeUserById(string $userid): bool;
}
