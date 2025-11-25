---
sidebar_position: 10
title: Custom Fields
---

# Custom Fields

You can extend the `UserModel` to add custom fields that match your database schema.

:::info When to Use This
This guide is for **adding new fields** beyond the standard user fields. If you just need to **map existing database columns** to the standard fields, see [Database Storage](database-storage.md#custom-database-schema) instead.
:::

## Extending UserModel

### Creating a Custom User Model

```php
<?php
namespace App\Model;

use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\Attributes\FieldAttribute;

class CustomUserModel extends UserModel
{
    #[FieldAttribute(fieldName: 'phone')]
    protected ?string $phone = null;

    #[FieldAttribute(fieldName: 'department')]
    protected ?string $department = null;

    #[FieldAttribute(fieldName: 'title')]
    protected ?string $title = null;

    #[FieldAttribute(fieldName: 'profile_picture')]
    protected ?string $profilePicture = null;

    public function __construct(
        string $name = "",
        string $email = "",
        string $username = "",
        string $password = "",
        string $admin = "no",
        string $phone = "",
        string $department = ""
    ) {
        parent::__construct($name, $email, $username, $password, $admin);
        $this->phone = $phone;
        $this->department = $department;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(?string $department): void
    {
        $this->department = $department;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    public function setProfilePicture(?string $profilePicture): void
    {
        $this->profilePicture = $profilePicture;
    }
}
```

## Database Schema

Add the custom fields to your users table:

```sql
CREATE TABLE users
(
    userid INTEGER AUTO_INCREMENT NOT NULL,
    name VARCHAR(50),
    email VARCHAR(120),
    username VARCHAR(15) NOT NULL,
    password CHAR(40) NOT NULL,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME,
    role VARCHAR(20),
    -- Custom fields
    phone VARCHAR(20),
    department VARCHAR(50),
    title VARCHAR(50),
    profile_picture VARCHAR(255),

    CONSTRAINT pk_users PRIMARY KEY (userid)
) ENGINE=InnoDB;
```

## Using the Custom Model

### Initializing the Service

```php
<?php
use ByJG\Authenticate\Enum\LoginField;
use ByJG\Authenticate\Service\UsersService;
use ByJG\Authenticate\Repository\UsersRepository;
use ByJG\Authenticate\Repository\UserPropertiesRepository;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;
use App\Model\CustomUserModel;

// Database connection
$dbDriver = Factory::getDbInstance('mysql://user:password@localhost/database');
$db = DatabaseExecutor::using($dbDriver);

// Initialize repositories with custom model
$usersRepo = new UsersRepository($db, CustomUserModel::class);
$propsRepo = new UserPropertiesRepository($db, UserPropertiesModel::class);

// Create service
$users = new UsersService($usersRepo, $propsRepo, LoginField::Email);

// Using the model directly
$user = new CustomUserModel();
$user->setName('John Doe');
$user->setEmail('john@example.com');
$user->setUsername('johndoe');
$user->setPassword('SecurePass123');
$user->setPhone('+1-555-1234');
$user->setDepartment('Engineering');
$user->setTitle('Senior Developer');

$users->save($user);
```

### Retrieving Users

```php
<?php
$user = $users->getById($userId);

// Access custom fields
echo $user->getName();
echo $user->getPhone();
echo $user->getDepartment();
echo $user->getTitle();
```

### Updating Custom Fields

```php
<?php
$user = $users->getById($userId);
$user->setDepartment('Sales');
$user->setTitle('Sales Manager');
$users->save($user);
```

## Read-Only Fields

You can mark fields as read-only to prevent updates using the `ReadOnlyMapper`:

```php
<?php
use ByJG\MicroOrm\MapperFunctions\ReadOnlyMapper;
use ByJG\MicroOrm\Attributes\FieldAttribute;

class CustomUserModel extends UserModel
{
    // Read-only field - can be set on creation but not updated
    #[FieldAttribute(fieldName: 'created_at', updateFunction: ReadOnlyMapper::class, insertFunction: NowUtcMapper::class)]
    protected ?string $createdAt = null;

    // Read-only custom field
    #[FieldAttribute(fieldName: 'phone', updateFunction: ReadOnlyMapper::class)]
    protected ?string $phone = null;
}
```

Read-only fields:
- Can be set during creation
- Cannot be updated after creation
- Are ignored during updates

## Field Transformation

You can transform fields during read/write operations using mappers. See [Mappers](mappers.md) for details.

### JSON Fields

For storing JSON data in custom fields:

```php
<?php
use ByJG\MicroOrm\FieldMapping\FieldHandler;
use ByJG\MicroOrm\Attributes\FieldAttribute;

class CustomUserModel extends UserModel
{
    #[FieldAttribute(
        fieldName: 'metadata',
        updateFunction: [FieldHandler::class, 'toJson'],
        selectFunction: [FieldHandler::class, 'fromJson']
    )]
    protected ?array $metadata = null;
}
```

### Date/Time Fields

```php
<?php
use ByJG\MicroOrm\FieldMapping\FieldHandler;
use ByJG\MicroOrm\Attributes\FieldAttribute;

class CustomUserModel extends UserModel
{
    #[FieldAttribute(
        fieldName: 'created_at',
        selectFunction: [FieldHandler::class, 'toDate']
    )]
    protected ?\DateTime $createdAt = null;
}
```

## Complete Example

```php
<?php
namespace App;

use ByJG\Authenticate\Enum\LoginField;
use ByJG\Authenticate\Service\UsersService;
use ByJG\Authenticate\Repository\UsersRepository;
use ByJG\Authenticate\Repository\UserPropertiesRepository;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use App\Model\CustomUserModel;

// Database connection
$dbDriver = Factory::getDbInstance('mysql://user:password@localhost/database');
$db = DatabaseExecutor::using($dbDriver);

// Initialize repositories with custom model
$usersRepo = new UsersRepository($db, CustomUserModel::class);
$propsRepo = new UserPropertiesRepository($db, UserPropertiesModel::class);

// Initialize user service
$users = new UsersService($usersRepo, $propsRepo, LoginField::Email);

// Create a user
$user = new CustomUserModel();
$user->setName('Jane Smith');
$user->setEmail('jane@example.com');
$user->setUsername('janesmith');
$user->setPassword('SecurePass123');
$user->setPhone('+1-555-5678');
$user->setDepartment('Marketing');
$user->setTitle('Marketing Director');

$savedUser = $users->save($user);

// Retrieve and update
$user = $users->getById($savedUser->getUserid());
$user->setTitle('VP of Marketing');
$users->save($user);
```

## When to Use Custom Fields vs Properties

| Use Custom Fields When | Use Properties When |
|------------------------|---------------------|
| Field is used frequently | Field is rarely used |
| Field is searched/filtered | Field is key-value metadata |
| Field is fixed schema | Field is dynamic/flexible |
| Better performance needed | Schema flexibility needed |
| Field is required | Field is optional |

## Next Steps

- [Mappers](mappers.md) - Custom field transformations
- [Database Storage](database-storage.md) - Schema configuration
- [User Properties](user-properties.md) - Flexible metadata storage
