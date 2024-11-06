<?php

namespace Tests;

use ByJG\Authenticate\Definition\PasswordDefinition;
use PHPUnit\Framework\TestCase;

class PasswordDefinitionTest extends TestCase
{
    protected $defaultRules = [
        PasswordDefinition::MINIMUM_CHARS => 8,
        PasswordDefinition::REQUIRE_UPPERCASE => 0,  // Number of uppercase characters
        PasswordDefinition::REQUIRE_LOWERCASE => 1,  // Number of lowercase characters
        PasswordDefinition::REQUIRE_SYMBOLS => 0,    // Number of symbols
        PasswordDefinition::REQUIRE_NUMBERS => 1,    // Number of numbers
        PasswordDefinition::ALLOW_WHITESPACE => 0,   // Allow whitespace
        PasswordDefinition::ALLOW_SEQUENTIAL => 0,   // Allow sequential characters
        PasswordDefinition::ALLOW_REPEATED => 0      // Allow repeated characters
    ];

    public function test__construct()
    {
        // Create Empty Password Definition
        $passwordDefinition = new PasswordDefinition();
        $this->assertEquals($this->defaultRules, $passwordDefinition->getRules());
    }

    public function testSetRule()
    {
        $passwordDefinition = new PasswordDefinition();
        $passwordDefinition->setRule(PasswordDefinition::MINIMUM_CHARS, 10);
        $this->assertEquals(10, $passwordDefinition->getRule(PasswordDefinition::MINIMUM_CHARS));
    }

    public function testSetRuleInvalid()
    {
        $this->expectException(\InvalidArgumentException::class);
        $passwordDefinition = new PasswordDefinition();
        $passwordDefinition->setRule('invalid', 10);
    }

    public function testGetRule()
    {
        $passwordDefinition = new PasswordDefinition();
        $this->assertEquals(8, $passwordDefinition->getRule(PasswordDefinition::MINIMUM_CHARS));
    }

    public function testGetRuleInvalid()
    {
        $this->expectException(\InvalidArgumentException::class);
        $passwordDefinition = new PasswordDefinition();
        $passwordDefinition->getRule('invalid');
    }

    public function testMatchPasswordMinimumChars()
    {
        $passwordDefinition = new PasswordDefinition([
            PasswordDefinition::MINIMUM_CHARS => 8,
            PasswordDefinition::REQUIRE_UPPERCASE => 0,  // Number of uppercase characters
            PasswordDefinition::REQUIRE_LOWERCASE => 0,  // Number of lowercase characters
            PasswordDefinition::REQUIRE_SYMBOLS => 0,    // Number of symbols
            PasswordDefinition::REQUIRE_NUMBERS => 0,    // Number of numbers
            PasswordDefinition::ALLOW_WHITESPACE => 1,   // Allow whitespace
            PasswordDefinition::ALLOW_SEQUENTIAL => 1,   // Allow sequential characters
            PasswordDefinition::ALLOW_REPEATED => 1      // Allow repeated characters
        ]);
        $this->assertEquals(PasswordDefinition::FAIL_MINIMUM_CHARS, $passwordDefinition->matchPassword('1234567'));
        $this->assertEquals(PasswordDefinition::SUCCESS, $passwordDefinition->matchPassword('12345678'));
    }

    public function testMatchPasswordUppercase()
    {
        $passwordDefinition = new PasswordDefinition([
            PasswordDefinition::MINIMUM_CHARS => 8,
            PasswordDefinition::REQUIRE_UPPERCASE => 2,  // Number of uppercase characters
            PasswordDefinition::REQUIRE_LOWERCASE => 0,  // Number of lowercase characters
            PasswordDefinition::REQUIRE_SYMBOLS => 0,    // Number of symbols
            PasswordDefinition::REQUIRE_NUMBERS => 0,    // Number of numbers
            PasswordDefinition::ALLOW_WHITESPACE => 1,   // Allow whitespace
            PasswordDefinition::ALLOW_SEQUENTIAL => 1,   // Allow sequential characters
            PasswordDefinition::ALLOW_REPEATED => 1      // Allow repeated characters
        ]);
        $this->assertEquals(PasswordDefinition::FAIL_UPPERCASE, $passwordDefinition->matchPassword('12345678'));
        $this->assertEquals(PasswordDefinition::FAIL_UPPERCASE, $passwordDefinition->matchPassword('12345678A'));
        $this->assertEquals(PasswordDefinition::SUCCESS, $passwordDefinition->matchPassword('1234567BA'));
    }

