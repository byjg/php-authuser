---
sidebar_key: authuser
tags: [php, authentication]
---

# User Authentication

Users, passwords, tokens and per-user properties behind one service, on any database, without adopting a framework.

[![Sponsor](https://img.shields.io/badge/Sponsor-%23ea4aaa?logo=githubsponsors&logoColor=white&labelColor=0d1117)](https://github.com/sponsors/byjg)
[![Build Status](https://github.com/byjg/php-authuser/actions/workflows/phpunit.yml/badge.svg?branch=master)](https://github.com/byjg/php-authuser/actions/workflows/phpunit.yml)
[![Opensource ByJG](https://img.shields.io/badge/opensource-byjg-success.svg)](https://opensource.byjg.com)
[![Install MCP Server](https://img.shields.io/badge/Install-MCP_Server-8A2BE2?logo=modelcontextprotocol&logoColor=white)](https://opensource.byjg.com/docs/ai/mcpserver-byjg-docs/)
[![GitHub source](https://img.shields.io/badge/Github-source-informational?logo=github)](https://github.com/byjg/php-authuser/)
[![GitHub license](https://img.shields.io/github/license/byjg/php-authuser.svg)](https://opensource.byjg.com/license)
[![GitHub release](https://img.shields.io/github/release/byjg/php-authuser.svg)](https://github.com/byjg/php-authuser/releases/)

The main purpose is to handle all complexity of user validation, authentication, properties management, and access tokens, abstracting the database layer.
This class can persist user data into session (or file, memcache, etc.) between requests.

## Why

Authentication is the same in every application: store users and hashed passwords, validate
a login, issue a token, keep a few extra attributes per user. Frameworks bundle it with
everything else; doing it by hand means getting password hashing and tokens right yourself.

AuthUser is that layer on its own, built on a repository and a service:

```php
$users = new UsersService($usersRepo, $propsRepo, LoginField::Username);

$user = $users->addUser('John Doe', 'johndoe', 'john@example.com', 'SecurePass123');
$users->addProperty($user->getUserid(), 'department', 'Engineering');
$engineers = $users->getUsersByProperty('department', 'Engineering');
```

- **Properties without schema changes.** Any number of key/value attributes per user, and
  queries by them, without adding columns.
- **Fits your schema.** Mappers and custom fields adapt it to an existing users table.
- **Sessions or JWT.** The authenticated user is kept in a session context, or issued as a
  JWT token.

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

## Installation

```bash
composer require byjg/authuser
```

See [Installation Guide](docs/installation.md) for detailed setup instructions and requirements.

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

See [Getting Started](docs/getting-started.md) for a complete introduction and [Examples](docs/examples.md) for more use cases.

## Features

- **User Management** - Complete CRUD operations. See [User Management](docs/user-management.md)
- **Authentication** - Username/email + password or JWT tokens. See [Authentication](docs/authentication.md) and [JWT Tokens](docs/jwt-tokens.md)
- **Session Management** - PSR-6 compatible cache storage. See [Session Context](docs/session-context.md)
- **User Properties** - Store custom key-value metadata. See [User Properties](docs/user-properties.md)
- **Password Validation** - Built-in strength requirements. See [Password Validation](docs/password-validation.md)
- **Database Storage** - Supports MySQL, PostgreSQL, SQLite, and more. See [Database Storage](docs/database-storage.md)
- **Custom Schema** - Map to existing database tables. See [Database Storage](docs/database-storage.md)
- **Field Mappers** - Transform data during read/write. See [Mappers](docs/mappers.md)
- **Extensible Model** - Add custom fields easily. See [Custom Fields](docs/custom-fields.md)

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

## Running Tests

Because this project uses PHP Session you need to run the unit test the following manner:

```bash
./vendor/bin/phpunit --stderr
```

----
[Open source ByJG](https://opensource.byjg.com)
