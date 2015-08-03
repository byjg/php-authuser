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
            $this->configKey = self::SESSION_PREFIX;
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
	* Make login in XMLNuke Engine
	* @access public
	* @param string $user
	* @return void
	*/
	public function registerLogin($user, $key = 'default')
	{
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