    public function testMatchPasswordLowercase()
    {
        $passwordDefinition = new PasswordDefinition([
            PasswordDefinition::MINIMUM_CHARS => 8,
            PasswordDefinition::REQUIRE_UPPERCASE => 0,  // Number of uppercase characters
            PasswordDefinition::REQUIRE_LOWERCASE => 2,  // Number of lowercase characters
            PasswordDefinition::REQUIRE_SYMBOLS => 0,    // Number of symbols
            PasswordDefinition::REQUIRE_NUMBERS => 0,    // Number of numbers
            PasswordDefinition::ALLOW_WHITESPACE => 1,   // Allow whitespace
            PasswordDefinition::ALLOW_SEQUENTIAL => 1,   // Allow sequential characters
            PasswordDefinition::ALLOW_REPEATED => 1      // Allow repeated characters
        ]);
        $this->assertEquals(PasswordDefinition::FAIL_LOWERCASE, $passwordDefinition->matchPassword('12345678'));
        $this->assertEquals(PasswordDefinition::FAIL_LOWERCASE, $passwordDefinition->matchPassword('12345678a'));
        $this->assertEquals(PasswordDefinition::SUCCESS, $passwordDefinition->matchPassword('1234567ba'));
    }

    public function testMatchPasswordSymbols()
    {
        $passwordDefinition = new PasswordDefinition([
            PasswordDefinition::MINIMUM_CHARS => 8,
            PasswordDefinition::REQUIRE_UPPERCASE => 0,  // Number of uppercase characters
            PasswordDefinition::REQUIRE_LOWERCASE => 0,  // Number of lowercase characters
            PasswordDefinition::REQUIRE_SYMBOLS => 2,    // Number of symbols
            PasswordDefinition::REQUIRE_NUMBERS => 0,    // Number of numbers
            PasswordDefinition::ALLOW_WHITESPACE => 1,   // Allow whitespace
            PasswordDefinition::ALLOW_SEQUENTIAL => 1,   // Allow sequential characters
            PasswordDefinition::ALLOW_REPEATED => 1      // Allow repeated characters
        ]);
        $this->assertEquals(PasswordDefinition::FAIL_SYMBOLS, $passwordDefinition->matchPassword('12345678'));
        $this->assertEquals(PasswordDefinition::FAIL_SYMBOLS, $passwordDefinition->matchPassword('12345678!'));
        $this->assertEquals(PasswordDefinition::SUCCESS, $passwordDefinition->matchPassword('1234567!!'));
    }

    public function testMatchPasswordNumbers()
    {
        $passwordDefinition = new PasswordDefinition([
            PasswordDefinition::MINIMUM_CHARS => 8,
            PasswordDefinition::REQUIRE_UPPERCASE => 0,  // Number of uppercase characters
            PasswordDefinition::REQUIRE_LOWERCASE => 0,  // Number of lowercase characters
            PasswordDefinition::REQUIRE_SYMBOLS => 0,    // Number of symbols
            PasswordDefinition::REQUIRE_NUMBERS => 2,    // Number of numbers
            PasswordDefinition::ALLOW_WHITESPACE => 1,   // Allow whitespace
            PasswordDefinition::ALLOW_SEQUENTIAL => 1,   // Allow sequential characters
            PasswordDefinition::ALLOW_REPEATED => 1      // Allow repeated characters
        ]);
        $this->assertEquals(PasswordDefinition::FAIL_NUMBERS, $passwordDefinition->matchPassword('abcdefgh'));
        $this->assertEquals(PasswordDefinition::FAIL_NUMBERS, $passwordDefinition->matchPassword('abcdefg1'));
        $this->assertEquals(PasswordDefinition::SUCCESS, $passwordDefinition->matchPassword('abcdef11'));
    }

