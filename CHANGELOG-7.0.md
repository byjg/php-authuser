# Changelog - Version 7.0

> **Status: in development.** This document tracks changes landing on the `7.0` branch.
> Nothing here is released yet, and the contents may still change.

## Overview

Version 7.0 upgrades the underlying persistence stack to `byjg/micro-orm` 7.0 and
`byjg/anydataset-db` 7.0. `UsersService`, `UsersRepository`, `UserPropertiesRepository`, models
and mapper interfaces keep the same signatures as 6.x; the only API change is the renaming of the
`UserField` enum cases to PascalCase (see below).

Since 6.0 the library was already wired exclusively through `DatabaseExecutor` — the API that
became mandatory in anydataset-db 7.0 — so apart from that rename, the library code changed only
to fix the user id conversion in `UserPropertiesRepository` (see Bug Fixes).

## Breaking Changes

### Passwords are hashed with bcrypt

`UserModel` hashes passwords with the new `PasswordBcryptMapper` instead of `PasswordSha1Mapper`
(unsalted SHA-1).

- **Widen the password column** to `VARCHAR(255)`: a bcrypt hash has 60 characters, and a new user
  or a changed password does not fit in the `CHAR(40)` of a SHA-1 column.
- **Existing users keep logging in.** The bcrypt mapper verifies a 40-character SHA-1 hash too, and
  the new `UsersService::rehashPasswordIfNeeded()` replaces it with a bcrypt hash at that user's next
  login: `createAuthToken()` calls it; a login of your own calls it after `isValidUser()`, which only
  checks the password. It reads the new hash back and keeps the old one when the column is too short
  (rejected or truncated), so nobody is locked out before the column is widened.
- **To keep SHA-1**, select `PasswordSha1Mapper` on the password field of your user model:
  `#[FieldAttribute(updateFunction: PasswordSha1Mapper::class)]`.
- `UsersServiceInterface` has the new `rehashPasswordIfNeeded()`.
- **Custom password mappers** implement two new methods of `PasswordMapperInterface`:
  `verifyPassword(string $password, string $hash): bool` and `needsRehash(string $hash): bool`.
  `isValidUser()` uses them instead of comparing the hash of the password with `===`; a SHA-1 or
  MD5 mapper verifies with `hash_equals()` and returns `false` from `needsRehash()`.
- `UserModel::setPassword()` asks the password mapper whether a value is already a hash (it skipped
  the password definition for any 40-character value).

### Dependencies

| Package | 6.x | 7.0 |
|---|---|---|
| `byjg/micro-orm` | `^6.0` | `^7.0` |
| `byjg/anydataset-db` (transitive) | `^6.0` | `^7.0` |

### Removed driver query methods (via anydataset-db 7.0)

The query methods deprecated on the drivers since anydataset-db 6.0 no longer exist
(`$dbDriver->execute()`, `getIterator()`, `getScalar()`, `executeAndGetId()`, `getAllFields()`).
If your application still calls them on the driver instance it passes to AuthUser, migrate to:

```php
<?php
use ByJG\AnyDataset\Db\DatabaseExecutor;

$executor = DatabaseExecutor::using($dbDriver);
$executor->execute($sql, $params);
```

The recommended wiring is unchanged and keeps working as-is:

```php
<?php
$dbDriver = Factory::getDbInstance('mysql://user:pass@host/db');
$executor = DatabaseExecutor::using($dbDriver);

$usersRepository = new UsersRepository($executor, UserModel::class);
$propertiesRepository = new UserPropertiesRepository($executor, UserPropertiesModel::class);
$service = new UsersService($usersRepository, $propertiesRepository, LoginField::Username);
```

### Observer system (via micro-orm 7.0)

