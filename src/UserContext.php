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
	public function registerLogin($user, $usersInstance, $key = 'default')
	{
        if (!is_array($user)) {
            throw new \InvalidArgumentException('User need to be an array');
        }

        if (!isset($user[$usersInstance->getUserTable()->id]) || !isset($user[$usersInstance->getUserTable()->username])) {
            throw new \InvalidArgumentException('Array is not a valid user data');
        }

        unset($user[$usersInstance->getUserTable()->password]);
        unset($user[$usersInstance->getUserTable()->admin]);

        $this->session->set("user.$key", $user);
	}

	/**
	* Make logout from XMLNuke Engine
	* @access public
	* @return void
	*/
	public function registerLogout($key = 'default')
	{
		$this->session->release("user.$key");

        if ($this->session instanceof ByJG\Cache\SessionCacheEngine)
        {
            session_unset();
        }
	}
}
