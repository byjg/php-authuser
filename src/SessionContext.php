<?php

namespace ByJG\Authenticate;

use ByJG\Authenticate\Exception\NotAuthenticatedException;
use ByJG\Cache\CacheEngineInterface;
use ByJG\Cache\Engine\SessionCacheEngine;

class SessionContext
{

    const SESSION_PREFIX = 'authuserpackage';

    /**
     *
     * @var CacheEngineInterface
     */
    protected $session;

    public function __construct()
    {
        $this->session = new SessionCacheEngine(self::SESSION_PREFIX);
    }

    /**
     * Get information about current context is authenticated.
     * @access public
     * @param string $key
     * @return bool Return true if authenticated; false otherwise.
     */
    public function isAuthenticated($key = 'default')
    {
        return !empty($this->session->get("user.$key"));
    }

    /**
     * Get the authenticated user name
     * @access public
     * @param string $key
     * @return string The authenticated username if exists.
     */
    public function userInfo($key = 'default')
    {
        return $this->session->get("user.$key");
    }

    /**
     *
     * @param $userId
     * @param string $key
     */
    public function registerLogin($userId, $key = 'default')
    {
        $this->session->set("user.$key", $userId);
    }

    /**
     *
     * @param string $name
     * @param mixed $value
     * @param string $key
     * @throws NotAuthenticatedException
     */
    public function setSessionData($name, $value, $key = 'default')
    {
        if (!$this->isAuthenticated($key)) {
            throw new NotAuthenticatedException('There is no active logged user');
        }

        $oldData = $this->session->get("user.$key.data");

        if (!is_array($oldData)) {
            $oldData = [];
        }

        $oldData[$name] = $value;

        $this->session->set("user.$key.data", $oldData);
    }

    /**
     *
     * @param string $name
     * @param string $key
     * @return mixed
     * @throws NotAuthenticatedException
     */
    public function getSessionData($name, $key = 'default')
    {
        if (!$this->isAuthenticated($key)) {
            throw new NotAuthenticatedException('There is no active logged user');
        }

        $oldData = $this->session->get("user.$key.data");

        if (!is_array($oldData)) {
            return false;
        }
        if (isset($oldData[$name])) {
            return $oldData[$name];
        }

        return false;
    }

    /**
     * Make logout from XMLNuke Engine
     * @access public
     * @param string $key
     */
    public function registerLogout($key = 'default')
    {
        $this->session->release("user.$key");
        $this->session->release("user.$key.data");

        if ($this->session instanceof SessionCacheEngine) {
            session_unset();
        }
    }
}
