---
sidebar_position: 11
title: Mappers and Entity Processors
---

# Mappers and Entity Processors

Auth User PHP relies on [byjg/micro-orm](https://github.com/byjg/micro-orm) to map models to database rows. Every property in `UserModel` and `UserPropertiesModel` can define how its value is transformed when it is inserted, updated, or selected. This page shows how to take advantage of those hooks.

## Controlling Fields with Attributes

`FieldAttribute` accepts optional mapper functions for each lifecycle event:

- `updateFunction` – runs before an UPDATE or when calling `save()` (most common).
- `insertFunction` – runs only when the record is first inserted.
- `selectFunction` – runs when values are loaded from the database.

```php
<?php
use ByJG\Authenticate\MapperFunctions\ClosureMapper;
use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\MicroOrm\MapperFunctions\ReadOnlyMapper;
use ByJG\MicroOrm\MapperFunctions\StandardMapper;

#[TableAttribute(tableName: 'users')]
class CustomUserModel extends UserModel
{
    // Store the column as full_name and prevent updates
    #[FieldAttribute(fieldName: 'full_name', updateFunction: ReadOnlyMapper::class)]
    protected ?string $name = null;

    // Normalize the email before persisting
    #[FieldAttribute(
        updateFunction: new ClosureMapper(fn ($value) => strtolower(trim((string) $value))),
        selectFunction: StandardMapper::class
    )]
    protected ?string $email = null;
}
```

### Built-in Mapper Helpers

This package ships with a few mapper utilities that complement the ones provided by Micro ORM:

- **PasswordSha1Mapper** – hashes passwords using SHA-1 (the default on `UserModel`). Replace it with your own mapper to change the hashing algorithm.
- **UserIdGeneratorMapper** – derives a user ID from the username when the primary key is empty.
    ```php
    <?php
    use ByJG\Authenticate\MapperFunctions\UserIdGeneratorMapper;
    use ByJG\Authenticate\Model\UserModel;
    use ByJG\MicroOrm\Attributes\FieldAttribute;

    class UsernameAsIdModel extends UserModel
    {
        #[FieldAttribute(primaryKey: true, updateFunction: UserIdGeneratorMapper::class)]
        protected string|int|null $userid = null;
    }
    ```
- **ClosureMapper** – wraps anonymous functions so they implement `MapperFunctionInterface`. This is handy when you need a small transformation in place.
    ```php
    <?php
    use ByJG\Authenticate\MapperFunctions\ClosureMapper;

    #[FieldAttribute(updateFunction: new ClosureMapper(function ($value) {
        return is_string($value) ? strtoupper($value) : $value;
    }))]
    protected ?string $role = null;
    ```

### Using Micro ORM Mappers

You can mix the helper classes above with the mappers that ship with `byjg/micro-orm`, such as:

- `StandardMapper` – pass-through behavior (already the default).
- `ReadOnlyMapper` – prevents updates so the database remains the source of truth.
- `NowUtcMapper` – sets timestamps automatically when inserting or updating.

```php
<?php
use ByJG\MicroOrm\MapperFunctions\NowUtcMapper;
use ByJG\MicroOrm\MapperFunctions\ReadOnlyMapper;

#[FieldAttribute(fieldName: 'created_at', updateFunction: ReadOnlyMapper::class, insertFunction: NowUtcMapper::class)]
protected ?string $createdAt = null;

#[FieldAttribute(fieldName: 'updated_at', updateFunction: NowUtcMapper::class)]
protected ?string $updatedAt = null;
```

## Creating Custom Mappers

Any mapper only needs to implement `MapperFunctionInterface`:

```php
<?php
namespace App\Mapper;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\MicroOrm\Interface\MapperFunctionInterface;

class BcryptPasswordMapper implements MapperFunctionInterface
{
    public function processedValue(mixed $value, mixed $instance, ?DatabaseExecutor $executor = null): mixed
    {
        if (empty($value) || str_starts_with((string) $value, '$2y$')) {
            return $value;
        }

        return password_hash((string) $value, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}
```

Attach it to the password field:

```php
<?php
#[FieldAttribute(updateFunction: BcryptPasswordMapper::class)]
protected ?string $password = null;
```

### JSON Columns Example

```php
<?php
use ByJG\MicroOrm\Interface\MapperFunctionInterface;
use ByJG\AnyDataset\Db\DatabaseExecutor;

class JsonEncodeMapper implements MapperFunctionInterface
{
    public function processedValue(mixed $value, mixed $instance, ?DatabaseExecutor $executor = null): mixed
    {
        return (is_array($value) || is_object($value)) ? json_encode($value) : $value;
    }
}

class JsonDecodeMapper implements MapperFunctionInterface
{
    public function processedValue(mixed $value, mixed $instance, ?DatabaseExecutor $executor = null): mixed
    {
        return is_string($value) ? json_decode($value, true) : $value;
    }
}

#[FieldAttribute(
    fieldName: 'preferences',
    updateFunction: JsonEncodeMapper::class,
    selectFunction: JsonDecodeMapper::class
)]
protected array $preferences = [];
```

## Entity Processors

Where mappers transform *fields*, entity processors transform the entire model before it is inserted or updated. They implement `EntityProcessorInterface` and can be attached through `TableAttribute` or by calling `setBeforeInsert()` / `setBeforeUpdate()` on the repository.

```php
<?php
namespace App\Processor;

use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\Interface\EntityProcessorInterface;

class UsernameValidationProcessor implements EntityProcessorInterface
{
    public function process(mixed $instance): void
    {
        if (!$instance instanceof UserModel) {
            return;
        }

        $username = (string) $instance->getUsername();
        if (strlen($username) < 3) {
            throw new \InvalidArgumentException('Username must have at least 3 characters.');
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
            throw new \InvalidArgumentException('Username can only contain letters, numbers, and underscores.');
        }
    }
}

class AuditProcessor implements EntityProcessorInterface
{
    public function __construct(private readonly int $actorId)
    {
    }

    public function process(mixed $instance): void
    {
        if ($instance instanceof UserModel) {
            $instance->set('modified_by', (string) $this->actorId);
            $instance->set('modified_at', date('Y-m-d H:i:s'));
        }
    }
}
```

Attach them using the table attribute:

```php
<?php
use App\Processor\AuditProcessor;
use App\Processor\UsernameValidationProcessor;
use ByJG\MicroOrm\Attributes\TableAttribute;

#[TableAttribute(
    tableName: 'users',
    beforeInsert: UsernameValidationProcessor::class,
    beforeUpdate: AuditProcessor::class
)]
class ProcessedUserModel extends CustomUserModel
{
}
```

If you need runtime dependencies (like the current actor ID), instantiate the processor and configure it on the repository:

```php
<?php
$repository = $users->getUsersRepository()->getRepository();
$repository->setBeforeUpdate(new AuditProcessor($currentUserId));
```

## Complete Example

```php
<?php
use App\Mapper\BcryptPasswordMapper;
use App\Processor\UsernameValidationProcessor;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory as DbFactory;
use ByJG\Authenticate\Enum\LoginField;
use ByJG\Authenticate\MapperFunctions\ClosureMapper;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Repository\UserPropertiesRepository;
use ByJG\Authenticate\Repository\UsersRepository;
use ByJG\Authenticate\Service\UsersService;
use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\MicroOrm\MapperFunctions\NowUtcMapper;
use ByJG\MicroOrm\MapperFunctions\ReadOnlyMapper;

#[TableAttribute(tableName: 'users', beforeInsert: UsernameValidationProcessor::class)]
class CustomUserModel extends UserModel
{
    #[FieldAttribute(updateFunction: BcryptPasswordMapper::class)]
    protected ?string $password = null;

    #[FieldAttribute(fieldName: 'created_at', updateFunction: ReadOnlyMapper::class, insertFunction: NowUtcMapper::class)]
    protected ?string $createdAt = null;

    #[FieldAttribute(fieldName: 'updated_at', updateFunction: NowUtcMapper::class)]
    protected ?string $updatedAt = null;

    #[FieldAttribute(updateFunction: new ClosureMapper(fn ($value) => strtolower(trim((string) $value))))]
    protected ?string $email = null;
}

$dbDriver = DbFactory::getDbInstance('mysql://user:pass@localhost/app');
$db = DatabaseExecutor::using($dbDriver);
$usersRepo = new UsersRepository($db, CustomUserModel::class);
$propsRepo = new UserPropertiesRepository($db, \ByJG\Authenticate\Model\UserPropertiesModel::class);
$users = new UsersService($usersRepo, $propsRepo, LoginField::Username);
```

With these tools you can precisely control how data flows between your database schema and the authentication service while keeping the domain models clean.
