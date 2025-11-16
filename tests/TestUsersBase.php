<?php

namespace Tests;

use ByJG\Authenticate\Enum\LoginField;
use ByJG\Authenticate\Exception\NotAuthenticatedException;
use ByJG\Authenticate\Exception\UserExistsException;
use ByJG\Authenticate\Exception\UserNotFoundException;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserToken;
use ByJG\Authenticate\Service\UsersService;
use ByJG\JwtWrapper\JwtHashHmacSecret;
use ByJG\JwtWrapper\JwtWrapper;
use PHPUnit\Framework\TestCase;

abstract class TestUsersBase extends TestCase
{
    /**
     * @var UsersService|null
     */
    protected UsersService|null $object = null;

    protected LoginField $loginField;

    protected $prefix = "";

    abstract public function __setUp($loginField);

    public function __chooseValue($forUsername, $forEmail): string
    {
        return match ($this->loginField) {
            LoginField::Email => $forEmail,
            LoginField::Username => $forUsername,
        };
    }

    #[\Override]
    public function setUp(): void
    {
        $this->__setUp(LoginField::Username);
    }

    /**
     * @return void
     */
    abstract public function testAddUser();

    public function testAddUserError(): void
    {
        $this->expectException(UserExistsException::class);
        $this->object->addUser('some user with same username', 'user2', 'user2@gmail.com', 'mypassword');
    }

    public function testAddProperty(): void
    {
        // Check state
        $user = $this->object->getById($this->prefix . '2');
        $this->assertEmpty($user->get('city'));

        // Add one property
        $this->object->addProperty($this->prefix . '2', 'city', 'Rio de Janeiro');
        $user = $this->object->getById($this->prefix . '2');
        $this->assertEquals('Rio de Janeiro', $user->get('city'));

        // Add another property (cannot change)
        $this->object->addProperty($this->prefix . '2', 'city', 'Belo Horizonte');
        $user = $this->object->getById($this->prefix . '2');
        $this->assertEquals(['Rio de Janeiro', 'Belo Horizonte'], $user->get('city'));

        // Get Property
        $this->assertEquals(['Rio de Janeiro', 'Belo Horizonte'], $this->object->getProperty($this->prefix . '2', 'city'));

        // Add another property
        $this->object->addProperty($this->prefix . '2', 'state', 'RJ');
        $user = $this->object->getById($this->prefix . '2');
        $this->assertEquals('RJ', $user->get('state'));

        // Remove Property
        $this->object->removeProperty($this->prefix . '2', 'state', 'RJ');
        $user = $this->object->getById($this->prefix . '2');
        $this->assertEmpty($user->get('state'));

        // Remove Property Again
        $this->object->removeProperty($this->prefix . '2', 'city', 'Rio de Janeiro');
        $this->assertEquals('Belo Horizonte', $this->object->getProperty($this->prefix . '2', 'city'));

    }

    public function testRemoveAllProperties(): void
    {
        // Add the properties
        $this->object->addProperty($this->prefix . '2', 'city', 'Rio de Janeiro');
        $this->object->addProperty($this->prefix . '2', 'city', 'Niteroi');
        $this->object->addProperty($this->prefix . '2', 'state', 'RJ');
        $user = $this->object->getById($this->prefix . '2');
        $this->assertEquals(['Rio de Janeiro', 'Niteroi'], $user->get('city'));
        $this->assertEquals('RJ', $user->get('state'));

        // Add another properties
        $this->object->addProperty($this->prefix . '1', 'city', 'Niteroi');
        $this->object->addProperty($this->prefix . '1', 'state', 'BA');
        $user = $this->object->getById($this->prefix . '1');
        $this->assertEquals('Niteroi', $user->get('city'));
        $this->assertEquals('BA', $user->get('state'));

        // Remove Properties
        $this->object->removeAllProperties('state');
        $user = $this->object->getById($this->prefix . '2');
        $this->assertEquals(['Rio de Janeiro', 'Niteroi'], $user->get('city'));
        $this->assertEmpty($user->get('state'));
        $user = $this->object->getById($this->prefix . '1');
        $this->assertEquals('Niteroi', $user->get('city'));
        $this->assertEmpty($user->get('state'));

        // Remove Properties Again
        $this->object->removeAllProperties('city', 'Niteroi');
        $user = $this->object->getById($this->prefix . '2');
        $this->assertEquals('Rio de Janeiro', $user->get('city'));
        $this->assertEmpty($user->get('state'));
        $user = $this->object->getById($this->prefix . '1');
        $this->assertEmpty($user->get('city'));
        $this->assertEmpty($user->get('state'));

    }

