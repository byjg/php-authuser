# Auth User PHP
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/69f04d22-055d-40b5-8c8d-90598a5367b5/mini.png)](https://insight.sensiolabs.com/projects/69f04d22-055d-40b5-8c8d-90598a5367b5)

## Description

A simple and customizable class for enable user authentication inside your application. It is available on XML files, Relational Databases and Moodle.

The main purpose is just to handle all complexity of validate a user, add properties and create access token abstracting the database layer. 
This class can persist into session (or file, memcache, etc) the user data between requests. 

## Examples

### Creating a Users handling class


**Using the FileSystem as the user storage**

```php
$users = new ByJG\Authenticate\UsersAnyDataset('/tmp/pass.anydata.xml');
```

**Using the Database as the user storage**

```php
$users = new ByJG\Authenticate\UsersDBDataset(
    'connection',   // The connection string. Please refer to the project byjg/anydataset
    new \ByJG\Authenticate\UserTable(),  // The field metadata for store the users
    new \ByJG\Authenticate\CustomTable()  // The field metadata for store the extra properties
);
```

**Using the Moodle as the user storage**

```php
$users = new \ByJG\Authenticate\UsersMoodleDataset('connection');
```


### Authenticate a user with your username and password and persist into the session

```php
$user = $users->isValidUser('someuser', '12345');
if (!is_null($user))
{
    $userId = $user->getField($users->getUserTable()->id;
    \ByJG\Authenticate\UserContext::getInstance()->registerLogin($userId);
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

You can save data in the session data exists only during the user is logged in. Once the user logged off the
data stored with the user session will be released.

**Store the data for the current user session**

```php
\ByJG\Authenticate\UserContext::getInstance()->setSessionData('key', 'value');
```

**Getting the data from the current user session**

```php
$value = \ByJG\Authenticate\UserContext::getInstance()->getSessionData('key');
```

Note: If the user is not logged an error will be throw

### Adding a custom property to the users;

```php
$user = $users->getById($userId);
$user->setField('somefield', 'somevalue');
$users->save();
```

### Logout from a session

```php
\ByJG\Authenticate\UserContext::getInstance()->registerLogout();
```

### Multiple authenticate contexts

It is possible have two or more different context of users authenticated,
including using different authentication methods, like, regular user logins and
admin user logins.

When you user the UserContext methods all of them can receive an extra parameter with the name
of the current context. If you do not pass anything, the 'default' context will be
assigned.

See the example:

```php
// 'default' context
\ByJG\Authenticate\UserContext::getInstance()->isAuthenticated();
\ByJG\Authenticate\UserContext::getInstance()->registerLogin('userId');
$value = \ByJG\Authenticate\UserContext::getInstance()->getSessionData('key');
\ByJG\Authenticate\UserContext::getInstance()->registerLogout();

// 'my' context
\ByJG\Authenticate\UserContext::getInstance()->isAuthenticated('my');
\ByJG\Authenticate\UserContext::getInstance()->registerLogin('userId', 'my');
$value = \ByJG\Authenticate\UserContext::getInstance()->getSessionData('key', 'my');
\ByJG\Authenticate\UserContext::getInstance()->registerLogout('my');
```

## Architecture

Authenticate Objects

User Context
TODO

### Database

### Custom Database


### Install

Just type: `composer require "byjg/authuser=1.0.*"`

## Running Tests