    public function testMatchPasswordWhitespace()
    {
        $passwordDefinition = new PasswordDefinition([
            PasswordDefinition::MINIMUM_CHARS => 8,
            PasswordDefinition::REQUIRE_UPPERCASE => 0,  // Number of uppercase characters
            PasswordDefinition::REQUIRE_LOWERCASE => 0,  // Number of lowercase characters
            PasswordDefinition::REQUIRE_SYMBOLS => 0,    // Number of symbols
            PasswordDefinition::REQUIRE_NUMBERS => 0,    // Number of numbers
            PasswordDefinition::ALLOW_WHITESPACE => 0,   // Allow whitespace
            PasswordDefinition::ALLOW_SEQUENTIAL => 1,   // Allow sequential characters
            PasswordDefinition::ALLOW_REPEATED => 1      // Allow repeated characters
        ]);
        $this->assertEquals(PasswordDefinition::FAIL_WHITESPACE, $passwordDefinition->matchPassword('1234 678'));
    }

    public function testMatchPasswordSequential()
    {
        $passwordDefinition = new PasswordDefinition([
            PasswordDefinition::MINIMUM_CHARS => 8,
            PasswordDefinition::REQUIRE_UPPERCASE => 0,  // Number of uppercase characters
            PasswordDefinition::REQUIRE_LOWERCASE => 0,  // Number of lowercase characters
            PasswordDefinition::REQUIRE_SYMBOLS => 0,    // Number of symbols
            PasswordDefinition::REQUIRE_NUMBERS => 0,    // Number of numbers
            PasswordDefinition::ALLOW_WHITESPACE => 1,   // Allow whitespace
            PasswordDefinition::ALLOW_SEQUENTIAL => 0,   // Allow sequential characters
            PasswordDefinition::ALLOW_REPEATED => 1      // Allow repeated characters
        ]);
        $this->assertEquals(PasswordDefinition::FAIL_SEQUENTIAL, $passwordDefinition->matchPassword('123asdkls'));     // 123 is sequential
        $this->assertEquals(PasswordDefinition::FAIL_SEQUENTIAL, $passwordDefinition->matchPassword('sds456sks'));     // 456 is sequential
        $this->assertEquals(PasswordDefinition::FAIL_SEQUENTIAL, $passwordDefinition->matchPassword('aju654sks'));     // 654 is sequential
        $this->assertEquals(PasswordDefinition::FAIL_SEQUENTIAL, $passwordDefinition->matchPassword('791fghkalal'));   // fgh is sequential
        $this->assertEquals(PasswordDefinition::SUCCESS, $passwordDefinition->matchPassword('diykdsn132'));
    }

    public function testMatchCharsRepeated()
    {
        $passwordDefinition = new PasswordDefinition([
            PasswordDefinition::MINIMUM_CHARS => 8,
            PasswordDefinition::REQUIRE_UPPERCASE => 0,  // Number of uppercase characters
            PasswordDefinition::REQUIRE_LOWERCASE => 0,  // Number of lowercase characters
            PasswordDefinition::REQUIRE_SYMBOLS => 0,    // Number of symbols
            PasswordDefinition::REQUIRE_NUMBERS => 0,    // Number of numbers
            PasswordDefinition::ALLOW_WHITESPACE => 1,   // Allow whitespace
            PasswordDefinition::ALLOW_SEQUENTIAL => 1,   // Allow sequential characters
            PasswordDefinition::ALLOW_REPEATED => 0      // Allow repeated characters
        ]);

        $this->assertEquals(PasswordDefinition::FAIL_REPEATED, $passwordDefinition->matchPassword('hay111oihsc'));      // 111 is repeated
        $this->assertEquals(PasswordDefinition::FAIL_REPEATED, $passwordDefinition->matchPassword('haycccoihsc'));      // ccc is repeated
        $this->assertEquals(PasswordDefinition::FAIL_REPEATED, $passwordDefinition->matchPassword('oilalalapo'));      // lalala is repeated
        $this->assertEquals(PasswordDefinition::SUCCESS, $passwordDefinition->matchPassword('hay1d11oihsc'));
    }
}