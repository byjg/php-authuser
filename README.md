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


**Using the FileSystem (XML) as the user storage**

```php
<?php
$users = new UsersAnyDataset('/tmp/pass.anydata.xml');
```

**Using the Database as the user storage**

```php
<?php
$users = new ByJG\Authenticate\UsersDBDataset(
    'connection',   // The connection string. Please refer to the project byjg/anydataset
    new UserDefinition(),  // The field metadata for store the users
    new UserPropertiesDefinition()  // The field metadata for store the extra properties
);
```

*Note*: See the [Anydataset project](https://github.com/byjg/anydataset#connection-based-on-uri) to see the
database available and the connection strings as well.

**Using the Moodle as the user storage**

```php
<?php
$users = new UsersMoodleDataset('connection');
```


### Authenticate a user with your username and password and persist into the session

```php
<?php
$user = $users->isValidUser('someuser', '12345');
if (!is_null($user))
{
    $userId = $user->getUserid();
    
    $sessionContext = new \ByJG\Authenticate\SessionContext(\ByJG\Cache\Factory::createSessionPool());
    $sessionContext->registerLogin($userId);
}
```

### Check if user was previously authenticated

```php
<?php
$sessionContext = new \ByJG\Authenticate\SessionContext(\ByJG\Cache\Factory::createSessionPool());

// Check if the user is authenticated
if ($sessionContext->isAuthenticated()) {
    
    // Get the userId of the authenticated users
    $userId = $sessionContext->userInfo();

    // Get the user and your name
    $user = $users->getById($userId);
    echo "Hello: " . $user->getName();
}
```

### Saving extra info into the user session 

You can save data in the session data exists only during the user is logged in. Once the user logged off the
data stored with the user session will be released.

**Store the data for the current user session**

```php
<?php
$sessionContext = new \ByJG\Authenticate\SessionContext(\ByJG\Cache\Factory::createSessionPool());
$sessionContext->setSessionData('key', 'value');
```

**Getting the data from the current user session**

```php
<?php
$sessionContext = new \ByJG\Authenticate\SessionContext(\ByJG\Cache\Factory::createSessionPool());
$value = $sessionContext->getSessionData('key');
```

Note: If the user is not logged an error will be throw

### Adding a custom property to the users;

```php
<?php
$user = $users->getById($userId);
$user->setField('somefield', 'somevalue');
$users->save();
```

### Logout from a session

```php
<?php
$sessionContext->registerLogout();
```
### Important note about SessionContext

`SessionContext` object will store the info about the current context. 
As SessionContext uses CachePool interface defined in PSR-6 you can set any storage
to save your session context. 

In our examples we are using a regular PHP Session for store the user context
(`Factory::createSessionPool()`). But if you are using another store like MemCached
you have to define a UNIQUE prefix for that session. Note if TWO users have the same
prefix you probably have an unexpected result for the SessionContext.
 
Example for memcached:

```php
<?php
$sessionContext = new \ByJG\Authenticate\SessionContext(\ByJG\Cache\Factory::createMemcachedPool(), 'UNIQUEPREFIX');
```

If you do not know to create/manage that unique prefix **prefer to use the regular Session object.**


## Architecture

```
                            +----------------+            +----------------+
                            |                |            |                |
                            | UsersInterface |------------| SessionContext |
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
    userid integer AUTO_INCREMENT not null,
    name varchar(50),
    email varchar(120),
    username varchar(15) not null,
    password char(40) not null,
    created datetime,
    admin enum('Y','N'),

   	constraint pk_users primary key (userid)
)
ENGINE=InnoDB;

create table users_property
(
   customid integer AUTO_INCREMENT not null,
   name varchar(20),
   value varchar(100),
   userid integer not null,

   constraint pk_custom primary key (customid),
   constraint fk_custom_user foreign key (userid) references users (userid)
)
ENGINE=InnoDB;
```

Using the database structure above you can create the UsersDBDatase as follow:

```php
<?php
$users = new ByJG\Authenticate\UsersDBDataset(
    'connection',
    new \ByJG\Authenticate\Definition\UserDefinition(),
    new \ByJG\Authenticate\Definition\UserPropertiesDefinition()
);
```

### Custom Database

If you have an existing database with different names but containing all fields above
you can use the UserDefinition and UserPropertiesDefinition classes for customize this info.

```php
<?php
$userDefinition = new \ByJG\Authenticate\Definition\UserDefinition(
    'users',    // $table
    \ByJG\Authenticate\Definition\UserDefinition::LOGIN_IS_EMAIL,
    [
        'userid'   => 'fieldname of userid',
        'name'     => 'fieldname of name',
        'email'    => 'fieldname of email',
        'username' => 'fieldname of username',
        'password' => 'fieldname of password',
        'created'  => 'fieldname of created',
        'admin'    => 'fieldname of admin'
    ]
);
```

#### Adding custom modifiers for read and update

```php
<?php
$userDefinition = new \ByJG\Authenticate\Definition\UserDefinition(
    'users',    // $table
    \ByJG\Authenticate\Definition\UserDefinition::LOGIN_IS_EMAIL
);

// Defines a custom function to be applied BEFORE update/insert the field 'password'
// $value --> the current value to be updated
// $instance -> The array with all other fields;
$userDefinition->defineClosureForUpdate('password', function ($value, $instance) {
    return strtoupper(sha1($value));
});

// Defines a custom function to be applied After the field 'created' is read but before
// the user get the result
// $value --> the current value retrieved from database
// $instance -> The array with all other fields;
$userDefinition->defineClosureForSelect('created', function ($value, $instance) {
    return date('Y', $value);
});

// If you want make the field READONLY just do it:
$userDefinition->markPropertyAsReadOnly('created');
```


#### Extending UserModel

It is possible extending the UserModel table, since you are adding new Fields. 

For example, imagine your table has one field called "otherfield". 
You'll have to extend like this:

```php
<?php
/**
 * Create a class that inherit the UserDefinition and add
 * the new fields
 */
class MyUserDefinition extends \ByJG\Authenticate\Definition\UserDefinition
{
    /**
     * This is the property that maps the field.
     * The property name have the name of property in the class
     * And your value have the the mapping for the field in the database 
     * @var string
     */
    protected $otherfield = 'otherfield';

    /**
     * Class Constructor
     */
    public function __construct(
        $table = 'users',
        $loginField = self::LOGIN_IS_USERNAME,
        array $fieldDef = []
    ) {
        // Remember to call the parent
        parent::__construct($table, $loginField, $fieldDef);
        
        // Set the Model class
        $this->model = MyUserModel::class;
    }

    /**
     * This will be set the mapping; 
     */
    public function getOtherfield()
    {
        return $this->otherfield;
    }
}

/**
 * This class is your model
 * This need to support the basic field plus your new fields
 * already set in your definition class 
 */
class MyUserModel extends UserModel
{
    protected $otherfield;

    public function __construct($name = "", $email = "", $username = "", $password = "", $admin = "no", $field = "")
    {
        parent::__construct($name, $email, $username, $password, $admin);
        $this->setOtherfield($field);
    }

    public function getOtherfield()
    {
        return $this->otherfield;
    }

    public function setOtherfield($otherfield)
    {
        $this->otherfield = $otherfield;
    }
}
```

After that you can use your new definition:

```php
<?php
$users = new ByJG\Authenticate\UsersDBDataset(
    'connection',
    new MyUserDefinition(),
    new \ByJG\Authenticate\Definition\UserPropertiesDefinition()
);
```



## Install

Just type: `composer require "byjg/authuser=2.0.*"`

## Running Tests

Because this project uses PHP Session you need to run the unit test the following manner:
 
```
phpunit --stderr
```

