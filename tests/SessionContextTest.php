<?php

namespace ByJG\Authenticate;

use ByJG\Cache\Factory;

// backward compatibility

if (!class_exists('\PHPUnit\Framework\TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', '\PHPUnit\Framework\TestCase');
}

class SessionContextTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \ByJG\Authenticate\Interfaces\UserContextInterface
     */
    protected $object;
    
    public function setUp()
    {
        $this->object = new SessionContext(Factory::createSessionPool());
    }
    
    public function tearDown()
    {
        $this->object = null;
    }
    
    public function testUserContext()
    {
        $this->assertFalse($this->object->isAuthenticated());

        $this->object->registerLogin(10);

        $this->assertEquals(10, $this->object->userInfo());
        $this->assertTrue($this->object->isAuthenticated());

        $this->object->setSessionData('property1', 'value1');
        $this->object->setSessionData('property2', 'value2');

        $this->assertEquals('value1', $this->object->getSessionData('property1'));
        $this->assertEquals('value2', $this->object->getSessionData('property2'));

        $this->object->registerLogout();

        $this->assertFalse($this->object->isAuthenticated());
    }

    /**
     * @expectedException \ByJG\Authenticate\Exception\NotAuthenticatedException
     */
    public function testUserContextNotActiveSession()
    {
        $this->assertEmpty($this->object->getSessionData('property1'));
    }


}