    public function testRemoveByLoginField(): void
    {
        $login = $this->__chooseValue('user1', 'user1@gmail.com');

        $user = $this->object->getByLogin($login);
        $this->assertNotNull($user);

        $result = $this->object->removeByLogin($login);
        $this->assertTrue($result);

        $user = $this->object->getByLogin($login);
        $this->assertNull($user);
    }

    public function testEditUser(): void
    {
        $login = $this->__chooseValue('user1', 'user1@gmail.com');

        // Getting data
        $user = $this->object->getByLogin($login);
        $this->assertEquals('User 1', $user->getName());

        // Change and Persist data
        $user->setName('Other name');
        $this->object->save($user);

        // Check if data persists
        $user = $this->object->getById($this->prefix . '1');
        $this->assertEquals('Other name', $user->getName());
    }

    public function testIsValidUser(): void
    {
        $login = $this->__chooseValue('user3', 'user3@gmail.com');
        $loginFalse = $this->__chooseValue('user3@gmail.com', 'user3');

        // User Exists!
        $user = $this->object->isValidUser($login, 'pwd3');
        $this->assertEquals('User 3', $user->getName());

        // User Does not Exists!
        $user = $this->object->isValidUser($loginFalse, 'pwd5');
        $this->assertNull($user);
    }

    public function testIsAdmin(): void
    {
        // Check user has no role initially
        $user3 = $this->object->getById($this->prefix . '3');
        $this->assertFalse($user3->hasRole('admin'));
        $this->assertEmpty($user3->getRole());

        // Set a role
        $login = $this->__chooseValue('user3', 'user3@gmail.com');
        $user = $this->object->getByLogin($login);
        $user->setRole('admin');
        $this->object->save($user);

        // Check user has the role
        $user3 = $this->object->getById($this->prefix . '3');
        $this->assertTrue($user3->hasRole('admin'));
        $this->assertTrue($user3->hasRole('ADMIN')); // Case insensitive
        $this->assertFalse($user3->hasRole('user'));
        $this->assertEquals('admin', $user3->getRole());

        // Test different roles
        $user->setRole('moderator');
        $this->object->save($user);

        $user3 = $this->object->getById($this->prefix . '3');
        $this->assertFalse($user3->hasRole('admin'));
        $this->assertTrue($user3->hasRole('moderator'));
        $this->assertEquals('moderator', $user3->getRole());
    }

    protected function expectedToken(string $tokenData, string $login, int $userId): void
    {
        $loginCreated = $this->__chooseValue('user2', 'user2@gmail.com');

        $jwtWrapper = new JwtWrapper('api.test.com', new JwtHashHmacSecret('12345678', false));

        $userToken = $this->object->createAuthToken(
            $loginCreated,
            'pwd2',
            $jwtWrapper,
            1200,
            ['userData'=>'userValue'],
            ['tokenData'=>$tokenData]
        );

        $user = $this->object->getByLogin($login);

        $dataFromToken = [
            'tokenData' => $tokenData,
            'userid' => $userId,
            'name' => $user->getName(),
            'role' => $user->getRole(),
        ];

        $tokenResult = $this->object->isValidToken($loginCreated, $jwtWrapper, $userToken->token);

        // Compare data object
        $this->assertEquals($dataFromToken, $tokenResult->data);

        // Compare user fields (excluding timestamps which may differ)
        $this->assertEquals($user->getUserid(), $tokenResult->user->getUserid());
        $this->assertEquals($user->getName(), $tokenResult->user->getName());
        $this->assertEquals($user->getEmail(), $tokenResult->user->getEmail());
        $this->assertEquals($user->getUsername(), $tokenResult->user->getUsername());
        $this->assertEquals($user->getPassword(), $tokenResult->user->getPassword());
        $this->assertEquals($user->getRole(), $tokenResult->user->getRole());
    }

    /**
     * @return void
     */
    abstract public function testCreateAuthToken();

    public function testCreateAuthTokenReturnsUserToken(): void
    {
        $login = $this->__chooseValue('user2', 'user2@gmail.com');
        $jwtWrapper = new JwtWrapper('api.test.com', new JwtHashHmacSecret('12345678', false));

        $userToken = $this->object->createAuthToken(
            $login,
            'pwd2',
            $jwtWrapper,
            1200
        );

        $this->assertInstanceOf(UserToken::class, $userToken);
        $this->assertNotEmpty($userToken->token);
        $this->assertInstanceOf(UserModel::class, $userToken->user);
        $this->assertEquals(2, $userToken->user->getUserid());
    }

