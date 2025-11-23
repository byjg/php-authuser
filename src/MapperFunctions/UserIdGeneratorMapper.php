<?php

namespace ByJG\Authenticate\MapperFunctions;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\Interface\MapperFunctionInterface;

/**
 * Mapper function to generate user ID from username if not set
 */
class UserIdGeneratorMapper implements MapperFunctionInterface
{
    #[\Override]
    public function processedValue(mixed $value, mixed $instance, ?DatabaseExecutor $executor = null): mixed
    {
        // If value is already set, use it
        if (!empty($value)) {
            return $value;
        }

        // Generate from username if instance is UserModel
        if ($instance instanceof UserModel) {
            $username = $instance->getUsername();
            if ($username !== null) {
                return preg_replace('/(?:([\w])|([\W]))/', '\1', strtolower($username));
            }
        }

        return $value;
    }
}
