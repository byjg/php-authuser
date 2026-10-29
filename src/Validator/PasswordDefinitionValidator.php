<?php

namespace ByJG\Authenticate\Validator;

use ByJG\Authenticate\Definition\PasswordDefinition;
use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\Interface\EntityValidatorInterface;

/**
 * Checks the password of a user against the password definition on every save of the users
 * repository: a plain password outside the rules throws InvalidArgumentException, a stored
 * hash is not checked. UsersRepository sets it when it is given a PasswordDefinition.
 */
class PasswordDefinitionValidator implements EntityValidatorInterface
{
    /**
     * @param PasswordDefinition $passwordDefinition
     * @param EntityValidatorInterface|null $next The validator the model declares, still run
     */
    public function __construct(
        protected PasswordDefinition $passwordDefinition,
        protected ?EntityValidatorInterface $next = null
    ) {
    }

    #[\Override]
    public function validate(mixed $instance, bool $isInsert): void
    {
        if ($instance instanceof UserModel) {
            // Sets the password again under the definition, which checks a plain password
            $instance->withPasswordDefinition($this->passwordDefinition);
        }

        $this->next?->validate($instance, $isInsert);
    }
}
