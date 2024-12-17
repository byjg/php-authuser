<?php

namespace ByJG\Authenticate\Definition;

use InvalidArgumentException;

class PasswordDefinition
{
    const MINIMUM_CHARS = "minimum_chars";
    const REQUIRE_UPPERCASE = "require_uppercase";
    const REQUIRE_LOWERCASE = "require_lowercase";
    const REQUIRE_SYMBOLS = "require_symbols";
    const REQUIRE_NUMBERS = "require_numbers";
    const ALLOW_WHITESPACE = "allow_whitespace";
    const ALLOW_SEQUENTIAL = "allow_sequential";
    const ALLOW_REPEATED = "allow_repeated";

    protected array $rules = [];

    public function __construct($rules = null)
    {
        $this->rules = [
            self::MINIMUM_CHARS => 8,
            self::REQUIRE_UPPERCASE => 0,  // Number of uppercase characters
            self::REQUIRE_LOWERCASE => 1,  // Number of lowercase characters
            self::REQUIRE_SYMBOLS => 0,    // Number of symbols
            self::REQUIRE_NUMBERS => 1,    // Number of numbers
            self::ALLOW_WHITESPACE => 0,   // Allow whitespace
            self::ALLOW_SEQUENTIAL => 0,   // Allow sequential characters
            self::ALLOW_REPEATED => 0      // Allow repeated characters
        ];
        foreach ((array)$rules as $rule => $value) {
            $this->setRule($rule, $value);
        }
    }

    public function setRule(string $rule, string|bool|int $value): void
    {
        if (!array_key_exists($rule, $this->rules)) {
            throw new InvalidArgumentException("Invalid rule");
        }
        $this->rules[$rule] = $value;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function getRule($rule): string|bool|int
    {
        if (!array_key_exists($rule, $this->rules)) {
            throw new InvalidArgumentException("Invalid rule");
        }
        return $this->rules[$rule];
    }

    const SUCCESS = 0;
    const FAIL_MINIMUM_CHARS = 1;
    const FAIL_UPPERCASE = 2;
    const FAIL_LOWERCASE = 4;
    const FAIL_SYMBOLS = 8;
    const FAIL_NUMBERS = 16;
    const FAIL_WHITESPACE = 32;
    const FAIL_SEQUENTIAL = 64;
    const FAIL_REPEATED = 128;

    public function matchPassword(string $password): int
    {
        $result = 0;

        // match password against the rules
        if (strlen($password) < $this->rules[self::MINIMUM_CHARS]) {
            $result |= PasswordDefinition::FAIL_MINIMUM_CHARS;
        }
        if ($this->rules[self::REQUIRE_UPPERCASE] > 0) {
            if (preg_match_all('/[A-Z]/', $password, $matches) < $this->rules[self::REQUIRE_UPPERCASE]) {
                $result |= PasswordDefinition::FAIL_UPPERCASE;
            }
        }
        if ($this->rules[self::REQUIRE_LOWERCASE] > 0) {
            if (preg_match_all('/[a-z]/', $password, $matches) < $this->rules[self::REQUIRE_LOWERCASE]) {
                $result |= PasswordDefinition::FAIL_LOWERCASE;
            }
        }
        if ($this->rules[self::REQUIRE_SYMBOLS] > 0) {
            if (preg_match_all('/[!@#$%^&*()\-_=+{};:,<.>]/', $password, $matches) < $this->rules[self::REQUIRE_SYMBOLS]) {
                $result |= PasswordDefinition::FAIL_SYMBOLS;
            }
        }
        if ($this->rules[self::REQUIRE_NUMBERS] > 0) {
            if (preg_match_all('/[0-9]/', $password, $matches) < $this->rules[self::REQUIRE_NUMBERS]) {
                $result |= PasswordDefinition::FAIL_NUMBERS;
            }
        }
        if ($this->rules[self::ALLOW_WHITESPACE] == 0) {
            if (preg_match_all('/\s/', $password, $matches) > 0) {
                $result |= PasswordDefinition::FAIL_WHITESPACE;
            }
        }
        if ($this->rules[self::ALLOW_SEQUENTIAL] == 0) {
            if (preg_match_all('/([aA][bB][cC]|[bB][cC][dD]|[cC][dD][eE]|[dD][eE][fF]|[eE][fF][gG]|[fF][gG][hH]|[gG][hH][iI]|[hH][iI][jJ]|[iI][jJ][kK]|[jJ][kK][lL]|[kK][lL][mM]|[lL][mM][nN]|[mM][nN][oO]|[nN][oO][pP]|[oO][pP][qQ]|[pP][qQ][rR]|[qQ][rR][sS]|[rR][sS][tT]|[sS][tT][uU]|[tT][uU][vV]|[uU][vV][wW]|[vV][wW][xX]|[wW][xX][yY]|[xX][yY][zZ]|012|123|234|345|456|567|678|789|890|987|876|765|654|543|432|321)/', $password, $matches) > 0) {
                $result |= PasswordDefinition::FAIL_SEQUENTIAL;
            }

        }
        if ($this->rules[self::ALLOW_REPEATED] == 0) {
            if (preg_match_all('/(..?)\1{2,}/', $password, $matches) > 0) {
                $result |= PasswordDefinition::FAIL_REPEATED;
            }
        }

        return $result;
    }



}