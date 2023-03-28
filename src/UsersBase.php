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
use InvalidArgumentException;

/**
 * Base implementation to search and handle users in XMLNuke.
 */
abstract class UsersBase implements UsersInterface
{

    /**
     * @var UserDefinition
     */
    protected $userTable;

    /**
     * @var UserPropertiesDefinition
     */
    protected $propertiesTable;

    /**
     * @return UserDefinition
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function getUserDefinition()
    {
        if ($this->userTable === null) {
            $this->userTable = new UserDefinition();
        }
        return $this->userTable;
    }

    /**
     * @return UserPropertiesDefinition
     */
    public function getUserPropertiesDefinition()
    {
        if ($this->propertiesTable === null) {
            $this->propertiesTable = new UserPropertiesDefinition();
        }
        return $this->propertiesTable;
    }

    /**
     * Save the current UsersAnyDataset
     *
     * @param \ByJG\Authenticate\Model\UserModel $model
     */
    abstract public function save(UserModel $model);

    /**
     * Add new user in database
     *
     * @param string $name
     * @param string $userName
     * @param string $email
     * @param string $password
     * @return UserModel
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function addUser($name, $userName, $email, $password)
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
     * @throws \ByJG\Authenticate\Exception\UserExistsException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function canAddUser($model)
    {
        if ($this->getByEmail($model->getEmail()) !== null) {
            throw new UserExistsException('Email already exists');
        }
        $filter = new IteratorFilter();
        $filter->addRelation($this->getUserDefinition()->getUsername(), Relation::EQUAL, $model->getUsername());
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
     * @return UserModel
     * */
    abstract public function getUser($filter);

    /**
     * Get the user based on his email.
     * Return Row if user was found; null, otherwise
     *
     * @param string $email
     * @return UserModel
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function getByEmail($email)
    {
        $filter = new IteratorFilter();
        $filter->addRelation($this->getUserDefinition()->getEmail(), Relation::EQUAL, strtolower($email));
        return $this->getUser($filter);
    }

    /**
     * Get the user based on his username.
     * Return Row if user was found; null, otherwise
     *
     * @param $username
     * @return UserModel
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function getByUsername($username)
    {
        $filter = new IteratorFilter();
        $filter->addRelation($this->getUserDefinition()->getUsername(), Relation::EQUAL, $username);
        return $this->getUser($filter);
    }

    /**
     * Get the user based on his login.
     * Return Row if user was found; null, otherwise
     *
     * @param string $login
     * @return UserModel
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function getByLoginField($login)
    {
        $filter = new IteratorFilter();
        $filter->addRelation($this->getUserDefinition()->loginField(), Relation::EQUAL, strtolower($login));

        return $this->getUser($filter);
    }

    /**
     * Get the user based on his id.
     * Return Row if user was found; null, otherwise
     *
     * @param string $userid
     * @return UserModel
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function getById($userid)
    {
        $filter = new IteratorFilter();
        $filter->addRelation($this->getUserDefinition()->getUserid(), Relation::EQUAL, $userid);
        return $this->getUser($filter);
    }

    /**
     * Remove the user based on his login.
     *
     * @param string $login
     * @return bool
     * */
    abstract public function removeByLoginField($login);

    /**
     * Validate if the user and password exists in the file
     * Return Row if user exists; null, otherwise
     *
     * @param string $userName User login
     * @param string $password Plain text password
     * @return UserModel
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function isValidUser($userName, $password)
    {
        $filter = new IteratorFilter();
        $passwordGenerator = $this->getUserDefinition()->getClosureForUpdate(UserDefinition::FIELD_PASSWORD);
        $filter->addRelation($this->getUserDefinition()->loginField(), Relation::EQUAL, strtolower($userName));
        $filter->addRelation(
            $this->getUserDefinition()->getPassword(),
            Relation::EQUAL,
            $passwordGenerator($password, null)
        );
        return $this->getUser($filter);
    }

    /**
     * Check if the user have a property and it have a specific value.
     * Return True if have rights; false, otherwise
     *
     * @param mixed $userId User identification
     * @param string $propertyName
     * @param string $value Property value
     * @return bool
     * @throws \ByJG\Authenticate\Exception\UserNotFoundException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function hasProperty($userId, $propertyName, $value = null)
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
        return ($values !== null ? in_array($value, (array)$values) : false);
    }

    /**
     * Return all sites from a specific user
     * Return String vector with all sites
     *
     * @param string $userId User ID
     * @param string $propertyName Property name
     * @return array
     * @throws \ByJG\Authenticate\Exception\UserNotFoundException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function getProperty($userId, $propertyName)
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

    abstract public function getUsersByProperty($propertyName, $value);

    /**
     *
     * @param int $userId
     * @param string $propertyName
     * @param string $value
     */
    abstract public function addProperty($userId, $propertyName, $value);

    /**
     * Remove a specific site from user
     * Return True or false
     *
     * @param int $userId User login
     * @param string $propertyName Property name
     * @param string $value Property value with a site
     * @return bool
     * */
    abstract public function removeProperty($userId, $propertyName, $value = null);

    /**
     * Remove a specific site from all users
     * Return True or false
     *
     * @param string $propertyName Property name
     * @param string $value Property value with a site
     * @return bool
     * */
    abstract public function removeAllProperties($propertyName, $value = null);

    /**
     * @param int|string $userId
     * @return bool
     * @throws \ByJG\Authenticate\Exception\UserNotFoundException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function isAdmin($userId)
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
     * @return string the TOKEN or false if dont.
     * @throws UserNotFoundException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function createAuthToken(
        $login,
        $password,
        $jwtWrapper,
        $expires = 1200,
        $updateUserInfo = [],
        $updateTokenInfo = []
    ) {
        if (!isset($login) || !isset($password)) {
            throw new InvalidArgumentException('Neither username or password can be empty!');
        }

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
     * @return array
     * @throws NotAuthenticatedException
     * @throws UserNotFoundException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     * @throws \ByJG\Util\JwtWrapperException
     */
    public function isValidToken($login, $jwtWrapper, $token)
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
     * @param $userid
     */
    abstract public function removeUserById($userid);
}
