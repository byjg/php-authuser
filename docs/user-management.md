---
sidebar_position: 3
title: User Management
---

# User Management

## Creating Users

### Using addUser() Method

The simplest way to add a user:

```php
<?php
$user = $users->addUser(
    'John Doe',           // Full name
    'johndoe',            // Username
    'john@example.com',   // Email
    'SecurePass123'       // Password
);
```

### Using UserModel

For more control, create a `UserModel` instance:

```php
<?php
use ByJG\Authenticate\Model\UserModel;

$userModel = new UserModel();
$userModel->setName('John Doe');
$userModel->setUsername('johndoe');
$userModel->setEmail('john@example.com');
$userModel->setPassword('SecurePass123');
$userModel->setAdmin('no');

$savedUser = $users->save($userModel);
```

## Retrieving Users

### Get User by ID

```php
<?php
$user = $users->getById($userId);
```

### Get User by Email

```php
<?php
$user = $users->getByEmail('john@example.com');
```

### Get User by Username

```php
<?php
$user = $users->getByUsername('johndoe');
```

### Get User by Login Field

The login field is determined by the `UserDefinition` (either email or username):

```php
<?php
$user = $users->getByLoginField('johndoe');
```

### Using Custom Filters

For advanced queries, use `IteratorFilter`:

```php
<?php
use ByJG\AnyDataset\Core\IteratorFilter;
use ByJG\AnyDataset\Core\Enum\Relation;

$filter = new IteratorFilter();
$filter->and('email', Relation::EQUAL, 'john@example.com');
$filter->and('admin', Relation::EQUAL, 'yes');

$user = $users->getUser($filter);
```

## Updating Users

```php
<?php
// Get the user
$user = $users->getById($userId);

// Update fields
$user->setName('Jane Doe');
$user->setEmail('jane@example.com');

// Save changes
$users->save($user);
```

## Deleting Users

### Delete by ID

```php
<?php
$users->removeUserById($userId);
```

### Delete by Login

```php
<?php
$users->removeByLoginField('johndoe');
```

## Checking Admin Status

```php
<?php
/** @var $user \ByJG\Authenticate\Model\UserModel */
if ($user->isAdmin()) {
    echo "User is an administrator";
}
```

The admin field accepts the following values as `true`:
- `yes`, `YES`, `y`, `Y`
- `true`, `TRUE`, `t`, `T`
- `1`
- `s`, `S` (from Portuguese "sim")

## UserModel Properties

The `UserModel` class provides the following properties:

| Property   | Type                | Description                    |
|------------|---------------------|--------------------------------|
| userid     | string\|int\|null   | User ID (auto-generated)       |
| name       | string\|null        | User's full name               |
| email      | string\|null        | User's email address           |
| username   | string\|null        | User's username                |
| password   | string\|null        | User's password (hashed)       |
| created    | string\|null        | Creation timestamp             |
| admin      | string\|null        | Admin flag (yes/no)            |

## Next Steps

- [Authentication](authentication.md) - Validate user credentials
- [User Properties](user-properties.md) - Store custom user data
- [Password Validation](password-validation.md) - Enforce password policies
