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

class CustomUserModel extends UserModel
{
    protected ?string $phone = null;
    protected ?string $department = null;
    protected ?string $title = null;
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
    created DATETIME,
    admin ENUM('Y','N'),
    -- Custom fields
    phone VARCHAR(20),
    department VARCHAR(50),
    title VARCHAR(50),
    profile_picture VARCHAR(255),

    CONSTRAINT pk_users PRIMARY KEY (userid)
) ENGINE=InnoDB;
```

## Configuring UserDefinition

Map the custom fields in your `UserDefinition`:

```php
<?php
use ByJG\Authenticate\Definition\UserDefinition;
use App\Model\CustomUserModel;

$userDefinition = new UserDefinition(
    'users',                      // Table name
    CustomUserModel::class,       // Your custom model class
    UserDefinition::LOGIN_IS_EMAIL,
    [
        // Standard fields
        UserDefinition::FIELD_USERID   => 'userid',
        UserDefinition::FIELD_NAME     => 'name',
        UserDefinition::FIELD_EMAIL    => 'email',
        UserDefinition::FIELD_USERNAME => 'username',
        UserDefinition::FIELD_PASSWORD => 'password',
        UserDefinition::FIELD_CREATED  => 'created',
        UserDefinition::FIELD_ADMIN    => 'admin',
        // Custom fields
        'phone'                        => 'phone',
        'department'                   => 'department',
        'title'                        => 'title',
        'profilePicture'               => 'profile_picture'
    ]
);
```

## Using the Custom Model

### Creating Users

```php
<?php
use ByJG\Authenticate\UsersDBDataset;

$users = new UsersDBDataset($dbDriver, $userDefinition);

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
$user = $users->get($userId);

// Access custom fields
echo $user->getName();
echo $user->getPhone();
echo $user->getDepartment();
echo $user->getTitle();
```

### Updating Custom Fields

```php
<?php
$user = $users->get($userId);
$user->setDepartment('Sales');
$user->setTitle('Sales Manager');
$users->save($user);
```

## Read-Only Fields

You can mark fields as read-only to prevent updates:

```php
<?php
// Make 'created' field read-only
$userDefinition->markPropertyAsReadOnly(UserDefinition::FIELD_CREATED);

// Make custom field read-only
$userDefinition->markPropertyAsReadOnly('phone');
```

Read-only fields:
- Can be set during creation
- Cannot be updated after creation
- Are ignored during updates

## Auto-Generated Fields

### Auto-Increment IDs

For auto-increment IDs, the database handles generation automatically. No configuration needed.

### UUID Fields

For UUID primary keys:

```php
<?php
use ByJG\Authenticate\MapperFunctions\UserIdGeneratorMapper;

$userDefinition->defineGenerateKey(UserIdGeneratorMapper::class);
```

### Custom ID Generation

Create a custom mapper for custom ID generation:

```php
<?php
use ByJG\MicroOrm\Interface\MapperFunctionInterface;

class CustomIdMapper implements MapperFunctionInterface
{
    public function processedValue(mixed $value, mixed $instance): mixed
    {
        if (empty($value)) {
            return 'USER_' . uniqid() . '_' . time();
        }
        return $value;
    }
}

// Use it
$userDefinition->defineGenerateKey(CustomIdMapper::class);
```

## Field Transformation

You can transform fields during read/write operations using mappers. See [Mappers](mappers.md) for details.

## Complex Data Types

### JSON Fields

For storing JSON data in custom fields:

```php
<?php
use ByJG\MicroOrm\Interface\MapperFunctionInterface;

class JsonMapper implements MapperFunctionInterface
{
    public function processedValue(mixed $value, mixed $instance): mixed
    {
        if (is_array($value)) {
            return json_encode($value);
        }
        return $value;
    }
}

class JsonDecodeMapper implements MapperFunctionInterface
{
    public function processedValue(mixed $value, mixed $instance): mixed
    {
        if (is_string($value)) {
            return json_decode($value, true);
        }
        return $value;
    }
}

// Configure mappers
$userDefinition->defineMapperForUpdate('metadata', JsonMapper::class);
$userDefinition->defineMapperForSelect('metadata', JsonDecodeMapper::class);
```

### Date/Time Fields

```php
<?php
use ByJG\MicroOrm\Interface\MapperFunctionInterface;

class DateTimeMapper implements MapperFunctionInterface
{
    public function processedValue(mixed $value, mixed $instance): mixed
    {
        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        }
        return $value;
    }
}

$userDefinition->defineMapperForUpdate('created', DateTimeMapper::class);
```

## Complete Example

```php
<?php
namespace App;

use ByJG\Authenticate\UsersDBDataset;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\AnyDataset\Db\Factory;
use App\Model\CustomUserModel;

// Database connection
$dbDriver = Factory::getDbInstance('mysql://user:password@localhost/database');

// Define custom user table
$userDefinition = new UserDefinition(
    'users',
    CustomUserModel::class,
    UserDefinition::LOGIN_IS_EMAIL,
    [
        UserDefinition::FIELD_USERID   => 'userid',
        UserDefinition::FIELD_NAME     => 'name',
        UserDefinition::FIELD_EMAIL    => 'email',
        UserDefinition::FIELD_USERNAME => 'username',
        UserDefinition::FIELD_PASSWORD => 'password',
        UserDefinition::FIELD_CREATED  => 'created',
        UserDefinition::FIELD_ADMIN    => 'admin',
        'phone'                        => 'phone',
        'department'                   => 'department',
        'title'                        => 'title',
        'profilePicture'               => 'profile_picture'
    ]
);

// Make created field read-only
$userDefinition->markPropertyAsReadOnly(UserDefinition::FIELD_CREATED);

// Initialize user management
$users = new UsersDBDataset($dbDriver, $userDefinition);

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
$user = $users->get($savedUser->getUserid());
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
