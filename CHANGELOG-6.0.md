# Changelog for Version 6.0

## Overview

Version 6.0 represents a major architectural overhaul of the php-authuser library, transitioning from a dataset-based approach to a modern repository and service layer architecture. This release introduces significant improvements in code organization, type safety, and extensibility while maintaining backward compatibility where possible.

## New Features

### Repository and Service Layer Architecture
- **New `UsersRepository` class**: Modern repository pattern implementation for user data persistence
- **New `UserPropertiesRepository` class**: Dedicated repository for managing user properties
- **New `UsersService` class**: Service layer encapsulating business logic for user operations
- **New `UsersServiceInterface`**: Interface defining the contract for user service implementations

### Enum-Based Field Management
- **`LoginField` enum**: Type-safe login field selection (Username, Email, etc.) replacing string constants
- **`UserField` enum**: Structured field mapping for user model attributes
- **`UserPropertyField` enum**: Field mapping for user properties with enhanced type handling

### Enhanced Model Features
- **Timestamp fields**: Added `createdAt`, `updatedAt`, and `deletedAt` fields using MicroORM traits
  - Replaces the legacy `created` field with proper timestamp management
  - Automatic timestamp updates on create/update operations
  - Soft delete support with `deletedAt` field
- **Role-based access**: Replaced binary `admin` field with flexible `role` field
  - Support for multiple role types (not limited to admin/non-admin)
  - Case-insensitive role checking with `hasRole()` method
- **ORM Attributes**: Full integration with PHP 8 attributes for field mapping
  - `#[TableAttribute]` for table configuration
  - `#[FieldAttribute]` for field-level metadata
  - Built-in support for password mappers via attributes

### Password Management
- **`PasswordMapperInterface`**: Standardized interface for password encryption handlers
- **`PasswordSha1Mapper`**: SHA-1 password hashing implementation with encryption detection
- **`PasswordMd5Mapper`**: MD5 password hashing implementation (available in tests/fixtures)
- Enhanced password validation and automatic encryption during save operations

### Token Management
- **`UserToken` model**: Structured token representation replacing raw strings/arrays
- Consistent token-related method return types across the service layer
- Enhanced JWT token payloads with improved field mapping

### Mapper Functions
- **`ClosureMapper`**: Flexible field transformation using closures
- **`UserIdGeneratorMapper`**: Custom user ID generation support
- **`MapperFunctionInterface`**: Migration from `UniqueIdGeneratorInterface` for extensible field mapping

### Documentation
- Comprehensive Docusaurus-based documentation
- New documentation pages:
  - Getting Started Guide
  - Installation Instructions
  - User Management
  - Authentication
  - Session Context
  - User Properties
  - Database Storage
  - Password Validation
  - JWT Tokens
  - Custom Fields
  - Mappers
  - Examples

## Bug Fixes

- Enhanced field mapping error handling in `UserPropertiesRepository`
- Improved default value handling in `resolveUserFieldValue` method
- Fixed type assertions for better compatibility with PHP 8.3+
- Corrected PSalm static analysis issues
- Fixed broken license links in README

## Breaking Changes

