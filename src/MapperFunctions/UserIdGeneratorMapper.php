<?php

namespace ByJG\Authenticate\MapperFunctions;

use ByJG\AnyDataset\Db\DbFunctionsInterface;
use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\Interface\MapperFunctionInterface;

/**
 * Mapper function to generate user ID from username if not set
 */
class UserIdGeneratorMapper implements MapperFunctionInterface
{
    public function processedValue(mixed $value, mixed $instance, ?DbFunctionsInterface $helper = null): mixed
    {
        // If value is already set, use it
        if (!empty($value)) {
            return $value;
        }

        // Generate from username if instance is UserModel
        if ($instance instanceof UserModel) {
            return preg_replace('/(?:([\w])|([\W]))/', '\1', strtolower($instance->getUsername()));
        }

        return $value;
    }
}