    public function testCreateAuthTokenWithInvalidPassword(): void
    {
        $this->expectException(UserNotFoundException::class);
        $login = $this->__chooseValue('user2', 'user2@gmail.com');
        $jwtWrapper = new JwtWrapper('api.test.com', new JwtHashHmacSecret('12345678', false));

        $this->object->createAuthToken(
            $login,
            'wrongpassword',
            $jwtWrapper,
            1200
        );
    }

    public function testCreateAuthTokenWithUpdateUserInfo(): void
    {
        $login = $this->__chooseValue('user2', 'user2@gmail.com');
        $jwtWrapper = new JwtWrapper('api.test.com', new JwtHashHmacSecret('12345678', false));

        $userToken = $this->object->createAuthToken(
            $login,
            'pwd2',
            $jwtWrapper,
            1200,
            ['last_login' => '2024-01-01 12:00:00']
        );

        $this->assertInstanceOf(UserToken::class, $userToken);
        $this->assertEquals('2024-01-01 12:00:00', $userToken->user->get('last_login'));
    }

    public function testCreateAuthTokenWithCustomTokenData(): void
    {
        $login = $this->__chooseValue('user2', 'user2@gmail.com');
        $jwtWrapper = new JwtWrapper('api.test.com', new JwtHashHmacSecret('12345678', false));

        $userToken = $this->object->createAuthToken(
            $login,
            'pwd2',
            $jwtWrapper,
            1200,
            [],
            ['custom_field' => 'custom_value', 'another_field' => 123]
        );

        $this->assertInstanceOf(UserToken::class, $userToken);
        $this->assertEquals('custom_value', $userToken->data['custom_field']);
        $this->assertEquals(123, $userToken->data['another_field']);
    }

    public function testCreateAuthTokenIncludesDefaultUserFields(): void
    {
        $login = $this->__chooseValue('user2', 'user2@gmail.com');
        $jwtWrapper = new JwtWrapper('api.test.com', new JwtHashHmacSecret('12345678', false));

        $userToken = $this->object->createAuthToken(
            $login,
            'pwd2',
            $jwtWrapper,
            1200
        );

        $this->assertArrayHasKey('userid', $userToken->data);
        $this->assertArrayHasKey('name', $userToken->data);
        $this->assertArrayHasKey('role', $userToken->data);
        $this->assertEquals(2, $userToken->data['userid']);
    }

    public function testCreateAuthTokenStoresTokenHash(): void
    {
        $login = $this->__chooseValue('user2', 'user2@gmail.com');
        $jwtWrapper = new JwtWrapper('api.test.com', new JwtHashHmacSecret('12345678', false));

        $userToken = $this->object->createAuthToken(
            $login,
            'pwd2',
            $jwtWrapper,
            1200
        );

        $expectedHash = sha1($userToken->token);
        $this->assertEquals($expectedHash, $userToken->user->get('TOKEN_HASH'));
    }

    public function testCreateInsecureAuthTokenWithLoginString(): void
    {
        $login = $this->__chooseValue('user2', 'user2@gmail.com');
        $jwtWrapper = new JwtWrapper('api.test.com', new JwtHashHmacSecret('12345678', false));

        $userToken = $this->object->createInsecureAuthToken(
            $login,
            $jwtWrapper,
            1200
        );

        $this->assertInstanceOf(UserToken::class, $userToken);
        $this->assertNotEmpty($userToken->token);
        $this->assertInstanceOf(UserModel::class, $userToken->user);
        $this->assertEquals(2, $userToken->user->getUserid());
    }

    public function testCreateInsecureAuthTokenWithUserModel(): void
    {
        $login = $this->__chooseValue('user2', 'user2@gmail.com');
        $user = $this->object->getByLogin($login);
        $jwtWrapper = new JwtWrapper('api.test.com', new JwtHashHmacSecret('12345678', false));

        $userToken = $this->object->createInsecureAuthToken(
            $user,
            $jwtWrapper,
            1200
        );

        $this->assertInstanceOf(UserToken::class, $userToken);
        $this->assertNotEmpty($userToken->token);
        $this->assertEquals($user->getUserid(), $userToken->user->getUserid());
    }

