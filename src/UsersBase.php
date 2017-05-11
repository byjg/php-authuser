<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Enum\Relation;
use ByJG\AnyDataset\Dataset\IteratorFilter;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Exception\NotAuthenticatedException;
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
    protected $_userTable;

    /**
     * @var UserPropertiesDefinition
     */
    protected $_propertiesTable;

    /**
     * @return UserDefinition
     */
    public function getUserDefinition()
    {
        if ($this->_userTable === null) {
            $this->_userTable = new UserDefinition();
        }
        return $this->_userTable;
    }

    /**
     * @return UserPropertiesDefinition
     */
    public function getUserPropertiesDefinition()
    {
        if ($this->_propertiesTable === null) {
            $this->_propertiesTable = new UserPropertiesDefinition();
        }
        return $this->_propertiesTable;
    }

    /**
     * Save the current UsersAnyDataset
     *
     * @param \ByJG\Authenticate\Model\UserModel $model
     */
    public function save(UserModel $model)
    {
        
    }

    /**
     * Add new user in database
     *
     * @param string $name
     * @param string $userName
     * @param string $email
     * @param string $password
     * @return bool
     */
    abstract public function addUser($name, $userName, $email, $password);

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
     * */
    public function getByEmail($email)
    {
        $filter = new IteratorFilter();
        $filter->addRelation($this->getUserDefinition()->getEmail(), Relation::EQUAL, strtolower($email));
        return $this->getUser($filter);
    }

    /**
     * Get the user based on his login.
     * Return Row if user was found; null, otherwise
     *
     * @param string $username
     * @return UserModel
     * */
    public function getByLoginField($username)
    {
        $filter = new IteratorFilter();
        $filter->addRelation($this->getUserDefinition()->getLoginField(), Relation::EQUAL, strtolower($username));

        return $this->getUser($filter);
    }

    /**
     * Get the user based on his id.
     * Return Row if user was found; null, otherwise
     *
     * @param string $id
     * @return UserModel
     * */
    public function getById($id)
    {
        $filter = new IteratorFilter();
        $filter->addRelation($this->getUserDefinition()->getUserid(), Relation::EQUAL, $id);
        return $this->getUser($filter);
    }

    /**
     * Remove the user based on his login.
     *
     * @param string $username
     * @return bool
     * */
    abstract public function removeByLoginField($username);

    /**
     * Validate if the user and password exists in the file
     * Return Row if user exists; null, otherwise
     *
     * @param string $userName User login
     * @param string $password Plain text password
     * @return UserModel
     * */
    public function isValidUser($userName, $password)
    {
        $filter = new IteratorFilter();
        $passwordGenerator = $this->getUserDefinition()->getClosureForUpdate('password');
        $filter->addRelation($this->getUserDefinition()->getLoginField(), Relation::EQUAL, strtolower($userName));
        $filter->addRelation($this->getUserDefinition()->getPassword(), Relation::EQUAL, $passwordGenerator($password, null));
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
     *
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
     * */
    public function getProperty($userId, $propertyName)
    {
        //anydataset.Row
        $user = $this->getById($userId);
        if ($user !== null) {
            $values = $user->get($propertyName);

            if ($this->isAdmin($userId)) {
                return array("admin" => "admin");
            }

            return $values;
        }

        return null;
    }

    /**
     *
     * @param int $userId
     * @param string $propertyName
     * @param string $value
     */
    public function addProperty($userId, $propertyName, $value)
    {

    }

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
     * @param string $username
     * @param string $password
     * @param string $serverUri
     * @param string $secret
     * @param int $expires
     * @param array $updateUserInfo
     * @param array $updateTokenInfo
     * @return string the TOKEN or false if dont.
     * @throws \ByJG\Authenticate\Exception\UserNotFoundException
     */
    public function createAuthToken($username, $password, $serverUri, $secret, $expires = 1200, $updateUserInfo = [], $updateTokenInfo = [])
    {
        if (!isset($username) || !isset($password)) {
            throw new InvalidArgumentException('Neither username or password can be empty!');
        }

        $user = $this->isValidUser($username, $password);
        if (is_null($user)) {
            throw new UserNotFoundException('User not found');
        }

        foreach ($updateUserInfo as $key => $value) {
            $user->set($key, $value);
        }
        $user->set('LAST_LOGIN', date('Y-m-d H:i:s'));
        $user->set('LAST_VISIT', date('Y-m-d H:i:s'));
        $user->set('LOGIN_TIMES', intval($user->get('LOGIN_TIMES')) + 1);

        $jwt = new JwtWrapper($serverUri, $secret);
        $updateTokenInfo['username'] = $username;
        $updateTokenInfo['userid'] = $user->getUserid();
        $jwtData = $jwt->createJwtData(
            $updateTokenInfo,
            $expires
        );

        $token = $jwt->generateToken($jwtData);

        $user->set('TOKEN_HASH', sha1($token));
        $this->save($user);

        return $token;
    }

    /**
     * Check if the Auth Token is valid
     *
     * @param string $username
     * @param string $uri
     * @param string $secret
     * @param string $token
     * @return array
     * @throws \ByJG\Authenticate\Exception\NotAuthenticatedException
     * @throws \ByJG\Authenticate\Exception\UserNotFoundException
     */
    public function isValidToken($username, $uri, $secret, $token)
    {
        $user = $this->getByLoginField($username);

        if (is_null($user)) {
            throw new UserNotFoundException('User not found!');
        }

        if ($user->get('TOKEN_HASH') !== sha1($token)) {
            throw new NotAuthenticatedException('Token does not match');
        }

        $jwt = new JwtWrapper($uri, $secret);
        $data = $jwt->extractData($token);

        $user->set('LAST_VISIT', date('Y-m-d H:i:s'));
        $this->save($user);

        return [
            'user' => $user,
            'data' => $data->data
        ];
    }

    /**
     * @inheritdoc
     */
    public function generateUserId()
    {
        return null;
    }
}