| Component | Before (5.x) | After (6.0) | Description |
|-----------|--------------|-------------|-------------|
| **PHP Version** | `>=8.1 <8.4` | `>=8.3 <8.6` | Minimum PHP version raised to 8.3 |
| **Dependencies** | `byjg/micro-orm: ^5.0`<br>`byjg/cache-engine: ^5.0` | `byjg/micro-orm: ^6.0`<br>`byjg/cache-engine: ^6.0` | Updated to version 6.x of core dependencies |
| **Main Class** | `UsersDBDataset` | `UsersService` + `UsersRepository` | Replaced dataset pattern with repository/service pattern |
| **Legacy Classes** | `UsersAnyDataset`<br>`UsersDBDataset`<br>`UsersBase` | **Removed** | Migrate to `UsersRepository` + `UsersService` |
| **Definition Classes** | `UserDefinition`<br>`UserPropertiesDefinition` | **Removed** | Use ORM attributes on model classes instead |
| **Field Reference** | String constants | `LoginField` enum | Use `LoginField::Username` or `LoginField::Email` |
| **Type Hints** | `HexUuidLiteral` | `Literal` | Generic `Literal` class replaces `HexUuidLiteral` |
| **User Field: admin** | `$user->getAdmin()`<br>`$user->setAdmin('yes')` | `$user->getRole()`<br>`$user->setRole('admin')`<br>`$user->hasRole('admin')` | Admin is now a role value, not a separate field |
| **User Field: created** | `$user->getCreated()`<br>`$user->setCreated($date)` | `$user->getCreatedAt()`<br>Auto-managed | Renamed to `createdAt` and auto-managed by ORM |
| **Timestamp Fields** | Manual `created` field | `createdAt`, `updatedAt`, `deletedAt` traits | Automatic timestamp management |
| **User Retrieval** | `getById($id)`<br>`getByEmail($email)`<br>`getByUsername($username)`<br>`getByLoginField($field, $value)` | `get($field, $value)` | Consolidated into single `get()` method |
| **isAdmin() Method** | `$usersService->isAdmin($user)` | `$user->hasRole('admin')` | Moved from service to model as `hasRole()` |
| **Token Returns** | String or array | `UserToken` object | Token methods now return `UserToken` instances |
| **ID Generator** | `UniqueIdGeneratorInterface` | `MapperFunctionInterface` | Use mapper functions for custom ID generation |
| **Constructor (UsersDBDataset)** | `DbDriverInterface\|DatabaseExecutor` | N/A - Use `UsersRepository` | Parameter order changed in legacy class before removal |
| **Interfaces** | `UsersInterface` | `UsersServiceInterface` | Interface renamed and restructured |
| **Exceptions** | `NotImplementedException` | **Removed** | Standard exceptions used instead |
| **Entity Processors** | `ClosureEntityProcessor`<br>`PassThroughEntityProcessor` | **Removed** | Use mapper functions instead |

## Upgrade Path from 5.x to 6.x

### Step 1: Update Dependencies

Update your `composer.json`:

```json
{
    "require": {
        "php": ">=8.3",
        "byjg/authuser": "^6.0"
    }
}
```

Run:
```bash
composer update byjg/authuser
```

### Step 2: Migrate from UsersDBDataset to Repository + Service Pattern

**Before (5.x):**
```php
use ByJG\Authenticate\UsersDBDataset;
use ByJG\Authenticate\Definition\UserDefinition;

$dbDriver = DbFactory::getDbInstance('mysql://...');
$userDefinition = new UserDefinition($dbDriver);
$users = new UsersDBDataset($dbDriver, $userDefinition);

// Add user
$user = $users->addUser('John', 'john', 'john@example.com', 'password');

// Get user by email
$user = $users->getByEmail('john@example.com');

// Check if admin
if ($users->isAdmin($user)) {
    // admin logic
}
```

**After (6.0):**
```php
use ByJG\Authenticate\Repository\UsersRepository;
use ByJG\Authenticate\Repository\UserPropertiesRepository;
use ByJG\Authenticate\Service\UsersService;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\Authenticate\Enum\LoginField;
use ByJG\AnyDataset\Db\DatabaseExecutor;

$dbDriver = DbFactory::getDbInstance('mysql://...');
$db = DatabaseExecutor::using($dbDriver);

$usersRepo = new UsersRepository($db, UserModel::class);
$propsRepo = new UserPropertiesRepository($db, UserPropertiesModel::class);
$users = new UsersService($usersRepo, $propsRepo, LoginField::Username);

// Add user (role parameter is now optional, defaults to empty string)
$user = $users->addUser('John', 'john', 'john@example.com', 'password', 'admin');

// Get user by email
$user = $users->get(LoginField::Email, 'john@example.com');

// Check if admin
if ($user->hasRole('admin')) {
    // admin logic
}
```

### Step 3: Update User Model Field Access

