<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Enum\Relation;
use ByJG\AnyDataset\Dataset\IteratorFilter;
use ByJG\AnyDataset\Dataset\Row;
use ByJG\Authenticate\Definition\CustomTable;
use ByJG\Authenticate\Definition\UserTable;
use ByJG\Authenticate\Exception\NotAuthenticatedException;
use ByJG\Authenticate\Exception\UserNotFoundException;
use ByJG\Util\JwtWrapper;
use InvalidArgumentException;

/**
 * Base implementation to search and handle users in XMLNuke.
 */
abstract class UsersBase implements UsersInterface
{

    /**
     * @var UserTable
     */
    protected $_userTable;

    /**
     * @var CustomTable
     */
    protected $_customTable;

    /**
     *
     * @return UserTable
     */
    public function getUserTable()
    {
        if ($this->_userTable === null) {
            $this->_userTable = new UserTable();
        }
        return $this->_userTable;
    }

    /**
     *
     * @return CustomTable
     */
    public function getCustomTable()
    {
        if ($this->_customTable === null) {
            $this->_customTable = new CustomTable();
        }
        return $this->_customTable;
    }

    /**
     * Save the current UsersAnyDataset
     * */
    public function save()
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
     * @return Row
     * */
    abstract public function getUser($filter);

    /**
     * Get the user based on his email.
     * Return Row if user was found; null, otherwise
     *
     * @param string $email
     * @return Row
     * */
    public function getByEmail($email)
    {
        $filter = new IteratorFilter();
        $filter->addRelation($this->getUserTable()->email, Relation::EQUAL, strtolower($email));
        return $this->getUser($filter);
    }

    /**
     * Get the user based on his login.
     * Return Row if user was found; null, otherwise
     *
     * @param string $username
     * @return Row
     * */
    public function getByUsername($username)
    {
        $filter = new IteratorFilter();
        $filter->addRelation($this->getUserTable()->username, Relation::EQUAL, strtolower($username));
        return $this->getUser($filter);
    }

    /**
     * Get the user based on his id.
     * Return Row if user was found; null, otherwise
     *
     * @param string $id
     * @return Row
     * */
    public function getById($id)
    {
        $filter = new IteratorFilter();
        $filter->addRelation($this->getUserTable()->id, Relation::EQUAL, $id);
        return $this->getUser($filter);
    }

    /**
     * Remove the user based on his login.
     *
     * @param string $username
     * @return bool
     * */
    abstract public function removeUserName($username);

    /**
     * Get the SHA1 string from user password
     *
     * @param string $password Plain password
     * @return string
     * */
    public function getPasswordHash($password)
    {
        return strtoupper(sha1($password));
    }

    /**
     * Validate if the user and password exists in the file
     * Return Row if user exists; null, otherwise
     *
     * @param string $userName User login
     * @param string $password Plain text password
     * @return Row
     * */
    public function isValidUser($userName, $password)
    {
        $filter = new IteratorFilter();
        $filter->addRelation($this->getUserTable()->username, Relation::EQUAL, strtolower($userName));
        $filter->addRelation($this->getUserTable()->password, Relation::EQUAL, $this->getPasswordHash($password));
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

        $values = $user->getAsArray($propertyName);
        return ($values !== null ? in_array($value, $values) : false);
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
            $values = $user->getAsArray($propertyName);

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
    abstract public function removeProperty($userId, $propertyName, $value);

    /**
     * Remove a specific site from all users
     * Return True or false
     *
     * @param string $propertyName Property name
     * @param string $value Property value with a site
     * @return bool
     * */
    abstract public function removeAllProperties($propertyName, $value);

    /**
     *
     * @param int $userId
     * @return bool
     * @throws NotAuthenticatedException
     * @throws UserNotFoundException
     */
    public function isAdmin($userId = null)
    {
        if (is_null($userId)) {
            $currentUser = (new SessionContext())->userInfo();
            if ($currentUser === false) {
                throw new NotAuthenticatedException();
            }
            $userId = $currentUser[$this->getUserTable()->id];
        }

        $user = $this->getById($userId);

        if (is_null($user)) {
            throw new UserNotFoundException("Cannot find the user");
        }

        return
            preg_match('/^(yes|YES|[yY]|true|TRUE|[tT]|1|[sS])$/', $user->get($this->getUserTable()->admin)) === 1
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
        $updateTokenInfo['userid'] = $user->get(UsersBase::getUserTable()->id);
        $jwtData = $jwt->createJwtData(
            $updateTokenInfo,
            $expires
        );

        $token = $jwt->generateToken($jwtData);

        $user->set('TOKEN_HASH', sha1($token));
        $this->save();

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
        $user = $this->getByUsername($username);

        if (is_null($user)) {
            throw new UserNotFoundException('User not found!');
        }

        if ($user->get('TOKEN_HASH') !== sha1($token)) {
            throw new NotAuthenticatedException('Token does not match');
        }

        $jwt = new JwtWrapper($uri, $secret);
        $data = $jwt->extractData($token);

        $user->set('LAST_VISIT', date('Y-m-d H:i:s'));
        $this->save();

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
