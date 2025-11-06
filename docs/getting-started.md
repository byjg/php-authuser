---
sidebar_position: 1
title: Getting Started
---

# Getting Started

Auth User PHP is a simple and customizable library for user authentication in PHP applications. It provides an abstraction layer for managing users, authentication, and user properties, supporting multiple storage backends including databases and XML files.

## Key Features

- **User Management**: Complete CRUD operations for users
- **Authentication**: Validate user credentials and manage sessions
- **User Properties**: Store and retrieve custom user properties
- **JWT Support**: Create and validate JWT tokens for stateless authentication
- **Password Validation**: Built-in password strength validation
- **Flexible Storage**: Support for databases (via AnyDataset) and XML files
- **Session Management**: PSR-6 compatible cache for session storage

## Quick Example

Here's a quick example of how to use the library:

```php
<?php
use ByJG\Authenticate\UsersDBDataset;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\SessionContext;
use ByJG\Cache\Factory;
use ByJG\AnyDataset\Db\Factory as DbFactory;

// Create a database connection
$dbDriver = DbFactory::getDbInstance('mysql://username:password@localhost/database');

// Initialize the user management system
$users = new UsersDBDataset($dbDriver);

// Add a new user
$user = $users->addUser('John Doe', 'johndoe', 'john@example.com', 'SecurePass123');

// Validate user credentials
$user = $users->isValidUser('johndoe', 'SecurePass123');

if ($user !== null) {
    // Create a session
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
