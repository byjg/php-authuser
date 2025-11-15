---
sidebar_position: 7
title: Database Storage
---

# Database Storage

The library uses a repository pattern to store users in relational databases through `UsersRepository` and `UserPropertiesRepository`.

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
    role VARCHAR(20),

    CONSTRAINT pk_users PRIMARY KEY (userid)
) ENGINE=InnoDB;

CREATE TABLE users_property
(
    id INTEGER AUTO_INCREMENT NOT NULL,
    name VARCHAR(20),
    value VARCHAR(100),
    userid INTEGER NOT NULL,

    CONSTRAINT pk_custom PRIMARY KEY (id),
    CONSTRAINT fk_custom_user FOREIGN KEY (userid) REFERENCES users (userid)
) ENGINE=InnoDB;
```

## Basic Usage

### Using Default Configuration

```php
<?php
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\Authenticate\Repository\UsersRepository;
use ByJG\Authenticate\Repository\UserPropertiesRepository;
use ByJG\Authenticate\Service\UsersService;

// Create database connection
$dbDriver = Factory::getDbInstance('mysql://user:password@localhost/database');
$db = DatabaseExecutor::using($dbDriver);

// Initialize repositories with default models
$usersRepo = new UsersRepository($db, UserModel::class);
$propsRepo = new UserPropertiesRepository($db, UserPropertiesModel::class);

// Create service
$users = new UsersService($usersRepo, $propsRepo, UsersService::LOGIN_IS_USERNAME);
```

### Supported Databases

The library uses [byjg/anydataset-db](https://github.com/byjg/anydataset-db) for database connectivity, which supports:

- MySQL / MariaDB: `mysql://user:password@host/database`
- PostgreSQL: `pgsql://user:password@host/database`
- SQLite: `sqlite:///path/to/database.db`
- MS SQL Server: `sqlsrv://user:password@host/database`
- Oracle: `oci8://user:password@host/database`

## Custom Database Schema

If you have an existing database with different table or column names, create a custom UserModel class with different attribute mappings.

### Custom Table and Column Names

```php
<?php
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\MapperFunctions\PasswordSha1Mapper;
use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\MicroOrm\MapperFunctions\ReadOnlyMapper;
use ByJG\MicroOrm\Literal\HexUuidLiteral;

#[TableAttribute(tableName: 'my_users_table')]
class CustomUserModel extends UserModel
{
    #[FieldAttribute(fieldName: 'user_id', primaryKey: true)]
    protected string|int|HexUuidLiteral|null $userid = null;

    #[FieldAttribute(fieldName: 'full_name')]
    protected ?string $name = null;

    #[FieldAttribute(fieldName: 'email_address')]
    protected ?string $email = null;

    #[FieldAttribute(fieldName: 'user_name')]
    protected ?string $username = null;

    #[FieldAttribute(fieldName: 'password_hash', updateFunction: PasswordSha1Mapper::class)]
    protected ?string $password = null;

    #[FieldAttribute(fieldName: 'date_created', updateFunction: ReadOnlyMapper::class)]
    protected ?string $created = null;

    #[FieldAttribute(fieldName: 'is_admin')]
    protected ?string $admin = null;
}

// Use custom model
$usersRepo = new UsersRepository($db, CustomUserModel::class);
```

### Custom Properties Table

```php
<?php
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\MicroOrm\Literal\HexUuidLiteral;

#[TableAttribute(tableName: 'custom_properties')]
class CustomPropertiesModel extends UserPropertiesModel
{
    #[FieldAttribute(fieldName: 'prop_id', primaryKey: true)]
    protected ?string $id = null;

    #[FieldAttribute(fieldName: 'prop_name')]
    protected ?string $name = null;

    #[FieldAttribute(fieldName: 'prop_value')]
    protected ?string $value = null;

    #[FieldAttribute(fieldName: 'user_id')]
    protected string|int|HexUuidLiteral|null $userid = null;
}

// Use custom model
$propsRepo = new UserPropertiesRepository($db, CustomPropertiesModel::class);
```

## Login Field Configuration

You can configure whether users log in with their email or username:

### Login with Email

```php
<?php
$users = new UsersService($usersRepo, $propsRepo, UsersService::LOGIN_IS_EMAIL);
```

### Login with Username

```php
<?php
$users = new UsersService($usersRepo, $propsRepo, UsersService::LOGIN_IS_USERNAME);
```

:::tip Login Field
The login field affects methods like `isValidUser()` and `getByLogin()`. They will use the configured field for authentication.
:::

## Complete Example

```php
<?php
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;
use ByJG\Authenticate\Repository\UsersRepository;
use ByJG\Authenticate\Repository\UserPropertiesRepository;
use ByJG\Authenticate\Service\UsersService;

// Database connection
$dbDriver = Factory::getDbInstance('mysql://root:password@localhost/myapp');
$db = DatabaseExecutor::using($dbDriver);

// Initialize with custom models
$usersRepo = new UsersRepository($db, CustomUserModel::class);
$propsRepo = new UserPropertiesRepository($db, CustomPropertiesModel::class);
$users = new UsersService($usersRepo, $propsRepo, UsersService::LOGIN_IS_EMAIL);

// Use it
$user = $users->addUser('John Doe', 'johndoe', 'john@example.com', 'password123');
```

## Architecture

```text
                                   ┌───────────────────┐
                                   │  SessionContext   │
                                   └───────────────────┘
                                             │
                                             │
                                   ┌───────────────────┐
                                   │  UsersService     │ (Business Logic)
                                   └───────────────────┘
                                             │
                        ┌────────────────────┴────────────────────┐
                        │                                         │
                ┌───────────────────┐                  ┌──────────────────────┐
                │ UsersRepository   │                  │ PropertiesRepository │
                └───────────────────┘                  └──────────────────────┘
                        │                                         │
                ┌───────┴───────┐                      ┌──────────┴──────────┐
                │               │                      │                     │
        ┌───────────────┐  ┌────────┐         ┌───────────────┐    ┌──────────────┐
        │  UserModel    │  │ Mapper │         │ PropsModel    │    │   Mapper     │
        └───────────────┘  └────────┘         └───────────────┘    └──────────────┘
```

- **UsersService**: High-level business logic for user operations
- **UsersRepository**: Data access layer for user records
- **UserPropertiesRepository**: Data access layer for user properties
- **UserModel**: User entity with table/field mapping via attributes
- **UserPropertiesModel**: Properties entity with table/field mapping
- **Mapper**: Field transformation functions (e.g., PasswordSha1Mapper, ReadOnlyMapper)

## Next Steps

- [User Management](user-management.md) - Managing users
- [Custom Fields](custom-fields.md) - Extending UserModel
- [Mappers](mappers.md) - Custom field transformations