**Before (5.x):**
```php
// Admin field
$user->setAdmin('yes');
$isAdmin = ($user->getAdmin() === 'yes');

// Created timestamp
$created = $user->getCreated();
$user->setCreated(date('Y-m-d H:i:s'));
```

**After (6.0):**
```php
// Role field
$user->setRole('admin');
$isAdmin = $user->hasRole('admin');

// Timestamp fields (auto-managed)
$createdAt = $user->getCreatedAt();
$updatedAt = $user->getUpdatedAt();
$deletedAt = $user->getDeletedAt();
// No manual setters needed - handled by ORM traits
```

### Step 4: Update Field References to Use Enums

**Before (5.x):**
```php
$user = $users->getByLoginField('email', 'john@example.com');
```

**After (6.0):**
```php
use ByJG\Authenticate\Enum\LoginField;

$user = $users->get(LoginField::Email, 'john@example.com');
```

### Step 5: Update Type Hints

**Before (5.x):**
```php
use ByJG\MicroOrm\Literal\HexUuidLiteral;

function setUserId(HexUuidLiteral $id) {
    // ...
}
```

**After (6.0):**
```php
use ByJG\MicroOrm\Literal\Literal;

function setUserId(Literal $id) {
    // ...
}
```

### Step 6: Update Token Handling

**Before (5.x):**
```php
$token = $users->createAuthToken($userId); // Returns string or array
if (is_string($token)) {
    // handle token
}
```

**After (6.0):**
```php
use ByJG\Authenticate\Model\UserToken;

$token = $users->createAuthToken($userId); // Returns UserToken object
echo $token->getToken();
echo $token->getUserId();
```

### Step 7: Update Custom Field Mapping

**Before (5.x):**
```php
use ByJG\Authenticate\Interfaces\UniqueIdGeneratorInterface;

class MyIdGenerator implements UniqueIdGeneratorInterface {
    // implementation
}
```

**After (6.0):**
```php
use ByJG\MicroOrm\MapperFunctionInterface;

class MyIdGenerator implements MapperFunctionInterface {
    // implementation
}
```

### Step 8: Update Password Mappers

If you have custom password mappers, implement the new interface:

```php
use ByJG\Authenticate\Interfaces\PasswordMapperInterface;

class MyPasswordMapper implements PasswordMapperInterface {
    public function map(mixed $instance, ?string $fieldName = null): mixed {
        // Implement encryption logic
    }

    public function isPasswordEncrypted(string $password): bool {
        // Implement detection logic
    }
}
```

### Step 9: Update Database Schema (Optional but Recommended)

Add new timestamp columns and rename fields:

```sql
-- Rename 'admin' to 'role'
ALTER TABLE users CHANGE COLUMN admin role VARCHAR(50);

-- Rename 'created' to 'created_at' and add new timestamp fields
ALTER TABLE users
    CHANGE COLUMN created created_at DATETIME,
    ADD COLUMN updated_at DATETIME DEFAULT NULL,
    ADD COLUMN deleted_at DATETIME DEFAULT NULL;
```

Note: The library maintains backward compatibility with old column names through field mapping, but updating the schema is recommended for new installations.

### Step 10: Review and Update Tests

Update your tests to reflect the new architecture:
- Replace `UsersDBDataset` with `UsersService` + repositories
- Update assertions for role-based checks
- Update token handling to work with `UserToken` objects
- Use enums instead of string constants

## Additional Notes

- **SessionContext** is still supported but JWT-based authentication is now recommended for better security and scalability
- The library now requires PHP 8.3+ for improved type safety and modern PHP features
- All deprecated classes have been removed; there is no backward compatibility layer
- Comprehensive documentation is now available in the `/docs` folder
- Static analysis support improved with PSalm compatibility fixes

## Migration Support

For complex migrations or custom implementations, refer to:
- [Getting Started Guide](docs/getting-started.md)
- [User Management Documentation](docs/user-management.md)
- [Examples](docs/examples.md)

If you encounter issues during migration, please open an issue on the [GitHub repository](https://github.com/byjg/php-authuser/issues).
