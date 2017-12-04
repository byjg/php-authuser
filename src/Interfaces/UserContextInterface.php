<?php

namespace ByJG\Authenticate\Interfaces;

interface UserContextInterface
{
    /**
     * Get information about current context is authenticated.
     * @access public
     * @return bool Return true if authenticated; false otherwise.
     */
    public function isAuthenticated();

    /**
     * Get the authenticated user name
     * @access public
     * @return string The authenticated username if exists.
     */
    public function userInfo();

    /**
     * @param $userId
     * @param array $data
     * @return void
     */
    public function registerLogin($userId, $data = []);

    /**
     *
     * @param string $name
     * @param mixed $value
     * @throws \ByJG\Authenticate\Exception\NotAuthenticatedException
     */
    public function setSessionData($name, $value);

    /**
     *
     * @param string $name
     * @return mixed
     * @throws \ByJG\Authenticate\Exception\NotAuthenticatedException
     */
    public function getSessionData($name);

    /**
     * Make logout from XMLNuke Engine
     * @access public
     */
    public function registerLogout();
}
