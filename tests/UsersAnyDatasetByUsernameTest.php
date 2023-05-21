<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Core\AnyDataset;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Exception\NotAuthenticatedException;
use ByJG\Authenticate\Exception\UserExistsException;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Util\JwtKeySecret;
use ByJG\Util\JwtWrapper;
use PHPUnit\Framework\TestCase;

class UsersAnyDatasetByUsernameTest extends TestCase
{
    /**
     * @var UsersAnyDataset
     */
    protected $object;

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

    public function __chooseValue($forUsername, $forEmail)
    {
        $searchForList = [
            $this->userDefinition->getUsername() => $forUsername,
            $this->userDefinition->getEmail() => $forEmail,
        ];
        return $searchForList[$this->userDefinition->loginField()];
    }

    public function setUp(): void
    {
        $this->__setUp(UserDefinition::LOGIN_IS_USERNAME);
    }

    public function testAddUser()
    {
        $this->object->addUser('John Doe', 'john', 'johndoe@gmail.com', 'mypassword');

        $user = $this->object->getByLoginField($this->__chooseValue('john', 'johndoe@gmail.com'));

        $this->assertEquals('john', $user->getUserid());
        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('john', $user->getUsername());
        $this->assertEquals('johndoe@gmail.com', $user->getEmail());
        $this->assertEquals('91dfd9ddb4198affc5c194cd8ce6d338fde470e2', $user->getPassword());
    }

    public function testAddUserError()
    {
        $this->expectException(UserExistsException::class);
        $this->object->addUser('some user with same username', 'user2', 'user2@gmail.com', 'mypassword');
    }

    public function testAddProperty()
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

    public function testRemoveAllProperties()
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

    public function testRemoveByLoginField()
    {
        $login = $this->__chooseValue('user1', 'user1@gmail.com');

        $user = $this->object->getByLoginField($login);
        $this->assertNotNull($user);

        $result = $this->object->removeByLoginField($login);
        $this->assertTrue($result);

        $user = $this->object->getByLoginField($login);
        $this->assertNull($user);
    }

    public function testEditUser()
    {
        $login = $this->__chooseValue('user1', 'user1@gmail.com');

        // Getting data
        $user = $this->object->getByLoginField($login);
        $this->assertEquals('User 1', $user->getName());

        // Change and Persist data
        $user->setName('Other name');
        $this->object->save($user);

        // Check if data persists
        $user = $this->object->getById($this->prefix . '1');
        $this->assertEquals('Other name', $user->getName());
    }

    public function testIsValidUser()
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

    public function testIsAdmin()
    {
        // Check is Admin
        $this->assertFalse($this->object->isAdmin($this->prefix . '3'));

        // Set the Admin Flag
        $login = $this->__chooseValue('user3', 'user3@gmail.com');
        $user = $this->object->getByLoginField($login);
        $user->setAdmin('Y');
        $this->object->save($user);

        // Check is Admin
        $this->assertTrue($this->object->isAdmin($this->prefix . '3'));
    }

    protected function expectedToken($tokenData, $login, $userId)
    {
        $loginCreated = $this->__chooseValue('user2', 'user2@gmail.com');

        $jwtWrapper = new JwtWrapper('api.test.com', new JwtKeySecret('12345678', false));

        $token = $this->object->createAuthToken(
            $loginCreated,
            'pwd2',
            $jwtWrapper,
            1200,
            ['userData'=>'userValue'],
            ['tokenData'=>$tokenData]
        );

        $user = $this->object->getByLoginField($login);

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

    public function testCreateAuthToken()
    {
        $login = $this->__chooseValue('user2', 'user2@gmail.com');

        $this->expectedToken('tokenValue', $login, 'user2');
    }

    public function testValidateTokenWithAnotherUser()
    {
        $this->expectException(NotAuthenticatedException::class);
        $login = $this->__chooseValue('user2', 'user2@gmail.com');
        $loginToFail = $this->__chooseValue('user1', 'user1@gmail.com');

        $jwtWrapper = new JwtWrapper('api.test.com', new JwtKeySecret('1234567'));
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

    public function testCreateAuthTokenFail_2()
    {
        $this->expectException(NotAuthenticatedException::class);
        $loginToFail = $this->__chooseValue('user1', 'user1@gmail.com');
        $this->object->isValidToken($loginToFail, 'api.test.com', '1234567', 'Invalid token');
    }

    public function testSaveAndSave()
    {
        $user = $this->object->getById('user1');
        $this->object->save($user);

        $user2 = $this->object->getById('user1');

        $this->assertEquals($user, $user2);
    }

    public function testRemoveUserById()
    {
        $user = $this->object->getById($this->prefix . '1');
        $this->assertNotNull($user);

        $this->object->removeUserById($this->prefix . '1');

        $user2 = $this->object->getById($this->prefix . '1');
        $this->assertNull($user2);
    }

    public function testGetByUsername()
    {
        $user = $this->object->getByUsername('user2');

        $this->assertEquals($this->prefix . '2', $user->getUserid());
        $this->assertEquals('User 2', $user->getName());
        $this->assertEquals('user2', $user->getUsername());
        $this->assertEquals('user2@gmail.com', $user->getEmail());
        $this->assertEquals('c88b5c841897dafe75cdd9f8ba98b32f007d6bc3', $user->getPassword());
    }

    public function testGetByUserProperty()
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
}
