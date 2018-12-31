<?php

namespace ByJG\Authenticate\Interfaces;

use ByJG\AnyDataset\Core\IteratorFilter;
use ByJG\AnyDataset\Core\Row;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Model\UserModel;

/**
 * IUsersBase is a Interface to Store and Retrive USERS from an AnyDataset or a DBDataset structure.
 * @package xmlnuke
 */
interface UsersInterface
{

    /**
     * @desc Save the current DataSet
     * @param \ByJG\Authenticate\Model\UserModel $model
     * @return void
     */
    public function save(UserModel $model);

    /**
     * @desc Add a new user
     * @param string $name
     * @param string $userName
     * @param string $email
     * @param string $password
     * @return bool
     */
    public function addUser($name, $userName, $email, $password);

    /**
     * @param $model
     * @return bool
     */
    public function canAddUser($model);

    /**
     * @desc Get the user based on a filter
     * @param IteratorFilter $filter
     * @return UserModel if user was found; null, otherwise
     */
    public function getUser($filter);

    /**
     * Enter description here...
     *
     * @param int $userid
     * @return Row
     */
    public function getById($userid);

    /**
     * @desc Get the user based on his email.
     * @param string $email Email to find
     * @return Row if user was found; null, otherwise
     */
    public function getByEmail($email);

    /**
     * @desc Get the user based on his username.
     * @param $username
     * @return Row if user was found; null, otherwise
     */
    public function getByUsername($username);

    /**
     * @desc Get the user based on his login
     * @param string $login
     * @return Row if user was found; null, otherwise
     */
    public function getByLoginField($login);

    /**
     * @desc Remove the user based on his login.
     * @param string $login
     * @return bool
     */
    public function removeByLoginField($login);

    /**
     * @desc Validate if the user and password exists in the file
     * @param string $userName
     * @param string $password
     * @return Row if user was found; null, otherwise
     */
    public function isValidUser($userName, $password);

    /**
     *
     * @param int|string $userId
     * @return bool
     */
    public function isAdmin($userId);

    /**
     * @desc Check if the user have rights to edit specific site.
     * @param int $userId
     * @param string $propertyName
     * @param string $value
     * @return True if have rights; false, otherwisebool
     */
    public function hasProperty($userId, $propertyName, $value = null);

    /**
     * @desc Return all sites from a specific user
     * @param int $userId
     * @param string $propertyName
     * @return string[] String vector with all sites
     */
    public function getProperty($userId, $propertyName);

    /**
     *
     * @param int $userId
     * @param string $propertyName
     * @param string $value
     */
    public function addProperty($userId, $propertyName, $value);

    /**
     *
     * @param int $userId
     * @param string $propertyName
     * @param string $value
     */
    public function removeProperty($userId, $propertyName, $value = null);

    /**
     * @desc Remove a specific site from all users
     * @param string $propertyName
     * @param string $value
     * @return bool
     */
    public function removeAllProperties($propertyName, $value = null);

    /**
     * Authenticate a user and create a token if it is valid
     *
     * @param string $login
     * @param string $password
     * @param string $serverUri
     * @param string $secret
     * @param int $expires
     * @param array $updateUserInfo
     * @param array $updateTokenInfo
     * @return \ByJG\AnyDataset\Core\Row Return the TOKEN or false if dont.
     */
    public function createAuthToken(
        $login,
        $password,
        $serverUri,
        $secret,
        $expires = 1200,
        $updateUserInfo = [],
        $updateTokenInfo = []
    );

    /**
     * Check if the Auth Token is valid
     *
     * @param string $login
     * @param string $uri
     * @param string $secret
     * @param string $token
     * @return bool
     */
    public function isValidToken($login, $uri, $secret, $token);

    /**
     * @return UserDefinition Description
     */
    public function getUserDefinition();

    /**
     * @return UserPropertiesDefinition Description
     */
    public function getUserPropertiesDefinition();

    /**
     * @param $userid
     * @return void
     */
    public function removeUserById($userid);
}
