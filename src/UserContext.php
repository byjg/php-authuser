<?php


namespace ByJG\Authenticate;

class UserContext
{
    use \ByJG\DesignPattern\Singleton;


    const SESSION_PREFIX = 'authuserpackage';

    /**
     *
     * @var ByJG\Cache\CacheEngineInterface
     */
    protected $session;

    protected function __construct()
    {
        try {
            $this->session = \ByJG\Cache\CacheContext::factory(self::SESSION_PREFIX);
        } catch (\Exception $ex) {
            $this->session = new \ByJG\Cache\SessionCacheEngine();
            $this->session->configKey = self::SESSION_PREFIX;
        }
    }

	/**
	* Get information about current context is authenticated.
	* @access public
	* @return bool Return true if authenticated; false otherwise.
	*/
	public function isAuthenticated($key = 'default')
	{
		return $this->session->get("user.$key") !== false;
	}

	/**
	* Get the authenticated user name
	* @access public
	* @return string The authenticated username if exists.
	*/
	public function userInfo($key = 'default')
	{
        return $this->session->get("user.$key");
	}

    /**
     *
     * @param string $user
     * @param UsersInterface $usersInstance
     * @param string $key
     * @throws \InvalidArgumentException
     */
	public function registerLogin($userId, $key = 'default')
	{
        $this->session->set("user.$key", $userId);
	}

    public function setSessionData($name, $value, $key = 'default')
    {
        $oldData = $this->session->get("user.$key.data");

        if (!is_array($oldData)) {
            $oldData = [];
        }

        $oldData[$name] = $value;

        $this->session->set("user.$key.data", $oldData);
    }

    public function getSessionData($name, $key = 'default')
    {
        $oldData = $this->session->get("user.$key.data");

        if (!is_array($oldData)) {
            return false;
        }
        if (isset($oldData['name'])) {
            return $oldData['name'];
        }

        return false;
    }

	/**
	* Make logout from XMLNuke Engine
	* @access public
	* @return void
	*/
	public function registerLogout($key = 'default')
	{
		$this->session->release("user.$key");
		$this->session->release("user.$key.data");

        if ($this->session instanceof ByJG\Cache\SessionCacheEngine)
        {
            session_unset();
        }
	}
}
