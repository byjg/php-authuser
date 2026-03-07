---
tags: [php, authentication]
---

# User Authentication

A simple and customizable library for user authentication in PHP applications using a clean repository and service layer architecture.

[![Sponsor](https://img.shields.io/badge/Sponsor-%23ea4aaa?logo=githubsponsors&logoColor=white&labelColor=0d1117)](https://github.com/sponsors/byjg)
[![Build Status](https://github.com/byjg/php-authuser/actions/workflows/phpunit.yml/badge.svg?branch=master)](https://github.com/byjg/php-authuser/actions/workflows/phpunit.yml)
[![Opensource ByJG](https://img.shields.io/badge/opensource-byjg-success.svg)](http://opensource.byjg.com)
[![GitHub source](https://img.shields.io/badge/Github-source-informational?logo=github)](https://github.com/byjg/php-authuser/)
[![GitHub license](https://img.shields.io/github/license/byjg/php-authuser.svg)](https://opensource.byjg.com/license)
[![GitHub release](https://img.shields.io/github/release/byjg/php-authuser.svg)](https://github.com/byjg/php-authuser/releases/)

The main purpose is to handle all complexity of user validation, authentication, properties management, and access tokens, abstracting the database layer.
This class can persist user data into session (or file, memcache, etc.) between requests.

## Documentation

- [Getting Started](getting-started)
- [Installation](installation)
- [User Management](user-management)
- [Authentication](authentication)
- [Session Context](session-context)
- [User Properties](user-properties)
- [Database Storage](database-storage)
- [Password Validation](password-validation)
- [JWT Tokens](jwt-tokens)
- [Custom Fields](custom-fields)
- [Mappers](mappers)
- [Examples](examples)

## Quick Start

### Installation

```bash
composer require byjg/authuser
```

See [Installation Guide](installation) for detailed setup instructions and requirements.

## Basic Usage

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

// Create and authenticate a user
$user = $users->addUser('John Doe', 'johndoe', 'john@example.com', 'SecurePass123');
$authenticatedUser = $users->isValidUser('johndoe', 'SecurePass123');

if ($authenticatedUser !== null) {
    $sessionContext = new SessionContext(Factory::createSessionPool());
    $sessionContext->registerLogin($authenticatedUser->getUserid());
    echo "Welcome, " . $authenticatedUser->getName();
}
```

Set the third constructor argument to `LoginField::Email` if you prefer authenticating users by email instead of username.

See [Getting Started](getting-started) for a complete introduction and [Examples](examples) for more use cases.

## Features

- **User Management** - Complete CRUD operations. See [User Management](user-management)
- **Authentication** - Username/email + password or JWT tokens. See [Authentication](authentication) and [JWT Tokens](jwt-tokens)
- **Session Management** - PSR-6 compatible cache storage. See [Session Context](session-context)
- **User Properties** - Store custom key-value metadata. See [User Properties](user-properties)
- **Password Validation** - Built-in strength requirements. See [Password Validation](password-validation)
- **Database Storage** - Supports MySQL, PostgreSQL, SQLite, and more. See [Database Storage](database-storage)
- **Custom Schema** - Map to existing database tables. See [Database Storage](database-storage)
- **Field Mappers** - Transform data during read/write. See [Mappers](mappers)
- **Extensible Model** - Add custom fields easily. See [Custom Fields](custom-fields)

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

## License

This project is licensed under the MIT License - see the [LICENSE](https://github.com/byjg/php-authuser/blob/master/LICENSE) file for details.

## Dependencies

```mermaid
flowchart TD
    byjg/authuser --> byjg/micro-orm
    byjg/authuser --> byjg/cache-engine
    byjg/authuser --> byjg/jwt-wrapper
```

----
[Open source ByJG](http://opensource.byjg.com)
