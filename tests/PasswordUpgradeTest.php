<?php

namespace Tests;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;
use ByJG\Authenticate\Definition\PasswordDefinition;
use ByJG\Authenticate\Enum\LoginField;
use ByJG\Authenticate\Exception\UserNotFoundException;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\Authenticate\Repository\UserPropertiesRepository;
use ByJG\Authenticate\Repository\UsersRepository;
use ByJG\Authenticate\Service\UsersService;
use ByJG\JwtWrapper\JwtHashHmacSecret;
use ByJG\JwtWrapper\JwtWrapper;
use PHPUnit\Framework\TestCase;

/**
 * A database created with SHA-1 passwords, read by UserModel's default mapper (bcrypt)
 */
class PasswordUpgradeTest extends TestCase
{
    const string DATABASE = '/tmp/teste_upgrade.db';

    private DatabaseExecutor $executor;
    private UsersService $service;

    /**
     * @param string $passwordColumn The password column, as an older schema may have it
     * @param string|null $trigger A trigger to create after the tables
     */
    private function createDatabase(string $passwordColumn = 'password varchar(255)', ?string $trigger = null): void
    {
        $this->executor = DatabaseExecutor::using(Factory::getDbInstance('sqlite://' . self::DATABASE));
        $this->executor->execute("create table users (
            userid integer primary key autoincrement,
            name varchar(45),
            email varchar(200),
            username varchar(20),
            $passwordColumn,
            created_at datetime default (datetime('2017-12-04')),
            updated_at datetime,
            deleted_at datetime,
            role varchar(20));"
        );
        $this->executor->execute('create table users_property (
            id integer primary key autoincrement,
            userid integer,
            name varchar(45),
            value varchar(45));'
        );
        if ($trigger !== null) {
            $this->executor->execute($trigger);
        }

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
        if (file_exists(self::DATABASE)) {
            unlink(self::DATABASE);
        }
    }

    private function storedPassword(): string
    {
        return (string)$this->executor->getScalar("select password from users where username = 'old'");
    }

    private function login(): void
    {
        $jwt = new JwtWrapper('api.test.com', new JwtHashHmacSecret(base64_encode(str_repeat('s', 64))));
        $this->service->createAuthToken('old', 'old', $jwt);
    }

    public function testIsValidUserDoesNotWrite(): void
    {
        $this->createDatabase();

        $this->assertNotNull($this->service->isValidUser('old', 'old'));
        $this->assertSame(sha1('old'), $this->storedPassword());
    }

    public function testRehashReplacesASha1Hash(): void
    {
        $this->createDatabase();
        $user = $this->service->isValidUser('old', 'old');

        $this->assertTrue($this->service->rehashPasswordIfNeeded($user, 'old'));
        $this->assertSame(PASSWORD_BCRYPT, password_get_info($this->storedPassword())['algo']);
        $this->assertTrue(password_verify('old', $this->storedPassword()));
        $this->assertNotNull($this->service->isValidUser('old', 'old'), 'the next login uses the bcrypt hash');
    }

    public function testRehashKeepsACurrentBcryptHash(): void
    {
        $this->createDatabase();
        $this->service->rehashPasswordIfNeeded($this->service->isValidUser('old', 'old'), 'old');
        $hash = $this->storedPassword();

        $this->assertFalse($this->service->rehashPasswordIfNeeded($this->service->isValidUser('old', 'old'), 'old'));
        $this->assertSame($hash, $this->storedPassword());
    }

    public function testLoginUpgradesTheHash(): void
    {
        $this->createDatabase();

        $this->login();

        $this->assertSame(PASSWORD_BCRYPT, password_get_info($this->storedPassword())['algo']);
    }

    public function testWrongPasswordIsNotUpgraded(): void
    {
        $this->createDatabase();

        $this->assertNull($this->service->isValidUser('old', 'wrong'));
        try {
            $jwt = new JwtWrapper('api.test.com', new JwtHashHmacSecret(base64_encode(str_repeat('s', 64))));
            $this->service->createAuthToken('old', 'wrong', $jwt);
            $this->fail('A wrong password logged in');
        } catch (UserNotFoundException) {
        }
        $this->assertSame(sha1('old'), $this->storedPassword());
    }

    /**
     * A column that rejects the longer hash (as MySQL does in strict mode): the user keeps SHA-1
     */
    public function testColumnTooShortRejectingTheHash(): void
    {
        $this->createDatabase('password varchar(40) check (length(password) <= 40)');

        $this->assertFalse($this->service->rehashPasswordIfNeeded($this->service->isValidUser('old', 'old'), 'old'));
        $this->assertSame(sha1('old'), $this->storedPassword());
        $this->login();
        $this->assertNotNull($this->service->isValidUser('old', 'old'), 'the user can still log in');
    }

    /**
     * A column that truncates the longer hash (as MySQL does without strict mode): the SHA-1 hash
     * is restored instead of leaving a truncated bcrypt hash nobody can log in with
     */
    public function testColumnTooShortTruncatingTheHash(): void
    {
        $this->createDatabase('password varchar(40)', "create trigger truncate_password after update of password on users
            begin update users set password = substr(new.password, 1, 40) where userid = new.userid; end");

        $this->assertFalse($this->service->rehashPasswordIfNeeded($this->service->isValidUser('old', 'old'), 'old'));
        $this->assertSame(sha1('old'), $this->storedPassword());
        $this->login();
        $this->assertNotNull($this->service->isValidUser('old', 'old'), 'the user can still log in');
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
