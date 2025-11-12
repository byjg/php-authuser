<?php

namespace Tests;

use ByJG\AnyDataset\Core\AnyDataset;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Exception\NotAuthenticatedException;
use ByJG\Authenticate\Exception\UserExistsException;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\UsersAnyDataset;
use ByJG\Authenticate\UsersBase;
use ByJG\JwtWrapper\JwtHashHmacSecret;
use ByJG\JwtWrapper\JwtWrapper;
use PHPUnit\Framework\TestCase;

class UsersAnyDatasetByUsernameTest extends TestCase
{
    /**
     * @var UsersBase|null
     */
    protected UsersBase|null $object = null;

    /**
     * @var UserDefinition
     */
    protected $userDefinition;

    /**
     * @var \ByJG\Authenticate\Definition\UserPropertiesDefinition
     */
    protected $propertyDefinition;


    protected $prefix = "";

    public function __setUp($loginField)
    {
        $this->prefix = "user";

        $this->userDefinition = new UserDefinition('users', UserModel::class, $loginField);
        $this->propertyDefinition = new UserPropertiesDefinition();

        $anydataSet = new AnyDataset('php://memory');
        $this->object = new UsersAnyDataset(
            $anydataSet,
            $this->userDefinition,
            $this->propertyDefinition
        );
        $this->object->addUser('User 1', 'user1', 'user1@gmail.com', 'pwd1');
        $this->object->addUser('User 2', 'user2', 'user2@gmail.com', 'pwd2');
        $this->object->addUser('User 3', 'user3', 'user3@gmail.com', 'pwd3');
    }

    public function __chooseValue($forUsername, $forEmail): string
    {
        $searchForList = [
            $this->userDefinition->getUsername() => $forUsername,
            $this->userDefinition->getEmail() => $forEmail,
        ];
        return $searchForList[$this->userDefinition->loginField()];
    }

    #[\Override]
    public function setUp(): void
    {
        $this->__setUp(UserDefinition::LOGIN_IS_USERNAME);
    }

    /**
     * @return void
     */
    public function testAddUser()
    {
        $this->object->addUser('John Doe', 'john', 'johndoe@gmail.com', 'mypassword');

        $user = $this->object->get(
            $this->__chooseValue('john', 'johndoe@gmail.com'),
            $this->object->getUserDefinition()->loginField()
        );

        $this->assertEquals('john', $user->getUserid());
        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('john', $user->getUsername());
        $this->assertEquals('johndoe@gmail.com', $user->getEmail());
        $this->assertEquals('91dfd9ddb4198affc5c194cd8ce6d338fde470e2', $user->getPassword());
    }

    public function testAddUserError(): void
    {
        $this->expectException(UserExistsException::class);
        $this->object->addUser('some user with same username', 'user2', 'user2@gmail.com', 'mypassword');
    }

    public function testAddProperty(): void
    {
        // Check state
        $user = $this->object->get($this->prefix . '2');
        $this->assertEmpty($user->get('city'));

        // Add one property
        $this->object->addProperty($this->prefix . '2', 'city', 'Rio de Janeiro');
        $user = $this->object->get($this->prefix . '2');
        $this->assertEquals('Rio de Janeiro', $user->get('city'));

        // Add another property (cannot change)
        $this->object->addProperty($this->prefix . '2', 'city', 'Belo Horizonte');
        $user = $this->object->get($this->prefix . '2');
        $this->assertEquals(['Rio de Janeiro', 'Belo Horizonte'], $user->get('city'));

        // Get Property
        $this->assertEquals(['Rio de Janeiro', 'Belo Horizonte'], $this->object->getProperty($this->prefix . '2', 'city'));

        // Add another property
        $this->object->addProperty($this->prefix . '2', 'state', 'RJ');
        $user = $this->object->get($this->prefix . '2');
        $this->assertEquals('RJ', $user->get('state'));

        // Remove Property
        $this->object->removeProperty($this->prefix . '2', 'state', 'RJ');
        $user = $this->object->get($this->prefix . '2');
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
        $user = $this->object->get($this->prefix . '2');
        $this->assertEquals(['Rio de Janeiro', 'Niteroi'], $user->get('city'));
        $this->assertEquals('RJ', $user->get('state'));

        // Add another properties
        $this->object->addProperty($this->prefix . '1', 'city', 'Niteroi');
        $this->object->addProperty($this->prefix . '1', 'state', 'BA');
        $user = $this->object->get($this->prefix . '1');
        $this->assertEquals('Niteroi', $user->get('city'));
        $this->assertEquals('BA', $user->get('state'));

        // Remove Properties
        $this->object->removeAllProperties('state');
        $user = $this->object->get($this->prefix . '2');
        $this->assertEquals(['Rio de Janeiro', 'Niteroi'], $user->get('city'));
        $this->assertEmpty($user->get('state'));
        $user = $this->object->get($this->prefix . '1');
        $this->assertEquals('Niteroi', $user->get('city'));
        $this->assertEmpty($user->get('state'));

        // Remove Properties Again
        $this->object->removeAllProperties('city', 'Niteroi');
        $user = $this->object->get($this->prefix . '2');
        $this->assertEquals('Rio de Janeiro', $user->get('city'));
        $this->assertEmpty($user->get('state'));
        $user = $this->object->get($this->prefix . '1');
        $this->assertEmpty($user->get('city'));
        $this->assertEmpty($user->get('state'));

    }

