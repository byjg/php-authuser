<?php

namespace Tests;

use ByJG\Authenticate\Definition\PasswordDefinition;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UserModelTest extends TestCase
{
    /**
     * @var \ByJG\Authenticate\Model\UserModel
     */
    protected $object;

    public function setUp(): void
    {
        $this->object = new UserModel();
    }

    public function tearDown(): void
    {
        $this->object = null;
    }

    public function testUserModel()
    {
        $this->object->setUserid("10");
        $this->object->setName('John');
        $this->object->setEmail('test@example.com');
        $this->object->setPassword('secret');
        $this->object->setUsername('johnuser');
        
        $this->assertEquals(10, $this->object->getUserid());
        $this->assertEquals('John', $this->object->getName());
        $this->assertEquals('test@example.com', $this->object->getEmail());
        $this->assertEquals('secret', $this->object->getPassword());
        $this->assertEquals('johnuser', $this->object->getUsername());
    }

    public function testUserModelProperties()
    {
        $this->object->setUserid("10");
        $this->object->setName('John');
        $this->object->setEmail('test@example.com');
        $this->object->setPassword('secret');
        $this->object->setUsername('johnuser');
        $this->object->set('property1', 'value1');
        $this->object->set('property2', 'value2');
        
        $this->assertEquals(10, $this->object->getUserid());
        $this->assertEquals('John', $this->object->getName());
        $this->assertEquals('test@example.com', $this->object->getEmail());
        $this->assertEquals('secret', $this->object->getPassword());
        $this->assertEquals('johnuser', $this->object->getUsername());
        $this->assertEquals('value1', $this->object->get('property1'));
        $this->assertEquals('value2', $this->object->get('property2'));

        $this->assertEquals([
            new UserPropertiesModel('property1', 'value1'),
            new UserPropertiesModel('property2', 'value2'),
        ], $this->object->getProperties());
    }

    public function testPasswordDefinition()
    {
        $this->object->withPasswordDefinition(new PasswordDefinition([
            PasswordDefinition::MINIMUM_CHARS => 12,
            PasswordDefinition::REQUIRE_UPPERCASE => 1,  // Number of uppercase characters
            PasswordDefinition::REQUIRE_LOWERCASE => 1,  // Number of lowercase characters
            PasswordDefinition::REQUIRE_SYMBOLS => 1,    // Number of symbols
            PasswordDefinition::REQUIRE_NUMBERS => 1,    // Number of numbers
            PasswordDefinition::ALLOW_WHITESPACE => 0,   // Allow whitespace
            PasswordDefinition::ALLOW_SEQUENTIAL => 0,   // Allow sequential characters
            PasswordDefinition::ALLOW_REPEATED => 0      // Allow repeated characters
        ]));

        $this->assertEmpty($this->object->setPassword(null));
        $this->assertEmpty($this->object->setPassword(''));
        $this->assertEmpty($this->object->setPassword('!Ab18Uk*H2oU9NQ'));
    }

    public function testPasswordDefinitionError()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->object->withPasswordDefinition(new PasswordDefinition([
            PasswordDefinition::MINIMUM_CHARS => 12,
            PasswordDefinition::REQUIRE_UPPERCASE => 1,  // Number of uppercase characters
            PasswordDefinition::REQUIRE_LOWERCASE => 1,  // Number of lowercase characters
            PasswordDefinition::REQUIRE_SYMBOLS => 1,    // Number of symbols
            PasswordDefinition::REQUIRE_NUMBERS => 1,    // Number of numbers
            PasswordDefinition::ALLOW_WHITESPACE => 0,   // Allow whitespace
            PasswordDefinition::ALLOW_SEQUENTIAL => 0,   // Allow sequential characters
            PasswordDefinition::ALLOW_REPEATED => 0      // Allow repeated characters
        ]));

        $this->object->setPassword('a');
    }

}