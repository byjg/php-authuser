---
sidebar_position: 1
title: Getting Started
---

# Getting Started

Auth User PHP is a simple and customizable library for user authentication in PHP applications. It provides an abstraction layer for managing users, authentication, and user properties, supporting multiple storage backends including databases and XML files.

## Quick Example

```php
<?php
use ByJG\Authenticate\UsersDBDataset;
use ByJG\Authenticate\SessionContext;
use ByJG\AnyDataset\Db\Factory as DbFactory;
use ByJG\Cache\Factory;

// Initialize
$users = new UsersDBDataset(DbFactory::getDbInstance('mysql://user:pass@host/db'));

// Create and authenticate user
$user = $users->addUser('John Doe', 'johndoe', 'john@example.com', 'SecurePass123');
$user = $users->isValidUser('johndoe', 'SecurePass123');

if ($user !== null) {
    $sessionContext = new SessionContext(Factory::createSessionPool());
    $sessionContext->registerLogin($user->getUserid());
    echo "User authenticated successfully!";
}
```

## Next Steps

- [Installation](installation.md) - Install the library via Composer
- [User Management](user-management.md) - Learn how to manage users
- [Authentication](authentication.md) - Understand authentication methods
- [Session Context](session-context.md) - Manage user sessions
