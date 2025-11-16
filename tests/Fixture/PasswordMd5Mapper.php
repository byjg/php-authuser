<?php

namespace Tests\Fixture;

use ByJG\Authenticate\Interfaces\PasswordMapperInterface;

/**
 * Custom MD5 Password Mapper for testing
 */
class PasswordMd5Mapper implements PasswordMapperInterface
{

    #[\Override]
    public function processedValue(mixed $value, mixed $instance, mixed $executor = null): mixed
    {
        // Already have an MD5 hash (32 characters)
        if ($this->isPasswordEncrypted($value)) {
            return $value;
        }

        // Leave null
        if (empty($value)) {
            return null;
        }

        // Return the MD5 hash
        return strtolower(md5($value));
    }

    public function isPasswordEncrypted(mixed $password): bool
    {
        return is_string($password) &&  strlen($password) === 32 &&  ctype_xdigit($password);
    }
}
