<?php

namespace Tests\Fixture;

use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\Interface\EntityValidatorInterface;
use RuntimeException;

/**
 * A validator a project declares on its user model
 */
class BlockedNameValidator implements EntityValidatorInterface
{
    #[\Override]
    public function validate(mixed $instance, bool $isInsert): void
    {
        if ($instance instanceof UserModel && $instance->getName() === 'Blocked') {
            throw new RuntimeException('Blocked name');
        }
    }
}
