# Auth User PHP
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/69f04d22-055d-40b5-8c8d-90598a5367b5/mini.png)](https://insight.sensiolabs.com/projects/69f04d22-055d-40b5-8c8d-90598a5367b5)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/byjg/authuser/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/byjg/authuser/?branch=master)
[![Build Status](https://travis-ci.org/byjg/authuser.svg?branch=master)](https://travis-ci.org/byjg/authuser)

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

You can create and handle more than on context for users. 
All methods of the UserContext object can
receive an extra parameter with the name of the current context.
If you do not pass anything, the 'default' context will be assigned.

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

```
                            +----------------+            +----------------+
                            |                |            |                |
                            | UsersInterface |------------|  UserContext   |
                            |                |            |                |
                            +----------------+            +----------------+
                                    ^
                                    |
                                    |
            +-----------------------+--------------------------+
            |                       |                          |
   +-----------------+      +----------------+       +--------------------+
   |                 |      |                |       |                    |
   | UsersAnyDataset |      | UsersDBDataset |       | UsersMoodleDataset |
   |                 |      |                |       |                    |
   +-----------------+      +----------------+       +--------------------+
```

### Database

The default structure adopted for store the user data in the database through the
UsersDBDataset class is the follow:

```sql
create table users
(
    userid integer identity not null,
    name varchar(50),
    email varchar(120),
    username varchar(15) not null,
    password char(40) not null,
    created datetime,
    admin enum('Y','N'),

   	constraint pk_users primary key (userid)
)
TYPE = InnoDB;

create table users_property
(
   customid integer identity not null,
   name varchar(20),
   value varchar(100),
   userid integer not null,

   constraint pk_custom primary key (customid),
   constraint fk_custom_user foreign key (userid) references users (userid),
)
TYPE = InnoDB;
```

Using the database structure above you can create the UsersDBDatase as follow:

```php
$users = new ByJG\Authenticate\UsersDBDataset(
    'connection',
    new \ByJG\Authenticate\UserTable(),
    new \ByJG\Authenticate\CustomTable()
);
```

### Custom Database

If you have an existing database with different names but containing all fields above
you can use the UserTable and CustomTable classes for customize this info.

```php
$userTable = new UserTable(
    'users',    // $table
    'userid',   // $id
    'name',     // $name
    'email',    // $email
    'username', // $username
    'password', // $password
    'created',  // $created
    'admin'     // $admin
);
```


## Install

Just type: `composer require "byjg/authuser=1.0.*"`

## Running Tests

Just type `phpunit` on the root directory of your project.

