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

The `UsersService` provides several methods to retrieve users:

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

The login field is determined by the `UsersService` constructor (either email or username):

```php
<?php
$user = $users->getByLogin('johndoe');
```

### Using Custom Queries

For advanced queries, use the repository directly:

```php
<?php
use ByJG\MicroOrm\Query;

// Get users repository
$usersRepo = $users->getUsersRepository();

// Build custom query
$query = Query::getInstance()
    ->table('users')
    ->where('email = :email', ['email' => 'john@example.com'])
    ->where('admin = :admin', ['admin' => 'yes']);

$results = $usersRepo->getRepository()->getByQuery($query);
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
$users->removeById($userId);
```

### Delete by Login

```php
<?php
$users->removeByLogin('johndoe');
```

## Checking Admin Status

The admin flag is now interpreted entirely inside `UserModel`. Use `$user->isAdmin()` to read the computed boolean value,
and `$user->setAdmin(true)` (or one of the accepted string values) to change it. This replaces the old `$users->isAdmin()`
method that lived in `UsersInterface`.

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
