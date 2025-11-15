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
$userModel->setRole('user');

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

## Checking User Roles

Users can have assigned roles stored in the `role` field. Use `$user->hasRole()` to check if a user has a specific role:

```php
<?php
/** @var $user \ByJG\Authenticate\Model\UserModel */
if ($user->hasRole('admin')) {
    echo "User is an administrator";
}

if ($user->hasRole('moderator')) {
    echo "User is a moderator";
}

// Set a role
$user->setRole('admin');
$users->save($user);

// Get current role
$role = $user->getRole();
```

The `hasRole()` method performs case-insensitive comparison, so `hasRole('admin')` and `hasRole('ADMIN')` are equivalent.

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
| role       | string\|null        | User's role (admin, moderator, user, etc.) |

## Next Steps

- [Authentication](authentication.md) - Validate user credentials
- [User Properties](user-properties.md) - Store custom user data
- [Password Validation](password-validation.md) - Enforce password policies
