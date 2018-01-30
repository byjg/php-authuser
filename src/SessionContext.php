<?php

namespace ByJG\Authenticate;

use ByJG\Authenticate\Exception\NotAuthenticatedException;
use ByJG\Authenticate\Interfaces\UserContextInterface;
use ByJG\Cache\Psr6\CachePool;

class SessionContext implements UserContextInterface
{
    /**
     *
     * @var \ByJG\Cache\Psr6\CachePool
     */
    protected $session;

    /**
     * @var string
     */
    protected $key;

    /**
     * SessionContext constructor.
     *
     * @param \ByJG\Cache\Psr6\CachePool $cachePool
     * @param string $key
     */
    public function __construct(CachePool $cachePool, $key = 'default')
    {
        $this->session = $cachePool;
        $this->key = $key;
    }

    /**
     * Get information about current context is authenticated.
     *
     * @access public
     * @return bool Return true if authenticated; false otherwise.
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function isAuthenticated()
    {
        $item = $this->session->getItem("user.{$this->key}");
        return $item->isHit();
    }

    /**
     * Get the authenticated user name
     *
     * @access public
     * @return string The authenticated username if exists.
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function userInfo()
    {
        $item = $this->session->getItem("user.{$this->key}");
        return $item->get();
    }

    /**
     * @param $userId
     * @param array $data
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function registerLogin($userId, $data = [])
    {
        $item = $this->session->getItem("user.{$this->key}");
        $item->set($userId);
        $this->session->saveDeferred($item);

        $data = $this->session->getItem("user.{$this->key}.data");
        $data->set($data);
        $this->session->saveDeferred($data);

        $this->session->commit();
    }

    /**
     * @param string $name
     * @param mixed $value
     * @throws NotAuthenticatedException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function setSessionData($name, $value)
    {
        if (!$this->isAuthenticated()) {
            throw new NotAuthenticatedException('There is no active logged user');
        }

        $item = $this->session->getItem("user.{$this->key}.data");
        $oldData = $item->get();

        if (!is_array($oldData)) {
            $oldData = [];
        }

        $oldData[$name] = $value;

        $item->set($oldData);
        $this->session->save($item);
    }

    /**
     * @param string $name
     * @return mixed
     * @throws NotAuthenticatedException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getSessionData($name)
    {
        if (!$this->isAuthenticated()) {
            throw new NotAuthenticatedException('There is no active logged user');
        }

        $item = $this->session->getItem("user.{$this->key}.data");

        if (!$item->isHit()) {
            return false;
        }

        $oldData = $item->get();
        if (isset($oldData[$name])) {
            return $oldData[$name];
        }

        return false;
    }

    /**
     * Make logout from XMLNuke Engine
     *
     * @access public
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function registerLogout()
    {
        $this->session->deleteItem("user.{$this->key}");
        $this->session->deleteItem("user.{$this->key}.data");
    }
}
