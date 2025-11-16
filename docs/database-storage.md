---
sidebar_position: 7
title: Database Storage
---

# Database Storage

The library uses a repository pattern to store users in relational databases through `UsersRepository` and `UserPropertiesRepository`.

## Database Setup

### Default Schema

The default database structure uses two tables. Below are the schema definitions for different databases:

<details>
<summary>MySQL / MariaDB</summary>

```sql
CREATE TABLE users
(
    userid INTEGER AUTO_INCREMENT NOT NULL,
    name VARCHAR(50),
    email VARCHAR(120),
    username VARCHAR(15) NOT NULL,
    password CHAR(40) NOT NULL,
    created_at DATETIME DEFAULT (now()),
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME,
    role VARCHAR(20),

    CONSTRAINT pk_users PRIMARY KEY (userid),
    CONSTRAINT ix_username UNIQUE (username),
    CONSTRAINT ix_users_2 UNIQUE (email),
    INDEX ix_users_email (email ASC, password ASC),
    INDEX ix_users_username (username ASC, password ASC)
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

</details>

<details>
<summary>SQLite</summary>

```sql
CREATE TABLE users
(
    userid INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    name VARCHAR(50),
    email VARCHAR(120),
    username VARCHAR(15) NOT NULL,
    password CHAR(40) NOT NULL,
    created_at DATETIME DEFAULT (datetime('now')),
    updated_at DATETIME DEFAULT (datetime('now')),
    deleted_at DATETIME,
    role VARCHAR(20),

    CONSTRAINT ix_username UNIQUE (username),
    CONSTRAINT ix_users_2 UNIQUE (email)
);

CREATE INDEX ix_users_email ON users (email ASC, password ASC);
CREATE INDEX ix_users_username ON users (username ASC, password ASC);

CREATE TRIGGER update_users_updated_at
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    UPDATE users SET updated_at = datetime('now') WHERE userid = NEW.userid;
END;

CREATE TABLE users_property
(
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    name VARCHAR(20),
    value VARCHAR(100),
    userid INTEGER NOT NULL,

    CONSTRAINT fk_custom_user FOREIGN KEY (userid) REFERENCES users (userid)
);
```

</details>

<details>
<summary>PostgreSQL</summary>

```sql
CREATE TABLE users
(
    userid SERIAL NOT NULL,
    name VARCHAR(50),
    email VARCHAR(120),
    username VARCHAR(15) NOT NULL,
    password CHAR(40) NOT NULL,
    created_at TIMESTAMP DEFAULT now(),
    updated_at TIMESTAMP DEFAULT now(),
    deleted_at TIMESTAMP,
    role VARCHAR(20),

    CONSTRAINT pk_users PRIMARY KEY (userid),
    CONSTRAINT ix_username UNIQUE (username),
    CONSTRAINT ix_users_2 UNIQUE (email)
);

CREATE INDEX ix_users_email ON users (email ASC, password ASC);
CREATE INDEX ix_users_username ON users (username ASC, password ASC);

CREATE OR REPLACE FUNCTION update_users_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_update_users_updated_at
BEFORE UPDATE ON users
FOR EACH ROW
EXECUTE FUNCTION update_users_updated_at();

CREATE TABLE users_property
(
    id SERIAL NOT NULL,
    name VARCHAR(20),
    value VARCHAR(100),
    userid INTEGER NOT NULL,

    CONSTRAINT pk_custom PRIMARY KEY (id),
    CONSTRAINT fk_custom_user FOREIGN KEY (userid) REFERENCES users (userid)
);
```

</details>

<details>
<summary>SQL Server</summary>

```sql
CREATE TABLE users
(
    userid INTEGER IDENTITY(1,1) NOT NULL,
    name VARCHAR(50),
    email VARCHAR(120),
    username VARCHAR(15) NOT NULL,
    password CHAR(40) NOT NULL,
    created_at DATETIME DEFAULT GETDATE(),
    updated_at DATETIME DEFAULT GETDATE(),
    deleted_at DATETIME,
    role VARCHAR(20),

    CONSTRAINT pk_users PRIMARY KEY (userid),
    CONSTRAINT ix_username UNIQUE (username),
    CONSTRAINT ix_users_2 UNIQUE (email)
);

