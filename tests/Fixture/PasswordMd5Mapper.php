<?php

namespace Tests\Fixture;

use ByJG\MicroOrm\Interface\MapperFunctionInterface;

/**
 * Custom MD5 Password Mapper for testing
 */
class PasswordMd5Mapper implements MapperFunctionInterface
{
    #[\Override]
    public function processedValue(mixed $value, mixed $instance, mixed $executor = null): mixed
    {
        // Already have an MD5 hash (32 characters)
        if (is_string($value) && strlen($value) === 32 && ctype_xdigit($value)) {
            return $value;
        }

        // Leave null
        if (empty($value)) {
            return null;
        }

        // Return the MD5 hash
        return strtolower(md5($value));
    }
}
