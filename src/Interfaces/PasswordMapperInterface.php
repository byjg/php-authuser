<?php

namespace ByJG\Authenticate\Interfaces;

use ByJG\MicroOrm\Interface\MapperFunctionInterface;

interface PasswordMapperInterface extends MapperFunctionInterface
{
    public function isPasswordEncrypted(mixed $password): bool;
}