    public function testCreateInsecureAuthTokenWithInvalidLogin(): void
    {
        $this->expectException(UserNotFoundException::class);
        $jwtWrapper = new JwtWrapper('api.test.com', new JwtHashHmacSecret('12345678', false));

        $this->object->createInsecureAuthToken(
            'nonexistent@example.com',
            $jwtWrapper,
            1200
        );
    }

    public function testCreateInsecureAuthTokenWithCustomFields(): void
    {
        $login = $this->__chooseValue('user2', 'user2@gmail.com');
        $jwtWrapper = new JwtWrapper('api.test.com', new JwtHashHmacSecret('12345678', false));

        $userToken = $this->object->createInsecureAuthToken(
            $login,
            $jwtWrapper,
            1200,
            ['session_id' => 'abc123'],
            ['ip_address' => '192.168.1.1']
        );

        $this->assertInstanceOf(UserToken::class, $userToken);
        $this->assertEquals('abc123', $userToken->user->get('session_id'));
        $this->assertEquals('192.168.1.1', $userToken->data['ip_address']);
    }

    public function testCreateInsecureAuthTokenStoresTokenHash(): void
    {
        $login = $this->__chooseValue('user2', 'user2@gmail.com');
        $jwtWrapper = new JwtWrapper('api.test.com', new JwtHashHmacSecret('12345678', false));

        $userToken = $this->object->createInsecureAuthToken(
            $login,
            $jwtWrapper,
            1200
        );

        $expectedHash = sha1($userToken->token);
        $this->assertEquals($expectedHash, $userToken->user->get('TOKEN_HASH'));
    }

    public function testValidateTokenWithAnotherUser(): void
    {
        $this->expectException(NotAuthenticatedException::class);
        $login = $this->__chooseValue('user2', 'user2@gmail.com');
        $loginToFail = $this->__chooseValue('user1', 'user1@gmail.com');

        $jwtWrapper = new JwtWrapper('api.test.com', new JwtHashHmacSecret('1234567'));
        $userToken = $this->object->createAuthToken(
            $login,
            'pwd2',
            $jwtWrapper,
            1200,
            ['userData'=>'userValue'],
            ['tokenData'=>'tokenValue']
        );

        $this->object->isValidToken($loginToFail, $jwtWrapper, $userToken->token);
    }

    /**
     * @return void
     */
    abstract public function testSaveAndSave();

    public function testRemoveUserById(): void
    {
        $user = $this->object->getById($this->prefix . '1');
        $this->assertNotNull($user);

        $this->object->removeById($this->prefix . '1');

        $user2 = $this->object->getById($this->prefix . '1');
        $this->assertNull($user2);
    }

    public function testGetByUsername(): void
    {
        $user = $this->object->getByUsername('user2');

        $this->assertEquals($this->prefix . '2', $user->getUserid());
        $this->assertEquals('User 2', $user->getName());
        $this->assertEquals('user2', $user->getUsername());
        $this->assertEquals('user2@gmail.com', $user->getEmail());
        $this->assertEquals('c88b5c841897dafe75cdd9f8ba98b32f007d6bc3', $user->getPassword());
    }

    public function testGetByUserProperty(): void
    {
        // Add property to user1
        $user = $this->object->getById($this->prefix . '1');
        $user->set('property1', 'somevalue');
        $this->object->save($user);

        // Add property to user2
        $user = $this->object->getById($this->prefix . '2');
        $user->set('property1', 'value1');
        $user->set('property2', 'value2');
        $this->object->save($user);

        // Get user by property
        $user = $this->object->getUsersByProperty('property1', 'value2');
        $this->assertCount(0, $user);

        // Get user by property
        $user = $this->object->getUsersByProperty('property1', 'somevalue');
        $this->assertCount(1, $user);
        $this->assertEquals($this->prefix . '1', $user[0]->getUserid());

        // Only one property is valid, so no results
        $user = $this->object->getUsersByPropertySet(['property1'=>'xyz', 'property2'=>'value2']);
        $this->assertCount(0, $user);

        // Get user2 by property using method getUsersByPropertySet
        $user = $this->object->getUsersByPropertySet(['property1'=>'value1', 'property2'=>'value2']);
        $this->assertCount(1, $user);
        $this->assertEquals($this->prefix . '2', $user[0]->getUserid());

    }

    public function testSetProperty(): void
    {
        $this->assertFalse($this->object->hasProperty($this->prefix . '1', 'propertySet'));
        $this->object->setProperty($this->prefix . '1', 'propertySet', 'somevalue');
        $this->assertTrue($this->object->hasProperty($this->prefix . '1', 'propertySet'));
        $this->assertTrue($this->object->hasProperty($this->prefix . '1', 'propertySet', 'somevalue'));
        $this->assertEquals('somevalue', $this->object->getProperty($this->prefix . '1', 'propertySet'));
    }

