# Auth User PHP
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/69f04d22-055d-40b5-8c8d-90598a5367b5/mini.png)](https://insight.sensiolabs.com/projects/69f04d22-055d-40b5-8c8d-90598a5367b5)

## Description

A simple and customizable class for enable user authentication inside your application. It is available on XML files, Relational Databases and Moodle.

The main purpose is just to handle all complexity of validate a user, add properties and create access token abstracting the database layer. 
This class can persist into session (or file, memcache, etc) the user data between requests. 

## Examples

### Creating a Users handling class

```php
$users = new ByJG\Authenticate\UsersAnyDataset('/tmp/pass.anydata.xml');
```

Note: Can be also UsersDBDataset and UsersMoodleDataset

### Authenticate a user with your username and password and persist into the session

```php
$user = $users->isValidUser('someuser', '12345');
if (!is_null($user))
{
    \ByJG\Authenticate\UserContext::getInstance()->registerLogin($user, $users);
}
```

### Check if user was previously authenticated

```php 
// Check if the user is authenticated
if (\ByJG\Authenticate\UserContext::getInstance()->isAuthenticated())
    
    // Get the userId of the authenticated users
    $userId = \ByJG\Authenticate\UserContext::getInstance()->userInfo();

    // Get the user and your name
    $user = $users->getById($userId);
    echo "Hello: " . $user->getField($users->getUserTable()->name);
}
```

### Saving extra info into the user session 

TODO

### Adding a custom property to the users;

```php
$user = $users->getById($userId);
$user->setField('somefield', 'somevalue');
$users->save();
```

### Multiple authenticate contexts

If you have two set of users in your application:
- One set of regular users;
- Another set with different database and tables;

It is possible with AuthUser.

```php
//TODO
```

### Logout from a session

TODO

## Architecture

Authenticate Objects

User Context
TODO

## Install

TODO

Just type: `composer install "byjg/authuser=~1.0"`

### Database

### Custom Database



## Running Tests

