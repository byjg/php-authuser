<?php

namespace ByJG\Authenticate;

use ByJG\Authenticate\Exception\NotAuthenticatedException;
use ByJG\Authenticate\Interfaces\UserContextInterface;
use ByJG\Cache\Psr6\CachePool;
use Override;
use Psr\SimpleCache\InvalidArgumentException;

class SessionContext implements UserContextInterface
{
    /**
     *
     * @var CachePool
     */
    protected CachePool $session;

    /**
     * @var string
     */
    protected string $key;

    /**
     * SessionContext constructor.
     *
     * @param CachePool $cachePool
     * @param string $key
     */
    public function __construct(CachePool $cachePool, string $key = 'default')
    {
        $this->session = $cachePool;
        $this->key = $key;
    }

    /**
     * Get information about current context is authenticated.
     *
     * @access public
     * @return bool Return true if authenticated; false otherwise.
     * @throws InvalidArgumentException
     */
    #[Override]
    public function isAuthenticated(): bool
    {
        $item = $this->session->getItem("user.$this->key");
        return $item->isHit();
    }

    /**
     * Get the authenticated username
     *
     * @access public
     * @return string|int The authenticated username if exists.
     * @throws InvalidArgumentException
     */
    #[Override]
    public function userInfo(): string|int
    {
        $item = $this->session->getItem("user.$this->key");
        return $item->get();
    }

    /**
     * @param string|int $userId
     * @param array $data
     * @throws InvalidArgumentException
     */
    #[Override]
    public function registerLogin(string|int $userId, array $data = []): void
    {
        $item = $this->session->getItem("user.$this->key");
        $item->set($userId);
        $this->session->saveDeferred($item);

        $data = $this->session->getItem("user.$this->key.data");
        $data->set($data);
        $this->session->saveDeferred($data);

        $this->session->commit();
    }

    /**
     * @param string $name
     * @param mixed $value
     * @throws NotAuthenticatedException
     * @throws InvalidArgumentException
     * @throws InvalidArgumentException
     * @throws InvalidArgumentException
     */
    #[Override]
    public function setSessionData(string $name, mixed $value): void
    {
        if (!$this->isAuthenticated()) {
            throw new NotAuthenticatedException('There is no active logged user');
        }

        $item = $this->session->getItem("user.$this->key.data");
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
     * @throws InvalidArgumentException
     * @throws InvalidArgumentException
     */
    #[Override]
    public function getSessionData(string $name): mixed
    {
        if (!$this->isAuthenticated()) {
            throw new NotAuthenticatedException('There is no active logged user');
        }

        $item = $this->session->getItem("user.$this->key.data");

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
     * @throws InvalidArgumentException
     * @throws InvalidArgumentException
     */
    #[Override]
    public function registerLogout(): void
    {
        $this->session->deleteItem("user.$this->key");
        $this->session->deleteItem("user.$this->key.data");
    }
}