    public function testPasswordDefinitionValidOnSave(): void
    {
        // Create a password definition requiring uppercase, lowercase, and numbers
        $passwordDef = new \ByJG\Authenticate\Definition\PasswordDefinition([
            \ByJG\Authenticate\Definition\PasswordDefinition::MINIMUM_CHARS => 8,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_UPPERCASE => 1,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_LOWERCASE => 1,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_NUMBERS => 1,
        ]);

        // Create a user model with valid password
        $user = new \ByJG\Authenticate\Model\UserModel();
        $user->setName('Test User');
        $user->setUsername('testuser_pwd');
        $user->setEmail('testpwd@example.com');
        $user->setPassword('ValidPass8642'); // Valid: uppercase, lowercase, numbers, no sequential, 12 chars
        $user->withPasswordDefinition($passwordDef);

        // Should save successfully
        $savedUser = $this->object->save($user);
        $this->assertNotNull($savedUser->getUserid());
        $this->assertEquals('Test User', $savedUser->getName());

        // Verify user was saved
        $retrievedUser = $this->object->getByUsername('testuser_pwd');
        $this->assertNotNull($retrievedUser);
        $this->assertEquals('testpwd@example.com', $retrievedUser->getEmail());
    }

    public function testPasswordDefinitionInvalidOnSave(): void
    {
        // Create a password definition requiring uppercase, lowercase, and numbers
        $passwordDef = new \ByJG\Authenticate\Definition\PasswordDefinition([
            \ByJG\Authenticate\Definition\PasswordDefinition::MINIMUM_CHARS => 8,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_UPPERCASE => 1,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_LOWERCASE => 1,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_NUMBERS => 1,
        ]);

        // Create a user model with invalid password (no uppercase)
        $user = new \ByJG\Authenticate\Model\UserModel();
        $user->setName('Test User');
        $user->setUsername('testuser_invalid');
        $user->setEmail('invalid@example.com');
        $user->withPasswordDefinition($passwordDef);

        // Should throw exception because password doesn't match definition
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password does not match the password definition');
        $user->setPassword('weakpass123'); // Invalid: no uppercase
    }

    public function testPasswordDefinitionValidOnUpdate(): void
    {
        // Get existing user
        $user = $this->object->getById($this->prefix . '1');
        $this->assertNotNull($user);

        // Create a password definition
        $passwordDef = new \ByJG\Authenticate\Definition\PasswordDefinition([
            \ByJG\Authenticate\Definition\PasswordDefinition::MINIMUM_CHARS => 10,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_UPPERCASE => 1,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_LOWERCASE => 1,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_NUMBERS => 2,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_SYMBOLS => 1,
        ]);

        // Update password with valid value
        $user->setPassword('StrongPass84!'); // Valid: uppercase, lowercase, 2 numbers, symbol, no sequential, 13 chars
        $user->withPasswordDefinition($passwordDef);

        // Should update successfully
        $savedUser = $this->object->save($user);

        // Verify password was updated (by checking authentication works with login field)
        $login = $this->__chooseValue($user->getUsername(), $user->getEmail());
        $validUser = $this->object->isValidUser($login, 'StrongPass84!');
        $this->assertNotNull($validUser);
        $this->assertEquals($user->getUserid(), $validUser->getUserid());
        $this->assertEquals($user->getPassword(), $validUser->getPassword());
    }

    public function testPasswordDefinitionInvalidOnUpdate(): void
    {
        // Get existing user
        $user = $this->object->getById($this->prefix . '2');
        $this->assertNotNull($user);

        // Create a strict password definition
        $passwordDef = new \ByJG\Authenticate\Definition\PasswordDefinition([
            \ByJG\Authenticate\Definition\PasswordDefinition::MINIMUM_CHARS => 12,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_UPPERCASE => 2,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_LOWERCASE => 2,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_NUMBERS => 2,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_SYMBOLS => 1,
        ]);

        $user->withPasswordDefinition($passwordDef);

        // Should throw exception because password doesn't have 2 uppercase letters
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password does not match the password definition');
        $user->setPassword('weak1!'); // Invalid: too short, not enough uppercase/lowercase/numbers
    }

