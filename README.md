# Auth User PHP

[![Build Status](https://github.com/byjg/php-authuser/actions/workflows/phpunit.yml/badge.svg?branch=master)](https://github.com/byjg/php-authuser/actions/workflows/phpunit.yml)
[![Opensource ByJG](https://img.shields.io/badge/opensource-byjg-success.svg)](http://opensource.byjg.com)
[![GitHub source](https://img.shields.io/badge/Github-source-informational?logo=github)](https://github.com/byjg/php-authuser/)
[![GitHub license](https://img.shields.io/github/license/byjg/php-authuser.svg)](https://opensource.byjg.com/opensource/licensing.html)
[![GitHub release](https://img.shields.io/github/release/byjg/php-authuser.svg)](https://github.com/byjg/php-authuser/releases/)

A simple and customizable library for user authentication in PHP applications. It supports multiple storage backends including databases and XML files.

The main purpose is to handle all complexity of user validation, authentication, properties management, and access tokens, abstracting the database layer.
This class can persist user data into session (or file, memcache, etc.) between requests.

## Documentation

- [Getting Started](docs/getting-started.md)
- [Installation](docs/installation.md)
- [User Management](docs/user-management.md)
- [Authentication](docs/authentication.md)
- [Session Context](docs/session-context.md)
- [User Properties](docs/user-properties.md)
- [Database Storage](docs/database-storage.md)
- [Password Validation](docs/password-validation.md)
- [JWT Tokens](docs/jwt-tokens.md)
- [Custom Fields](docs/custom-fields.md)
- [Mappers](docs/mappers.md)
- [Examples](docs/examples.md)

## Quick Start

### Installation

```bash
composer require byjg/authuser
```

### Using Database Storage

```php
<?php
use ByJG\Authenticate\UsersDBDataset;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\AnyDataset\Db\Factory;

// Create database connection
$dbDriver = Factory::getDbInstance('mysql://user:password@localhost/database');

// Initialize user management
$users = new UsersDBDataset(
    $dbDriver,
    new UserDefinition(),              // Field metadata for users table
    new UserPropertiesDefinition()     // Field metadata for user properties table
);
```

### Using XML File Storage

```php
<?php
use ByJG\Authenticate\UsersAnyDataset;
use ByJG\AnyDataset\Core\AnyDataset;

// Create or load AnyDataset
$anyDataset = new AnyDataset('/tmp/users.xml');

// Initialize user management
$users = new UsersAnyDataset($anyDataset);
```

*Note*: See the [AnyDataset DB project](https://github.com/byjg/anydataset-db) for available databases and connection strings.

## Basic Usage

### Creating and Authenticating Users

```php
<?php
use ByJG\Authenticate\SessionContext;
use ByJG\Cache\Factory;

// Add a new user
$user = $users->addUser('John Doe', 'johndoe', 'john@example.com', 'SecurePass123');

// Validate user credentials
$authenticatedUser = $users->isValidUser('johndoe', 'SecurePass123');

if ($authenticatedUser !== null) {
    // Create session context
    $sessionContext = new SessionContext(Factory::createSessionPool());

    // Register the login
    $sessionContext->registerLogin($authenticatedUser->getUserid());

    echo "Welcome, " . $authenticatedUser->getName();
}
```

### Check if User is Authenticated

```php
<?php
$sessionContext = new SessionContext(Factory::createSessionPool());

// Check if the user is authenticated
if ($sessionContext->isAuthenticated()) {
    // Get the userId of the authenticated user
    $userId = $sessionContext->userInfo();

    // Get the user and display name
    $user = $users->getById($userId);
    echo "Hello: " . $user->getName();
} else {
    echo "Please log in";
}
```

## Managing Session Data

You can store temporary data in the user session that exists only while the user is logged in. Once the user logs out, the data is automatically released.

### Store Session Data

```php
<?php
$sessionContext = new SessionContext(Factory::createSessionPool());

// User must be authenticated
$sessionContext->setSessionData('shopping_cart', [
    'item1' => 'Product A',
    'item2' => 'Product B'
]);

$sessionContext->setSessionData('last_page', '/products');
```

### Retrieve Session Data

```php
<?php
$sessionContext = new SessionContext(Factory::createSessionPool());

$cart = $sessionContext->getSessionData('shopping_cart');
$lastPage = $sessionContext->getSessionData('last_page');
```

:::note
A `NotAuthenticatedException` will be thrown if the user is not authenticated when accessing session data.
:::

## Managing User Properties

User properties allow you to store custom key-value data associated with users permanently.

### Add Custom Properties

```php
<?php
// Add a property to a user
$users->addProperty($userId, 'phone', '555-1234');
$users->addProperty($userId, 'department', 'Engineering');

// Users can have multiple values for the same property
$users->addProperty($userId, 'role', 'developer');
$users->addProperty($userId, 'role', 'manager');
```

### Using UserModel

```php
<?php
$user = $users->getById($userId);

// Set a property (update or create)
$user->set('phone', '555-1234');

// Save changes
$users->save($user);
```

## Logout

```php
<?php
$sessionContext->registerLogout();
```

## JWT Token Authentication

For stateless API authentication, you can use JWT tokens:

```php
<?php
use ByJG\JwtWrapper\JwtKeySecret;
use ByJG\JwtWrapper\JwtWrapper;

// Create JWT wrapper
$jwtKey = new JwtKeySecret('your-secret-key');
$jwtWrapper = new JwtWrapper($jwtKey);

// Create authentication token
$token = $users->createAuthToken(
    'johndoe',           // Login
    'SecurePass123',     // Password
    $jwtWrapper,
    3600,                // Expires in 1 hour (seconds)
    [],                  // Additional user info to save
    ['role' => 'admin']  // Additional token data
);

// Validate token
$result = $users->isValidToken('johndoe', $jwtWrapper, $token);
if ($result !== null) {
    $user = $result['user'];
    $tokenData = $result['data'];
}
```

See [JWT Tokens](docs/jwt-tokens.md) for detailed information.

## Database Schema

The default database schema uses two tables:

```sql
CREATE TABLE users (
    userid INTEGER AUTO_INCREMENT NOT NULL,
    name VARCHAR(50),
    email VARCHAR(120),
    username VARCHAR(15) NOT NULL,
    password CHAR(40) NOT NULL,
    created DATETIME,
    admin ENUM('Y','N'),
    CONSTRAINT pk_users PRIMARY KEY (userid)
) ENGINE=InnoDB;

CREATE TABLE users_property (
    customid INTEGER AUTO_INCREMENT NOT NULL,
    name VARCHAR(20),
    value VARCHAR(100),
    userid INTEGER NOT NULL,
    CONSTRAINT pk_custom PRIMARY KEY (customid),
    CONSTRAINT fk_custom_user FOREIGN KEY (userid) REFERENCES users (userid)
) ENGINE=InnoDB;
```

You can customize table and column names using `UserDefinition` and `UserPropertiesDefinition`. See [Database Storage](docs/database-storage.md) for details.

## Features

- **Complete User Management** - Create, read, update, and delete users
- **Flexible Authentication** - Username/email + password or JWT tokens
- **Session Management** - PSR-6 compatible cache storage
- **User Properties** - Store custom key-value data per user
- **Password Validation** - Built-in password strength requirements
- **Multiple Storage Backends** - Database (MySQL, PostgreSQL, SQLite, etc.) or XML files
- **Customizable Schema** - Map to existing database tables
- **Field Mappers** - Transform data during read/write operations
- **Extensible User Model** - Add custom fields easily

## Running Tests

Because this project uses PHP Session you need to run the unit test the following manner:

```bash
./vendor/bin/phpunit --stderr
```

## Architecture

```text
                                   ┌───────────────────┐
                                   │  SessionContext   │
                                   └───────────────────┘
                                             │
┌────────────────────────┐                                       ┌────────────────────────┐
│     UserDefinition     │─ ─ ┐              │               ─ ─ ┤       UserModel        │
└────────────────────────┘         ┌───────────────────┐    │    └────────────────────────┘
┌────────────────────────┐    └────│  UsersInterface   │────┐    ┌────────────────────────┐
│ UserPropertyDefinition │─ ─ ┘    └───────────────────┘     ─ ─ ┤   UserPropertyModel    │
└────────────────────────┘                   ▲                   └────────────────────────┘
                                             │
                    ┌────────────────────────┼─────────────────────────┐
                    │                        │                         │
                    │                        │                         │
                    │                        │                         │
          ┌───────────────────┐    ┌───────────────────┐    ┌────────────────────┐
          │  UsersAnyDataset  │    │  UsersDBDataset   │    │   Custom Impl.     │
          └───────────────────┘    └───────────────────┘    └────────────────────┘
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Dependencies

```mermaid
flowchart TD
    byjg/authuser --> byjg/micro-orm
    byjg/authuser --> byjg/cache-engine
    byjg/authuser --> byjg/jwt-wrapper
```

----
[Open source ByJG](http://opensource.byjg.com)
