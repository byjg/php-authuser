<?php

namespace Tests;

use ByJG\Authenticate\MapperFunctions\PasswordBcryptMapper;
use PHPUnit\Framework\TestCase;

class PasswordBcryptMapperTest extends TestCase
{
    private PasswordBcryptMapper $mapper;

    #[\Override]
    protected function setUp(): void
    {
        $this->mapper = new PasswordBcryptMapper();
    }

    public function testHashesWithBcrypt(): void
    {
        $hash = (string)$this->mapper->processedValue('mySecret1', null);

        $this->assertSame(PASSWORD_BCRYPT, password_get_info($hash)['algo']);
        $this->assertSame(60, strlen($hash));
        $this->assertNotSame($hash, $this->mapper->processedValue('mySecret1', null), 'bcrypt salts every hash');
    }

    public function testKeepsAHashAsIs(): void
    {
        $bcrypt = password_hash('mySecret1', PASSWORD_BCRYPT);
        $sha1 = sha1('mySecret1');

        $this->assertSame($bcrypt, $this->mapper->processedValue($bcrypt, null));
        $this->assertSame($sha1, $this->mapper->processedValue($sha1, null));
        $this->assertNull($this->mapper->processedValue('', null));
    }

    public function testRecognizesHashes(): void
    {
        $this->assertTrue($this->mapper->isPasswordEncrypted(password_hash('x', PASSWORD_BCRYPT)));
        $this->assertTrue($this->mapper->isPasswordEncrypted(sha1('x')));
        $this->assertFalse($this->mapper->isPasswordEncrypted('mySecret1'));
        $this->assertFalse($this->mapper->isPasswordEncrypted(str_repeat('z', 40)), '40 characters but not hexadecimal');
        $this->assertFalse($this->mapper->isPasswordEncrypted(str_repeat('z', 60)), '60 characters but not bcrypt');
    }

    public function testVerifiesBcrypt(): void
    {
        $hash = password_hash('mySecret1', PASSWORD_BCRYPT);

        $this->assertTrue($this->mapper->verifyPassword('mySecret1', $hash));
        $this->assertFalse($this->mapper->verifyPassword('wrong', $hash));
    }

    public function testVerifiesSha1(): void
    {
        $hash = sha1('mySecret1');

        $this->assertTrue($this->mapper->verifyPassword('mySecret1', $hash));
        $this->assertTrue($this->mapper->verifyPassword('mySecret1', strtoupper($hash)));
        $this->assertFalse($this->mapper->verifyPassword('wrong', $hash));
        $this->assertFalse($this->mapper->verifyPassword('mySecret1', 'not a hash'));
    }

    public function testNeedsRehash(): void
    {
        $this->assertTrue($this->mapper->needsRehash(sha1('mySecret1')), 'SHA-1 is upgraded');
        $this->assertTrue($this->mapper->needsRehash(password_hash('mySecret1', PASSWORD_BCRYPT, ['cost' => 4])), 'a lower cost is upgraded');
        $this->assertFalse($this->mapper->needsRehash(password_hash('mySecret1', PASSWORD_BCRYPT)));
    }
}
