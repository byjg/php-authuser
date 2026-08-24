# Changelog - Version 7.0

## Overview

Version 7.0 upgrades the underlying persistence stack to `byjg/micro-orm` 7.0 and
`byjg/anydataset-db` 7.0. The AuthUser public API is **unchanged**: `UsersService`,
`UsersRepository`, `UserPropertiesRepository`, models, enums and mapper interfaces keep the exact
same signatures as 6.x.

Since 6.0 the library was already wired exclusively through `DatabaseExecutor` — the API that
became mandatory in anydataset-db 7.0 — so no library code had to change beyond the dependency
constraints.

## Breaking Changes

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
- Psalm: `^5.9|^6.13` → `^6.13`

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
- Psalm moved out of `require-dev` into its own manifest, `tools/psalm/composer.json`.

  Psalm enumerates the PHP versions it supports and no published release lists
  8.6. As a dev dependency it made `composer install` fail on the 8.6 build job
  before any test ran. It now installs separately, only for the Psalm job.

  `composer psalm` still works — it bootstraps the tool and runs it.

- PHPUnit 13 is deliberately **not** used. It requires PHP `>=8.4.1`, breaking the
  8.3 floor, and needs `sebastian/diff ^9.0`, which stable Psalm 6.16.1 rejects —
  a combination that silently resolves Psalm to an unreleased `6.x-dev` branch.

## Continuous Integration

- The build matrix now includes PHP 8.6.
- The Psalm job runs on PHP 8.5 and installs Psalm from `tools/psalm`.

## Housekeeping

- `phpunit.xml.dist` renamed to `phpunit.xml`.
