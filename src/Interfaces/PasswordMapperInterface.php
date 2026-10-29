<?php

namespace ByJG\Authenticate\Interfaces;

use ByJG\MicroOrm\Interface\MapperFunctionInterface;

interface PasswordMapperInterface extends MapperFunctionInterface
{
    /**
     * Whether the value is a hash this mapper reads (stored as is), not a plain password.
     */
    public function isPasswordEncrypted(mixed $password): bool;

    /**
     * Whether the plain password matches the stored hash.
     */
    public function verifyPassword(string $password, string $hash): bool;

    /**
     * Whether the stored hash should be replaced with a new one, done at the next successful login.
     */
    public function needsRehash(string $hash): bool;
}