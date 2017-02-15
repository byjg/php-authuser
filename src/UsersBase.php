<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Enum\Relation;
use ByJG\AnyDataset\Dataset\IteratorFilter;
use ByJG\AnyDataset\Dataset\SingleRow;
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
     * Return SingleRow if user was found; null, otherwise
     *
     * @param IteratorFilter $filter Filter to find user
     * @return SingleRow
     * */
    abstract public function getUser($filter);

    /**
     * Get the user based on his email.
     * Return SingleRow if user was found; null, otherwise
     *
     * @param string $email
     * @return SingleRow
     * */
    public function getByEmail($email)
    {
        $filter = new IteratorFilter();
        $filter->addRelation($this->getUserTable()->email, Relation::EQUAL, strtolower($email));
        return $this->getUser($filter);
    }

    /**
     * Get the user based on his login.
     * Return SingleRow if user was found; null, otherwise
     *
     * @param string $username
     * @return SingleRow
     * */
    public function getByUsername($username)
    {
        $filter = new IteratorFilter();
        $filter->addRelation($this->getUserTable()->username, Relation::EQUAL, strtolower($username));
        return $this->getUser($filter);
    }

    /**
     * Get the user based on his id.
     * Return SingleRow if user was found; null, otherwise
     *
     * @param string $id
     * @return SingleRow
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
     * Return SingleRow if user exists; null, otherwise
     *
     * @param string $userName User login
     * @param string $password Plain text password
     * @return SingleRow
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
        //anydataset.SingleRow
        $user = $this->getById($userId);

        if (empty($user)) {
            return false;
        }

        if ($this->isAdmin($userId)) {
            return true;
        }

        $values = $user->getFieldArray($propertyName);
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
        //anydataset.SingleRow
        $user = $this->getById($userId);
        if ($user !== null) {
            $values = $user->getFieldArray($propertyName);

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
            preg_match('/^(yes|YES|[yY]|true|TRUE|[tT]|1|[sS])$/', $user->getField($this->getUserTable()->admin)) === 1
        ;
    }

    /**
     * Authenticate a user and create a token if it is valid
     *
     * @param string $username
     * @param string $password
     * @param string $uri
     * @param string $secret
     * @param int $expires
     * @param array $updateUserInfo
     * @param array $updateTokenInfo
     * @return \ByJG\AnyDataset\Dataset\SingleRow Return the TOKEN or false if dont.
     * @throws \ByJG\Authenticate\Exception\UserNotFoundException
     */
    public function createAuthToken($username, $password, $uri, $secret, $expires = 1200, $updateUserInfo = [], $updateTokenInfo = [])
    {
        if (!isset($username) || !isset($password)) {
            throw new InvalidArgumentException('Neither username or password can be empty!');
        }

        $user = $this->isValidUser($username, $password);
        if (is_null($user)) {
            throw new UserNotFoundException('User not found');
        }

        foreach ($updateUserInfo as $key => $value) {
            $user->setField($key, $value);
        }
        $user->setField('LAST_LOGIN', date('Y-m-d H:i:s'));
        $user->setField('LAST_VISIT', date('Y-m-d H:i:s'));
        $user->setField('LOGIN_TIMES', intval($user->getField('LOGIN_TIMES')) + 1);

        $jwt = new JwtWrapper($uri, $secret);
        $updateTokenInfo['username'] = $username;
        $jwtData = $jwt->createJwtData(
            $updateTokenInfo,
            $expires
        );

        $token = $jwt->generateToken($jwtData);

        $user->setField('TOKEN', $token);
        $this->save();

        return $user;
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

        if ($user->getField('TOKEN') !== $token) {
            throw new NotAuthenticatedException('Token does not match');
        }

        $jwt = new JwtWrapper($uri, $secret);
        $data = $jwt->extractData($user->getField('TOKEN'));

        $user->setField('LAST_VISIT', date('Y-m-d H:i:s'));
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
