<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Repository\IteratorFilter;
use ByJG\AnyDataset\Repository\SingleRow;

/**
 * IUsersBase is a Interface to Store and Retrive USERS from an AnyDataset or a DBDataset structure.
 * @package xmlnuke
 */
interface UsersInterface
{

    /**
     * @desc Save the current DataSet
     */
    function save();

    /**
     * @desc Add a new user
     * @param string $name
     * @param string $userName
     * @param string $email
     * @param string $password
     * @return bool
     */
    function addUser($name, $userName, $email, $password);

    /**
     * @desc Get the user based on a filter
     * @param IteratorFilter $filter
     * @return SingleRow if user was found; null, otherwise
     */
    function getUser($filter);

    /**
     * Enter description here...
     *
     * @param int $id
     * @return SingleRow
     */
    function getById($id);

    /**
     * @desc Get the user based on his email.
     * @param string Email to find
     * @return SingleRow if user was found; null, otherwise
     */
    function getByEmail($email);

    /**
     * @desc Get the user based on his login
     * @param string $username
     * @return SingleRow if user was found; null, otherwise
     */
    function getByUsername($username);

    /**
     * @desc Remove the user based on his login.
     * @param string $username
     * @return bool
     */
    function removeUserName($username);

    /**
     * @desc Get the SHA1 string from user password
     * @param string $password
     * @return string SHA1 encripted passwordstring
     */
    function getPasswordHash($password);

    /**
     * @desc Validate if the user and password exists in the file
     * @param string $userName
     * @param string $password
     * @return SingleRow if user was found; null, otherwise
     */
    function isValidUser($userName, $password);

    /**
     *
     * @param int $userId
     * @return bool
     */
    public function isAdmin($userId = "");

    /**
     * @desc Check if the user have rights to edit specific site.
     * @param int $userId
     * @param string $propertyName
     * @param string $value
     * @return True if have rights; false, otherwisebool
     */
    public function hasProperty($userId, $propertyName, $value);

    /**
     * @desc Return all sites from a specific user
     * @param int $userId
     * @param string $propertyName
     * @return string[] String vector with all sites
     */
    function getProperty($userId, $propertyName);

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
    public function removeProperty($userId, $propertyName, $value);

    /**
     * @desc Remove a specific site from all users
     * @param string $propertyName
     * @param string $value
     * @return bool
     */
    public function removeAllProperties($propertyName, $value);

    /**
     * Authenticate a user and create a token if it is valid
     *
     * @param string $username
     * @param string $password
     * @param array $extraInfo
     * @return \ByJG\AnyDataset\Repository\SingleRow Return the TOKEN or false if dont.
     * @throws \ByJG\Authenticate\Exception\UserNotFoundException
     */
    public function createAuthToken($username, $password, $extraInfo = []);

    /**
     * Check if the Auth Token is valid
     * 
     * @param string $token
     */
    public function isValidToken($username, $token);

    /**
     * @return UserTable Description
     */
    public function getUserTable();

    /**
     * @return CustomTable Description
     */
    public function getCustomTable();
}