micro-orm 7.0 removed the global `ORMSubject` singleton and rewired observers on top of the
`DatabaseExecutor` observer mechanism. AuthUser does not use observers internally, but if your
application registered observers on the AuthUser repositories, review the
[micro-orm 7.0 changelog](https://github.com/byjg/micro-orm/blob/master/CHANGELOG-7.0.md):
observers are now scoped per executor instead of global.

### Development dependencies

- PHPUnit: `^10.5|^11.5` → `^12.5`
- Psalm: `vimeo/psalm ^5.9|^6.13` → `psalm/phar ^6.16`

### Enum cases renamed to PascalCase

Enum cases follow the ByJG standard and are written in PascalCase. The backed values did not
change, so `->value`, `from()`, `tryFrom()` and stored data are not affected; only code that
names a case (or reads `->name`) has to be updated.

| Enum | Before (6.x) | After (7.0) |
|---|---|---|
| `UserField` | `Created_at`, `uUpdated_at`, `Deleted_at` | `CreatedAt`, `UpdatedAt`, `DeletedAt` |

Not a plain change of casing: `UserField::uUpdated_at` is now `UserField::UpdatedAt`.

## Bug Fixes

- A model created with `new UserModel(...)` was saved without checking its password against the
  service's `PasswordDefinition` (only users created or loaded by `UsersService` had it). The service
  now sets the new `PasswordDefinitionValidator` (a MicroORM `EntityValidatorInterface`) on the users
  repository, so every save, also through `UsersRepository` or MicroORM's `Repository`, checks a plain
  password and throws `InvalidArgumentException` outside the rules; a stored hash is not checked.
  A validator declared on the user model's `TableAttribute` still runs.

- `UserPropertiesRepository` converted the user id for its queries without the database at
  hand (`getUpdateFunctionValue($userid, null)`), so a UUID user id was always written as
  `X'…'`. With byjg/micro-orm 7.0, which writes a UUID in the syntax of each database, the
  repository now passes its executor, and the queries work on PostgreSQL (a native `uuid`)
  and SQL Server too.
- `SessionContext::registerLogin($userId, $data)` lost `$data`: it stored the cache item in
  place of the array, so `getSessionData()` never found the values passed at login.

## Migration from 6.x

1. Update the composer constraint:

```json
{
  "require": {
    "byjg/authuser": "^7.0"
  }
}
```

2. If your application already wires AuthUser through `DatabaseExecutor::using()` (the documented
   pattern since 6.0), no code changes are required.
3. If you still call the removed query methods directly on the `DbDriverInterface` instance,
   migrate those calls to `DatabaseExecutor` (see table in the
   [anydataset-db 7.0 changelog](https://github.com/byjg/anydataset-db/blob/master/CHANGELOG-7.0.md)).

## Requirements

- PHP 8.3, 8.4, 8.5 and 8.6 are now supported: `"php": ">=8.3 <8.7"`.
  The previous `<8.6` upper bound excluded PHP 8.6, since `<8.6` is exclusive.

### ByJG dependencies

- `byjg/cache-engine` is now `^7.0`.
- `byjg/jwt-wrapper` is now `^7.0`.
- `byjg/micro-orm` is now `^7.0`.

While 7.0 is unreleased these resolve to `7.0.x-dev` from each component's
`7.0` branch, via `minimum-stability: dev` with `prefer-stable: true`.

## Toolchain

- PHPUnit updated to `^12.5`.
- Psalm is installed as `psalm/phar` instead of `vimeo/psalm`.

  `vimeo/psalm` lists the PHP versions it supports and no published release includes
  8.6, so as a dev dependency it made `composer install` fail on the 8.6 build job
  before any test ran. `psalm/phar` requires only `php ^8.2` and bundles its own
  dependencies, so it installs on every PHP version in the matrix and cannot conflict
  with the project's. Psalm itself still refuses to *run* on 8.6, which is why the
  Psalm job uses 8.5. `composer psalm` runs it.

- PHPUnit 13 is deliberately **not** used. It requires PHP `>=8.4.1`, breaking the
  8.3 floor.

## Continuous Integration

- The build matrix now includes PHP 8.6.
- The Psalm job runs on PHP 8.5.

## Housekeeping

- `phpunit.xml.dist` renamed to `phpunit.xml`.
- The root `example.php` script was removed; see the [Examples](docs/examples.md) page.
