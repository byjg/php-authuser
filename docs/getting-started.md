---
sidebar_position: 1
title: Getting Started
---

# Getting Started

Auth User PHP is a simple and customizable library for user authentication in PHP applications. It provides a clean repository and service layer architecture for managing users, authentication, and user properties with database storage.

## Quick Example

```php
<?php
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory as DbFactory;
use ByJG\Authenticate\Enum\LoginField;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\Authenticate\Repository\UsersRepository;
use ByJG\Authenticate\Repository\UserPropertiesRepository;
use ByJG\Authenticate\Service\UsersService;
use ByJG\Authenticate\SessionContext;
use ByJG\Cache\Factory;

// Initialize repositories and service
$dbDriver = DbFactory::getDbInstance('mysql://user:pass@host/db');
$db = DatabaseExecutor::using($dbDriver);
$usersRepo = new UsersRepository($db, UserModel::class);
$propsRepo = new UserPropertiesRepository($db, UserPropertiesModel::class);
$users = new UsersService($usersRepo, $propsRepo, LoginField::Username);

// Create and authenticate user
$user = $users->addUser('John Doe', 'johndoe', 'john@example.com', 'SecurePass123');
$user = $users->isValidUser('johndoe', 'SecurePass123');

if ($user !== null) {
    $sessionContext = new SessionContext(Factory::createSessionPool());
    $sessionContext->registerLogin($user->getUserid());
    echo "User authenticated successfully!";
}
```

To authenticate users by email instead of username, create the service with `LoginField::Email`.

## Next Steps

- [Installation](installation.md) - Install the library via Composer
- [User Management](user-management.md) - Learn how to manage users
- [Authentication](authentication.md) - Understand authentication methods
- [Session Context](session-context.md) - Manage user sessions