    public function testRemoveByLoginField(): void
    {
        $login = $this->__chooseValue('user1', 'user1@gmail.com');

        $user = $this->object->get($login, $this->object->getUserDefinition()->loginField());
        $this->assertNotNull($user);

        $result = $this->object->removeByLoginField($login);
        $this->assertTrue($result);

        $user = $this->object->get($login, $this->object->getUserDefinition()->loginField());
        $this->assertNull($user);
    }

    public function testEditUser(): void
    {
        $login = $this->__chooseValue('user1', 'user1@gmail.com');

        // Getting data
        $user = $this->object->get($login, $this->object->getUserDefinition()->loginField());
        $this->assertEquals('User 1', $user->getName());

        // Change and Persist data
        $user->setName('Other name');
        $this->object->save($user);

        // Check if data persists
        $user = $this->object->get($this->prefix . '1');
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
        // Check is Admin
        $user3 = $this->object->get($this->prefix . '3');
        $this->assertFalse($user3->isAdmin());

        // Set the Admin Flag
        $login = $this->__chooseValue('user3', 'user3@gmail.com');
        $user = $this->object->get($login, $this->object->getUserDefinition()->loginField());
        $user->setAdmin('Y');
        $this->object->save($user);

        // Check is Admin
        $user3 = $this->object->get($this->prefix . '3');
        $this->assertTrue($user3->isAdmin());
    }

    protected function expectedToken($tokenData, $login, $userId): void
    {
        $loginCreated = $this->__chooseValue('user2', 'user2@gmail.com');

        $jwtWrapper = new JwtWrapper('api.test.com', new JwtHashHmacSecret('12345678', false));

        $token = $this->object->createAuthToken(
            $loginCreated,
            'pwd2',
            $jwtWrapper,
            1200,
            ['userData'=>'userValue'],
            ['tokenData'=>$tokenData]
        );

        $user = $this->object->get($login, $this->object->getUserDefinition()->loginField());

        $dataFromToken = new \stdClass();
        $dataFromToken->tokenData = $tokenData;
        $dataFromToken->login = $loginCreated;
        $dataFromToken->userid = $userId;

        $this->assertEquals(
            [
                'user' => $user,
                'data' => $dataFromToken
            ],
            $this->object->isValidToken($loginCreated, $jwtWrapper, $token)
        );
    }

    /**
     * @return void
     */
    public function testCreateAuthToken()
    {
        $login = $this->__chooseValue('user2', 'user2@gmail.com');

        $this->expectedToken('tokenValue', $login, 'user2');
    }

    public function testValidateTokenWithAnotherUser(): void
    {
        $this->expectException(NotAuthenticatedException::class);
        $login = $this->__chooseValue('user2', 'user2@gmail.com');
        $loginToFail = $this->__chooseValue('user1', 'user1@gmail.com');

        $jwtWrapper = new JwtWrapper('api.test.com', new JwtHashHmacSecret('1234567'));
        $token = $this->object->createAuthToken(
            $login,
            'pwd2',
            $jwtWrapper,
            1200,
            ['userData'=>'userValue'],
            ['tokenData'=>'tokenValue']
        );

        $this->object->isValidToken($loginToFail, $jwtWrapper, $token);
    }

    /**
     * @return void
     */
    public function testSaveAndSave()
    {
        $user = $this->object->get('user1');
        $this->object->save($user);

        $user2 = $this->object->get('user1');

        $this->assertEquals($user, $user2);
    }

    public function testRemoveUserById(): void
    {
        $user = $this->object->get($this->prefix . '1');
        $this->assertNotNull($user);

        $this->object->removeUserById($this->prefix . '1');

        $user2 = $this->object->get($this->prefix . '1');
        $this->assertNull($user2);
    }

    public function testGetByUsername(): void
    {
        $user = $this->object->get('user2', $this->object->getUserDefinition()->getUsername());

        $this->assertEquals($this->prefix . '2', $user->getUserid());
        $this->assertEquals('User 2', $user->getName());
        $this->assertEquals('user2', $user->getUsername());
        $this->assertEquals('user2@gmail.com', $user->getEmail());
        $this->assertEquals('c88b5c841897dafe75cdd9f8ba98b32f007d6bc3', $user->getPassword());
    }

    public function testGetByUserProperty(): void
    {
        // Add property to user1
        $user = $this->object->get($this->prefix . '1');
        $user->set('property1', 'somevalue');
        $this->object->save($user);

        // Add property to user2
        $user = $this->object->get($this->prefix . '2');
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
}
