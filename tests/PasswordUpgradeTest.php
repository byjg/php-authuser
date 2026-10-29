<?php

namespace Tests;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;
use ByJG\Authenticate\Definition\PasswordDefinition;
use ByJG\Authenticate\Enum\LoginField;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\Authenticate\Repository\UserPropertiesRepository;
use ByJG\Authenticate\Repository\UsersRepository;
use ByJG\Authenticate\Service\UsersService;
use PHPUnit\Framework\TestCase;

/**
 * A database created with SHA-1 passwords, read by UserModel's default mapper (bcrypt)
 */
class PasswordUpgradeTest extends TestCase
{
    const string CONNECTION_STRING = 'sqlite:///tmp/teste_upgrade.db';

    private DatabaseExecutor $executor;
    private UsersService $service;

    #[\Override]
    protected function setUp(): void
    {
        $this->executor = DatabaseExecutor::using(Factory::getDbInstance(self::CONNECTION_STRING));
        $this->executor->execute('create table users (
            userid integer primary key autoincrement,
            name varchar(45),
            email varchar(200),
            username varchar(20),
            password varchar(255),
            created_at datetime default (datetime(\'2017-12-04\')),
            updated_at datetime,
            deleted_at datetime,
            role varchar(20));'
        );
        $this->executor->execute('create table users_property (
            id integer primary key autoincrement,
            userid integer,
            name varchar(45),
            value varchar(45));'
        );

        // A user saved by an older version: SHA-1, and a password weaker than today's definition
        $this->executor->execute(
            "insert into users (name, email, username, password, role) values ('Old User', 'old@example.com', 'old', '" . sha1('old') . "', 'user')"
        );

        $this->service = new UsersService(
            new UsersRepository($this->executor, UserModel::class),
            new UserPropertiesRepository($this->executor, UserPropertiesModel::class),
            LoginField::Username,
            new PasswordDefinition()
        );
    }

    #[\Override]
    protected function tearDown(): void
    {
        unlink('/tmp/teste_upgrade.db');
    }

    private function storedPassword(): string
    {
        return (string)$this->executor->getScalar("select password from users where username = 'old'");
    }

    public function testSha1PasswordLogsInAndIsUpgradedToBcrypt(): void
    {
        $user = $this->service->isValidUser('old', 'old');

        $this->assertNotNull($user);
        $this->assertSame('old', $user->getUsername());
        $this->assertSame(PASSWORD_BCRYPT, password_get_info($this->storedPassword())['algo']);
        $this->assertTrue(password_verify('old', $this->storedPassword()));

        // And the next login uses the bcrypt hash
        $this->assertNotNull($this->service->isValidUser('old', 'old'));
    }

    public function testWrongPasswordIsNotUpgraded(): void
    {
        $this->assertNull($this->service->isValidUser('old', 'wrong'));
        $this->assertSame(sha1('old'), $this->storedPassword());
    }

    /**
     * A model with a password definition (as Gluo's BaseUser sets in its constructor) is given the
     * stored hash when it is loaded: a hash is not checked against the definition
     */
    public function testAStoredHashIsNotCheckedAgainstTheDefinition(): void
    {
        $definition = new PasswordDefinition([PasswordDefinition::MINIMUM_CHARS => 100]);
        foreach ([password_hash('old', PASSWORD_BCRYPT), sha1('old')] as $hash) {
            $user = (new UserModel())->withPasswordDefinition($definition);
            $user->setPassword($hash);
            $this->assertSame($hash, $user->getPassword());
        }
    }
}
