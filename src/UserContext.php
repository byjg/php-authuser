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
        } catch (Exception $ex) {
            $this->session = new ByJG\Cache\SessionCacheEngine();
            $this->configKey = self::SESSION_PREFIX;
        }
    }

	/**
	* Get information about current context is authenticated.
	* @access public
	* @return bool Return true if authenticated; false otherwise.
	*/
	public function isAuthenticated()
	{
		return $this->session->get('user') !== false;
	}

	/**
	* Get the authenticated user name
	* @access public
	* @return string The authenticated username if exists.
	*/
	public function userInfo()
	{
        return $this->session->get('user');
	}

	/**
	* Make login in XMLNuke Engine
	* @access public
	* @param string $user
	* @return void
	*/
	public function registerLogin($user)
	{
        $this->session->set('user', $user);
	}

	/**
	* Make logout from XMLNuke Engine
	* @access public
	* @return void
	*/
	public function registerLogout()
	{
		$this->session->release('user');

        if ($this->session instanceof ByJG\Cache\SessionCacheEngine)
        {
            session_unset();
        }
	}
}
