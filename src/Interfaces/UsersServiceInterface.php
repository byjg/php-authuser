<?php

namespace ByJG\Authenticate\Interfaces;

use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\Authenticate\Model\UserToken;
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
     * @param string|null $role
     * @return UserModel
     */
    public function addUser(string $name, string $userName, string $email, string $password, ?string $role = null): UserModel;

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
     * Replace the user's stored hash when the password mapper says so (a SHA-1 hash, or bcrypt with
     * a lower cost than the current one), now that the plain password is known. createAuthToken()
     * calls it at login; call it after isValidUser() in a login of your own.
     *
     * The new hash is read back: if it was not stored (a password column too short for it), the
     * old hash is kept and the method returns false, so the user can still log in.
     *
     * @param UserModel $user A user isValidUser() returned for this password
     * @param string $password The plain password
     * @return bool Whether the hash was replaced
     */
    public function rehashPasswordIfNeeded(UserModel $user, string $password): bool;

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
     * @return UserToken|null
     */
    public function createAuthToken(
        string     $login,
        string     $password,
        JwtWrapper $jwtWrapper,
        int        $expires = 1200,
        array      $updateUserInfo = [],
        array      $updateTokenInfo = [],
        array      $tokenUserFields = []
    ): ?UserToken;

    public function createInsecureAuthToken(
        UserModel|string    $login,
        JwtWrapper          $jwtWrapper,
        int                 $expires = 1200,
        array               $updateUserInfo = [],
        array               $updateTokenInfo = [],
        array               $tokenUserFields = []
    ): ?UserToken;

    /**
     * Validate authentication token
     *
     * @param string $login
     * @param JwtWrapper $jwtWrapper
     * @param string $token
     * @return UserToken
     */
    public function isValidToken(string $login, JwtWrapper $jwtWrapper, string $token): UserToken;

    public function getUsersEntity(array $fields): UserModel;
}