    public function testPasswordDefinitionMultipleFailures(): void
    {
        // Create a strict password definition
        $passwordDef = new \ByJG\Authenticate\Definition\PasswordDefinition([
            \ByJG\Authenticate\Definition\PasswordDefinition::MINIMUM_CHARS => 12,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_UPPERCASE => 1,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_LOWERCASE => 1,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_NUMBERS => 1,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_SYMBOLS => 1,
            \ByJG\Authenticate\Definition\PasswordDefinition::ALLOW_WHITESPACE => 0,
        ]);

        $user = new \ByJG\Authenticate\Model\UserModel();
        $user->setName('Test User');
        $user->setUsername('testuser_multi');
        $user->setEmail('multi@example.com');
        $user->withPasswordDefinition($passwordDef);

        // Test multiple failure scenarios
        try {
            $user->setPassword('short'); // Fails: too short, no uppercase, no numbers, no symbols
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Password does not match the password definition', $e->getMessage());
        }

        try {
            $user->setPassword('with space 123A!'); // Fails: has whitespace
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Password does not match the password definition', $e->getMessage());
        }
    }

    public function testPasswordDefinitionViaServiceAddUser(): void
    {
        // Create a new service instance with password definition
        $passwordDef = new \ByJG\Authenticate\Definition\PasswordDefinition([
            \ByJG\Authenticate\Definition\PasswordDefinition::MINIMUM_CHARS => 10,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_UPPERCASE => 1,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_LOWERCASE => 1,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_NUMBERS => 1,
            \ByJG\Authenticate\Definition\PasswordDefinition::REQUIRE_SYMBOLS => 1,
        ]);

        // Get the repositories from existing service
        $usersRepo = $this->object->getUsersRepository();
        $propsRepo = $this->object->getPropertiesRepository();

        // Create new service with password definition
        $usersWithPwdDef = new \ByJG\Authenticate\Service\UsersService(
            $usersRepo,
            $propsRepo,
            LoginField::Username,
            $passwordDef
        );

        // Valid password should work
        $user = $usersWithPwdDef->addUser(
            'Valid User',
            'validuser',
            'valid@example.com',
            'StrongPass8!' // Valid: 12 chars, uppercase, lowercase, number, symbol, no sequential
        );
        $this->assertNotNull($user->getUserid());
        $this->assertEquals('Valid User', $user->getName());

        // Invalid password should fail
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password does not match the password definition');
        $usersWithPwdDef->addUser(
            'Invalid User',
            'invaliduser',
            'invalid@example.com',
            'weak' // Invalid: too short, no uppercase, no numbers, no symbols
        );
    }

    public function testPasswordHashRemainsUnchangedOnSave(): void
    {
        // Create a user with a password
        $originalPassword = 'mySecretPassword';
        $user = $this->object->addUser('Test User', 'testuser', 'test@example.com', $originalPassword);

        // Store the original password hash
        $originalHash = $user->getPassword();
        $this->assertEquals($originalHash, sha1($originalPassword));

        // Verify the user can authenticate with the original password
        $login = $this->__chooseValue('testuser', 'test@example.com');
        $authenticatedUser = $this->object->isValidUser($login, $originalPassword);
        $this->assertNotNull($authenticatedUser);
        $this->assertEquals($user->getUserid(), $authenticatedUser->getUserid());

        // Get the user from database
        $retrievedUser = $this->object->getById($user->getUserid());
        $this->assertEquals($originalHash, $retrievedUser->getPassword());

        // Update other fields WITHOUT touching password
        $retrievedUser->setName('Updated Name');
        $retrievedUser->setEmail('updated@example.com');
        $retrievedUser->setRole('moderator');

        // Save the user
        $updatedUser = $this->object->save($retrievedUser);

        // Verify the password hash remained exactly the same
        $this->assertEquals($originalHash, $updatedUser->getPassword());
        $this->assertEquals('Updated Name', $updatedUser->getName());
        $this->assertEquals('updated@example.com', $updatedUser->getEmail());
        $this->assertEquals('moderator', $updatedUser->getRole());

        // Verify user can still authenticate with original password
        $login = $this->__chooseValue('testuser', 'updated@example.com');
        $authenticatedUser = $this->object->isValidUser($login, $originalPassword);
        $this->assertNotNull($authenticatedUser);
        $this->assertEquals($user->getUserid(), $authenticatedUser->getUserid());

        // Get user again from database to confirm persistence
        $finalUser = $this->object->getById($user->getUserid());
        $this->assertEquals($originalHash, $finalUser->getPassword());
        $this->assertEquals('Updated Name', $finalUser->getName());
    }
}
