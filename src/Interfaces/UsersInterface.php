<?php

namespace ByJG\Authenticate\Interfaces;

use ByJG\AnyDataset\Core\IteratorFilter;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Util\JwtWrapper;

/**
 * IUsersBase is an Interface to Store and Retrive USERS from an AnyDataset or a DBDataset structure.
 * @package xmlnuke
 */
interface UsersInterface
{

    /**
     * @desc Save the current DataSet
     * @param UserModel $model
     * @return UserModel
     */
    public function save(UserModel $model): UserModel;

    /**
     * @desc Add a new user
     * @param string $name
     * @param string $userName
     * @param string $email
     * @param string $password
     * @return UserModel
     */
    public function addUser(string $name, string $userName, string $email, string $password): UserModel;

    /**
     * @param UserModel $model
     * @return bool
     */
    public function canAddUser(UserModel $model): bool;

    /**
     * @desc Get the user based on a filter
     * @param IteratorFilter $filter
     * @return UserModel|null if user was found; null, otherwise
     */
    public function getUser(IteratorFilter $filter): UserModel|null;

    /**
     * Enter description here...
     *
     * @param string $userid
     * @return UserModel|null
     */
    public function getById(string $userid): UserModel|null;

    /**
     * @desc Get the user based on his email.
     * @param string $email Email to find
     * @return UserModel|null if user was found; null, otherwise
     */
    public function getByEmail(string $email): UserModel|null;

    /**
     * @desc Get the user based on his username.
     * @param string $username
     * @return UserModel|null if user was found; null, otherwise
     */
    public function getByUsername(string $username): UserModel|null;

    /**
     * @desc Get the user based on his login
     * @param string $login
     * @return UserModel|null if user was found; null, otherwise
     */
    public function getByLoginField(string $login): UserModel|null;

    /**
     * @desc Remove the user based on his login.
     * @param string $login
     * @return bool
     */
    public function removeByLoginField(string $login): bool;

    /**
     * @desc Validate if the user and password exists in the file
     * @param string $userName
     * @param string $password
     * @return UserModel|null if user was found; null, otherwise
     */
    public function isValidUser(string $userName, string $password): UserModel|null;

    /**
     *
     * @param string $userId
     * @return bool
     */
    public function isAdmin(string $userId): bool;

    /**
     * @desc Check if the user have rights to edit specific site.
     * @param string $userId
     * @param string $propertyName
     * @param string|null $value
     * @return bool True, if it has the property; false, otherwisebool
     */
    public function hasProperty(string $userId, string $propertyName, string $value = null): bool;

    /**
     * @desc Return all sites from a specific user
     * @param string $userId
     * @param string $propertyName
     * @return string|array|null String vector with all sites
     */
    public function getProperty(string $userId, string $propertyName): array|string|null;

    /**
     *
     * @param string $userId
     * @param string $propertyName
     * @param string|null $value
     */
    public function addProperty(string $userId, string $propertyName, string|null $value): bool;

    /**
     *
     * @param string $userId
     * @param string $propertyName
     * @param string|null $value
     */
    public function setProperty(string $userId, string $propertyName, string|null $value): bool;

    /**
     *
     * @param string $userId
     * @param string $propertyName
     * @param string|null $value
     */
    public function removeProperty(string $userId, string $propertyName, string|null $value = null): bool;

    /**
     * @desc Remove a specific site from all users
     * @param string $propertyName
     * @param string|null $value
     * @return void
     */
    public function removeAllProperties(string $propertyName, string|null $value = null): void;

    /**
     * Authenticate a user and create a token if it is valid
     *
     * @param string $login
     * @param string $password
     * @param JwtWrapper $jwtWrapper
     * @param int $expires
     * @param array $updateUserInfo
     * @param array $updateTokenInfo
     * @return string|null Return the TOKEN or null, if can't create it.
     */
    public function createAuthToken(
        string     $login,
        string     $password,
        JwtWrapper $jwtWrapper,
        int        $expires = 1200,
        array      $updateUserInfo = [],
        array      $updateTokenInfo = []
    ): string|null;

    /**
     * Check if the Auth Token is valid
     *
     * @param string $login
     * @param JwtWrapper $jwtWrapper
     * @param string $token
     * @return array|null
     */
    public function isValidToken(string $login, JwtWrapper $jwtWrapper, string $token): array|null;

    /**
     * @return UserDefinition Description
     */
    public function getUserDefinition(): UserDefinition;

    /**
     * @return UserPropertiesDefinition Description
     */
    public function getUserPropertiesDefinition(): UserPropertiesDefinition;

    /**
     * @param string $userid
     * @return bool
     */
    public function removeUserById(string $userid): bool;
}
