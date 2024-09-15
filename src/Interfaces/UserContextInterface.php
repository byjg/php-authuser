<?php

namespace ByJG\Authenticate\Interfaces;

use ByJG\Authenticate\Exception\NotAuthenticatedException;

interface UserContextInterface
{
    /**
     * Get information about current context is authenticated.
     * @access public
     * @return bool Return true if authenticated; false otherwise.
     */
    public function isAuthenticated(): bool;

    /**
     * Get the authenticated username
     * @access public
     * @return string|int The authenticated username if exists.
     */
    public function userInfo(): string|int;

    /**
     * @param string|int $userId
     * @param array $data
     * @return void
     */
    public function registerLogin(string|int $userId, array $data = []): void;

    /**
     *
     * @param string $name
     * @param mixed $value
     * @throws NotAuthenticatedException
     */
    public function setSessionData(string $name, mixed $value): void;

    /**
     *
     * @param string $name
     * @return mixed
     * @throws NotAuthenticatedException
     */
    public function getSessionData(string $name): mixed;

    /**
     * Make logout from XMLNuke Engine
     * @access public
     */
    public function registerLogout(): void;
}
