<?php

namespace ByJG\Authenticate\MapperFunctions;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\Authenticate\Interfaces\PasswordMapperInterface;

/**
 * Mapper function to hash passwords using SHA1
 */
class PasswordSha1Mapper implements PasswordMapperInterface
{
    #[\Override]
    public function processedValue(mixed $value, mixed $instance, ?DatabaseExecutor $executor = null): mixed
    {
        // Already have a SHA1 password (40 characters)
        if ($this->isPasswordEncrypted($value)) {
            return $value;
        }

        // Leave null
        if (empty($value)) {
            return null;
        }

        // Return the hash password
        return strtolower(sha1($value));
    }

    #[\Override]
    public function isPasswordEncrypted(mixed $password): bool
    {
        return (is_string($password) && strlen($password) === 40);
    }
}
