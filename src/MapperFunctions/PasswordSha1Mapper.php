<?php

namespace ByJG\Authenticate\MapperFunctions;

use ByJG\AnyDataset\Db\DbFunctionsInterface;
use ByJG\MicroOrm\Interface\MapperFunctionInterface;

/**
 * Mapper function to hash passwords using SHA1
 */
class PasswordSha1Mapper implements MapperFunctionInterface
{
    public function processedValue(mixed $value, mixed $instance, ?DbFunctionsInterface $helper = null): mixed
    {
        // Already have a SHA1 password (40 characters)
        if (is_string($value) && strlen($value) === 40) {
            return $value;
        }

        // Leave null
        if (empty($value)) {
            return null;
        }

        // Return the hash password
        return strtolower(sha1($value));
    }
}
