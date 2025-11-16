<?php

namespace ByJG\Authenticate\Interfaces;

use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\JwtWrapper\JwtWrapper;
use ByJG\MicroOrm\Literal\Literal;

/**
 * Interface for Users Service
 */
interface UsersServiceInterface
{
    /**
     * Save a user
     *
     * @param UserModel $model
     * @return UserModel
     */
    public function save(UserModel $model): UserModel;

    /**
     * Add a new user
     *
     * @param string $name
     * @param string $userName
     * @param string $email
     * @param string $password
     * @return UserModel
     */
    public function addUser(string $name, string $userName, string $email, string $password): UserModel;

    /**
     * Get user by ID
     *
     * @param string|Literal|int $userid
     * @return UserModel|null
     */
    public function getById(string|Literal|int $userid): ?UserModel;

    /**
     * Get user by email
     *
     * @param string $email
     * @return UserModel|null
     */
    public function getByEmail(string $email): ?UserModel;

    /**
     * Get user by username
     *
     * @param string $username
     * @return UserModel|null
     */
    public function getByUsername(string $username): ?UserModel;

    /**
     * Get user by login field (email or username based on configuration)
     *
     * @param string $login
     * @return UserModel|null
     */
    public function getByLogin(string $login): ?UserModel;

    /**
     * Remove user by login field
     *
     * @param string $login
     * @return bool
     */
    public function removeByLogin(string $login): bool;

    /**
     * Remove user by ID
     *
     * @param string|Literal|int $userid
     * @return bool
     */
    public function removeById(string|Literal|int $userid): bool;

    /**
     * Validate if user and password are correct
     *
     * @param string $login
     * @param string $password
     * @return UserModel|null
     */
    public function isValidUser(string $login, string $password): ?UserModel;

    /**
     * Check if user has a property
     *
     * @param string|int|Literal $userId
     * @param string $propertyName
     * @param string|null $value
     * @return bool
     */
    public function hasProperty(string|int|Literal $userId, string $propertyName, ?string $value = null): bool;

    /**
     * Get property value(s) for a user
     *
     * @param string|Literal|int $userId
     * @param string $propertyName
     * @return array|string|UserPropertiesModel|null
     */
    public function getProperty(string|Literal|int $userId, string $propertyName): array|string|UserPropertiesModel|null;

    /**
     * Add a property to a user
     *
     * @param string|Literal|int $userId
     * @param string $propertyName
     * @param string|null $value
     * @return bool
     */
    public function addProperty(string|Literal|int $userId, string $propertyName, ?string $value): bool;

    /**
     * Set a property (replaces existing)
     *
     * @param string|Literal|int $userId
     * @param string $propertyName
     * @param string|null $value
     * @return bool
     */
    public function setProperty(string|Literal|int $userId, string $propertyName, ?string $value): bool;

    /**
     * Remove a property from a user
     *
     * @param string|Literal|int $userId
     * @param string $propertyName
     * @param string|null $value
     * @return bool
     */
    public function removeProperty(string|Literal|int $userId, string $propertyName, ?string $value = null): bool;

    /**
     * Remove a property from all users
     *
     * @param string $propertyName
     * @param string|null $value
     * @return void
     */
    public function removeAllProperties(string $propertyName, ?string $value = null): void;

    /**
     * Get users by property
     *
     * @param string $propertyName
     * @param string $value
     * @return UserModel[]
     */
    public function getUsersByProperty(string $propertyName, string $value): array;

    /**
     * Get users by multiple properties
     *
     * @param array $propertiesArray
     * @return UserModel[]
     */
    public function getUsersByPropertySet(array $propertiesArray): array;

    /**
     * Create authentication token
     *
     * @param string $login
     * @param string $password
     * @param JwtWrapper $jwtWrapper
     * @param int $expires
     * @param array $updateUserInfo
     * @param array $updateTokenInfo
     * @param array $tokenUserFields
     * @return string|null
     */
    public function createAuthToken(
        string     $login,
        string     $password,
        JwtWrapper $jwtWrapper,
        int        $expires = 1200,
        array      $updateUserInfo = [],
        array      $updateTokenInfo = [],
        array      $tokenUserFields = []
    ): ?string;

    /**
     * Validate authentication token
     *
     * @param string $login
     * @param JwtWrapper $jwtWrapper
     * @param string $token
     * @return array|null
     */
    public function isValidToken(string $login, JwtWrapper $jwtWrapper, string $token): ?array;

    public function getUsersEntity(array $fields): UserModel;
}
