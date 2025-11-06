---
sidebar_position: 7
title: Database Storage
---

# Database Storage

The library supports storing users in relational databases through the `UsersDBDataset` class.

## Database Setup

### Default Schema

The default database structure uses two tables:

```sql
CREATE TABLE users
(
    userid INTEGER AUTO_INCREMENT NOT NULL,
    name VARCHAR(50),
    email VARCHAR(120),
    username VARCHAR(15) NOT NULL,
    password CHAR(40) NOT NULL,
    created DATETIME,
    admin ENUM('Y','N'),

    CONSTRAINT pk_users PRIMARY KEY (userid)
) ENGINE=InnoDB;

CREATE TABLE users_property
(
    customid INTEGER AUTO_INCREMENT NOT NULL,
    name VARCHAR(20),
    value VARCHAR(100),
    userid INTEGER NOT NULL,

    CONSTRAINT pk_custom PRIMARY KEY (customid),
    CONSTRAINT fk_custom_user FOREIGN KEY (userid) REFERENCES users (userid)
) ENGINE=InnoDB;
```

## Basic Usage

### Using Default Configuration

```php
<?php
use ByJG\Authenticate\UsersDBDataset;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\AnyDataset\Db\Factory;

// Create database connection
$dbDriver = Factory::getDbInstance('mysql://user:password@localhost/database');

// Initialize with default configuration
$users = new UsersDBDataset(
    $dbDriver,
    new UserDefinition(),
    new UserPropertiesDefinition()
);
```

### Supported Databases

The library uses [byjg/anydataset-db](https://github.com/byjg/anydataset-db) for database connectivity, which supports:

- MySQL / MariaDB: `mysql://user:password@host/database`
- PostgreSQL: `pgsql://user:password@host/database`
- SQLite: `sqlite:///path/to/database.db`
- MS SQL Server: `sqlsrv://user:password@host/database`
- Oracle: `oci8://user:password@host/database`

## Custom Database Schema

If you have an existing database with different table or column names, use custom definitions.

### Custom Table and Column Names

```php
<?php
use ByJG\Authenticate\Definition\UserDefinition;

$userDefinition = new UserDefinition(
    'my_users_table',              // Table name
    UserModel::class,              // Model class
    UserDefinition::LOGIN_IS_EMAIL, // Login field
    [
        // Map model properties to database columns
        UserDefinition::FIELD_USERID   => 'user_id',
        UserDefinition::FIELD_NAME     => 'full_name',
        UserDefinition::FIELD_EMAIL    => 'email_address',
        UserDefinition::FIELD_USERNAME => 'user_name',
        UserDefinition::FIELD_PASSWORD => 'password_hash',
        UserDefinition::FIELD_CREATED  => 'date_created',
        UserDefinition::FIELD_ADMIN    => 'is_admin'
    ]
);

$users = new UsersDBDataset($dbDriver, $userDefinition);
```

### Custom Properties Table

```php
<?php
use ByJG\Authenticate\Definition\UserPropertiesDefinition;

$propertiesDefinition = new UserPropertiesDefinition(
    'custom_properties',           // Table name
    'prop_id',                     // ID field
    'prop_name',                   // Name field
    'prop_value',                  // Value field
    'user_id'                      // Foreign key to users table
);

$users = new UsersDBDataset(
    $dbDriver,
    $userDefinition,
    $propertiesDefinition
);
```

## Login Field Configuration

You can configure whether users log in with their email or username:

### Login with Email

```php
<?php
$userDefinition = new UserDefinition(
    'users',
    UserModel::class,
    UserDefinition::LOGIN_IS_EMAIL  // Users log in with email
);
```

### Login with Username

```php
<?php
$userDefinition = new UserDefinition(
    'users',
    UserModel::class,
    UserDefinition::LOGIN_IS_USERNAME  // Users log in with username
);
```

:::tip Login Field
The login field affects methods like `isValidUser()` and `getByLoginField()`. They will use the configured field for authentication.
:::

## Complete Example

```php
<?php
use ByJG\Authenticate\UsersDBDataset;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Model\UserModel;
use ByJG\AnyDataset\Db\Factory;

// Database connection
$dbDriver = Factory::getDbInstance('mysql://root:password@localhost/myapp');

// Custom user definition
$userDefinition = new UserDefinition(
    'app_users',
    UserModel::class,
    UserDefinition::LOGIN_IS_EMAIL,
    [
        UserDefinition::FIELD_USERID   => 'id',
        UserDefinition::FIELD_NAME     => 'fullname',
        UserDefinition::FIELD_EMAIL    => 'email',
        UserDefinition::FIELD_USERNAME => 'username',
        UserDefinition::FIELD_PASSWORD => 'pwd',
        UserDefinition::FIELD_CREATED  => 'created_at',
        UserDefinition::FIELD_ADMIN    => 'is_admin'
    ]
);

// Custom properties definition
$propertiesDefinition = new UserPropertiesDefinition(
    'app_user_meta',
    'id',
    'meta_key',
    'meta_value',
    'user_id'
);

// Initialize
$users = new UsersDBDataset($dbDriver, $userDefinition, $propertiesDefinition);

// Use it
$user = $users->addUser('John Doe', 'johndoe', 'john@example.com', 'password123');
```

## XML/File Storage

For simple applications or development, you can use XML file storage:

```php
<?php
use ByJG\Authenticate\UsersAnyDataset;
use ByJG\AnyDataset\Core\AnyDataset;

// Create or load an AnyDataset
$anyDataset = new AnyDataset('/path/to/users.xml');

$users = new UsersAnyDataset($anyDataset);

// Use it the same way as UsersDBDataset
$user = $users->addUser('John Doe', 'johndoe', 'john@example.com', 'password123');
```

:::warning Production Use
XML file storage is suitable for development and small applications. For production applications with multiple users, use database storage.
:::

## Architecture

```text
                                   ┌───────────────────┐
                                   │  SessionContext   │
                                   └───────────────────┘
                                             │
┌────────────────────────┐                                       ┌────────────────────────┐
│     UserDefinition     │─ ─ ┐              │               ─ ─ ┤       UserModel        │
└────────────────────────┘         ┌───────────────────┐    │    └────────────────────────┘
┌────────────────────────┐    └────│  UsersInterface   │────┐    ┌────────────────────────┐
│ UserPropertyDefinition │─ ─ ┘    └───────────────────┘     ─ ─ ┤   UserPropertyModel    │
└────────────────────────┘                   ▲                   └────────────────────────┘
                                             │
                    ┌────────────────────────┼─────────────────────────┐
                    │                        │                         │
                    │                        │                         │
                    │                        │                         │
          ┌───────────────────┐    ┌───────────────────┐    ┌────────────────────┐
          │  UsersAnyDataset  │    │  UsersDBDataset   │    │   Custom Impl.     │
          └───────────────────┘    └───────────────────┘    └────────────────────┘
```

- **UserInterface**: Base interface for all implementations
- **UsersDBDataset**: Database implementation
- **UsersAnyDataset**: XML file implementation
- **UserModel**: The user data model
- **UserPropertyModel**: The user property data model
- **UserDefinition**: Maps model to database schema
- **UserPropertiesDefinition**: Maps properties to database schema

## Next Steps

- [User Management](user-management.md) - Managing users
- [Custom Fields](custom-fields.md) - Extending UserModel
- [Mappers](mappers.md) - Custom field transformations
