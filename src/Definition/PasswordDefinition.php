<?php

namespace ByJG\Authenticate\Definition;

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

    protected $rules = [];

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

    public function setRule($rule, $value)
    {
        if (!array_key_exists($rule, $this->rules)) {
            throw new \InvalidArgumentException("Invalid rule");
        }
        $this->rules[$rule] = $value;
    }

    public function getRules()
    {
        return $this->rules;
    }

    public function getRule($rule)
    {
        if (!array_key_exists($rule, $this->rules)) {
            throw new \InvalidArgumentException("Invalid rule");
        }
        return $this->rules[$rule];
    }

    public function matchPassword($password)
    {
        // match password against the rules
        if (strlen($password) < $this->rules[self::MINIMUM_CHARS]) {
            return false;
        }
        if ($this->rules[self::REQUIRE_UPPERCASE] > 0) {
            if (preg_match_all('/[A-Z]/', $password, $matches) < $this->rules[self::REQUIRE_UPPERCASE]) {
                return false;
            }
        }
        if ($this->rules[self::REQUIRE_LOWERCASE] > 0) {
            if (preg_match_all('/[a-z]/', $password, $matches) < $this->rules[self::REQUIRE_LOWERCASE]) {
                return false;
            }
        }
        if ($this->rules[self::REQUIRE_SYMBOLS] > 0) {
            if (preg_match_all('/[!@#$%^&*()\-_=+{};:,<.>]/', $password, $matches) < $this->rules[self::REQUIRE_SYMBOLS]) {
                return false;
            }
        }
        if ($this->rules[self::REQUIRE_NUMBERS] > 0) {
            if (preg_match_all('/[0-9]/', $password, $matches) < $this->rules[self::REQUIRE_NUMBERS]) {
                return false;
            }
        }
        if ($this->rules[self::ALLOW_WHITESPACE] == 0) {
            if (preg_match_all('/\s/', $password, $matches) > 0) {
                return false;
            }
        }
        if ($this->rules[self::ALLOW_SEQUENTIAL] == 0) {
            if (preg_match_all('/([aA][bB][cC]|[bB][cC][dD]|[cC][dD][eE]|[dD][eE][fF]|[eE][fF][gG]|[fF][gG][hH]|[gG][hH][iI]|[hH][iI][jJ]|[iI][jJ][kK]|[jJ][kK][lL]|[kK][lL][mM]|[lL][mM][nN]|[mM][nN][oO]|[nN][oO][pP]|[oO][pP][qQ]|[pP][qQ][rR]|[qQ][rR][sS]|[rR][sS][tT]|[sS][tT][uU]|[tT][uU][vV]|[uU][vV][wW]|[vV][wW][xX]|[wW][xX][yY]|[xX][yY][zZ]|012|123|234|345|456|567|678|789|890|987|876|765|654|543|432|321)/', $password, $matches) > 0) {
                return false;
            }

        }
        if ($this->rules[self::ALLOW_REPEATED] == 0) {
            if (preg_match_all('/(..?)\1{2,}/', $password, $matches) > 0) {
                return false;
            }
        }
        return true;
    }



}