CREATE INDEX ix_users_email ON users (email ASC, password ASC);
CREATE INDEX ix_users_username ON users (username ASC, password ASC);

GO
CREATE TRIGGER trigger_update_users_updated_at
ON users
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE users
    SET updated_at = GETDATE()
    FROM users u
    INNER JOIN inserted i ON u.userid = i.userid;
END;
GO

CREATE TABLE users_property
(
    id INTEGER IDENTITY(1,1) NOT NULL,
    name VARCHAR(20),
    value VARCHAR(100),
    userid INTEGER NOT NULL,

    CONSTRAINT pk_custom PRIMARY KEY (id),
    CONSTRAINT fk_custom_user FOREIGN KEY (userid) REFERENCES users (userid)
);
```

</details>

## Basic Usage

### Using Default Configuration

```php
<?php
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;
use ByJG\Authenticate\Enum\LoginField;
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
$users = new UsersService($usersRepo, $propsRepo, LoginField::Username);
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
use ByJG\MicroOrm\MapperFunctions\NowUtcMapper;
use ByJG\MicroOrm\Literal\Literal;

#[TableAttribute(tableName: 'my_users_table')]
class CustomUserModel extends UserModel
{
    #[FieldAttribute(fieldName: 'user_id', primaryKey: true)]
    protected string|int|Literal|null $userid = null;

    #[FieldAttribute(fieldName: 'full_name')]
    protected ?string $name = null;

    #[FieldAttribute(fieldName: 'email_address')]
    protected ?string $email = null;

    #[FieldAttribute(fieldName: 'user_name')]
    protected ?string $username = null;

    #[FieldAttribute(fieldName: 'password_hash', updateFunction: PasswordSha1Mapper::class)]
    protected ?string $password = null;

    #[FieldAttribute(fieldName: 'date_created', updateFunction: ReadOnlyMapper::class, insertFunction: NowUtcMapper::class)]
    protected ?string $createdAt = null;

    #[FieldAttribute(fieldName: 'date_updated', updateFunction: NowUtcMapper::class)]
    protected ?string $updatedAt = null;

    #[FieldAttribute(fieldName: 'date_deleted', syncWithDb: false)]
    protected ?string $deletedAt = null;

    #[FieldAttribute(fieldName: 'user_role')]
    protected ?string $role = null;
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
use ByJG\MicroOrm\Literal\Literal;

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
    protected string|int|Literal|null $userid = null;
}

// Use custom model
$propsRepo = new UserPropertiesRepository($db, CustomPropertiesModel::class);
```

## Login Field Configuration

You can configure whether users log in with their email or username:

### Login with Email

```php
<?php
use ByJG\Authenticate\Enum\LoginField;

$users = new UsersService($usersRepo, $propsRepo, LoginField::Email);
```

### Login with Username

```php
<?php
$users = new UsersService($usersRepo, $propsRepo, LoginField::Username);
```

:::tip Login Field
The login field affects methods like `isValidUser()` and `getByLogin()`. They will use the configured field for authentication.
:::
`LoginField` is available under the `ByJG\Authenticate\Enum` namespace.

## Complete Example

```php
<?php
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;
use ByJG\Authenticate\Enum\LoginField;
use ByJG\Authenticate\Repository\UsersRepository;
use ByJG\Authenticate\Repository\UserPropertiesRepository;
use ByJG\Authenticate\Service\UsersService;

// Database connection
$dbDriver = Factory::getDbInstance('mysql://root:password@localhost/myapp');
$db = DatabaseExecutor::using($dbDriver);

// Initialize with custom models
$usersRepo = new UsersRepository($db, CustomUserModel::class);
$propsRepo = new UserPropertiesRepository($db, CustomPropertiesModel::class);
$users = new UsersService($usersRepo, $propsRepo, LoginField::Email);

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
