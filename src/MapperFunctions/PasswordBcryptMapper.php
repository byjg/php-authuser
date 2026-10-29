<?php

namespace ByJG\Authenticate\MapperFunctions;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\Authenticate\Interfaces\PasswordMapperInterface;

/**
 * Mapper function to hash passwords with bcrypt (the default on UserModel).
 *
 * It also reads the SHA-1 hashes of PasswordSha1Mapper, so a database created with it keeps
 * working: a SHA-1 hash still verifies, and UsersService::isValidUser() replaces it with a
 * bcrypt hash at the next successful login. The password column needs 60 characters.
 */
class PasswordBcryptMapper implements PasswordMapperInterface
{
    #[\Override]
    public function processedValue(mixed $value, mixed $instance, ?DatabaseExecutor $executor = null): mixed
    {
        // Already a hash (bcrypt, or SHA-1 not upgraded yet)
        if ($this->isPasswordEncrypted($value)) {
            return $value;
        }

        // Leave null
        if (empty($value)) {
            return null;
        }

        return password_hash((string)$value, PASSWORD_BCRYPT);
    }

    #[\Override]
    public function isPasswordEncrypted(mixed $password): bool
    {
        return is_string($password) && ($this->isBcrypt($password) || $this->isSha1($password));
    }

    #[\Override]
    public function verifyPassword(string $password, string $hash): bool
    {
        if ($this->isBcrypt($hash)) {
            return password_verify($password, $hash);
        }
        if ($this->isSha1($hash)) {
            return hash_equals(strtolower($hash), sha1($password));
        }
        return false;
    }

    #[\Override]
    public function needsRehash(string $hash): bool
    {
        return !$this->isBcrypt($hash) || password_needs_rehash($hash, PASSWORD_BCRYPT);
    }

    private function isBcrypt(string $hash): bool
    {
        return strlen($hash) === 60 && password_get_info($hash)['algo'] === PASSWORD_BCRYPT;
    }

    private function isSha1(string $hash): bool
    {
        return strlen($hash) === 40 && ctype_xdigit($hash);
    }
}
