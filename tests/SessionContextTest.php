<?php

namespace ByJG\Authenticate;

use ByJG\Cache\Factory;
use PHPUnit\Framework\TestCase;

class SessionContextTest extends TestCase
{
    /**
     * @var \ByJG\Authenticate\Interfaces\UserContextInterface
     */
    protected $object;
    
    public function setUp(): void
    {
        $this->object = new SessionContext(Factory::createSessionPool());
    }
    
    public function tearDown(): void
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

    public function testUserContextNotActiveSession()
    {
        $this->expectException(\ByJG\Authenticate\Exception\NotAuthenticatedException::class);
        $this->assertEmpty($this->object->getSessionData('property1'));
    }

    public function testUserContextNotActive2Session()
    {
        $this->expectException(\ByJG\Authenticate\Exception\NotAuthenticatedException::class);
        $this->object->setSessionData('property1', 'value');
    }
}
