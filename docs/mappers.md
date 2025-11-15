---
sidebar_position: 11
title: Mappers and Entity Processors
---

# Mappers and Entity Processors

Mappers and Entity Processors allow you to transform data as it's read from or written to the database.

## What Are Mappers?

Mappers implement the `MapperFunctionInterface` and transform individual field values during database operations.

- **Update Mappers**: Transform values **before** saving to database
- **Select Mappers**: Transform values **after** reading from database

## Built-in Mappers

### PasswordSha1Mapper

Automatically hashes passwords using SHA-1:

```php
<?php
use ByJG\Authenticate\MapperFunctions\PasswordSha1Mapper;

// This is applied by default to the password field
$userDefinition->defineMapperForUpdate(
    UserDefinition::FIELD_PASSWORD,
    PasswordSha1Mapper::class
);
```

### StandardMapper

Default mapper that passes values through unchanged:

```php
<?php
use ByJG\MicroOrm\MapperFunctions\StandardMapper;

$userDefinition->defineMapperForUpdate('name', StandardMapper::class);
```

### ReadOnlyMapper

Prevents field updates:

```php
<?php
use ByJG\MicroOrm\MapperFunctions\ReadOnlyMapper;

// Shortcut method
$userDefinition->markPropertyAsReadOnly(UserDefinition::FIELD_CREATED);

// Or explicitly
$userDefinition->defineMapperForUpdate(
    UserDefinition::FIELD_CREATED,
    ReadOnlyMapper::class
);
```

## Creating Custom Mappers

### Mapper Interface

```php
<?php
namespace ByJG\MicroOrm\Interface;

interface MapperFunctionInterface
{
    /**
     * @param mixed $value The value to transform
     * @param mixed $instance The model instance (optional)
     * @return mixed The transformed value
     */
    public function processedValue(mixed $value, mixed $instance): mixed;
}
```

### Example: Bcrypt Password Mapper

```php
<?php
use ByJG\MicroOrm\Interface\MapperFunctionInterface;

class BcryptPasswordMapper implements MapperFunctionInterface
{
    public function processedValue(mixed $value, mixed $instance): mixed
    {
        // Don't hash if already hashed (starts with $2y$)
        if (empty($value) || str_starts_with($value, '$2y$')) {
            return $value;
        }

        // Hash the password
        return password_hash($value, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}

// Use it
$userDefinition->defineMapperForUpdate(
    UserDefinition::FIELD_PASSWORD,
    BcryptPasswordMapper::class
);
```

### Example: Email Normalization Mapper

```php
<?php
use ByJG\MicroOrm\Interface\MapperFunctionInterface;

class EmailNormalizationMapper implements MapperFunctionInterface
{
    public function processedValue(mixed $value, mixed $instance): mixed
    {
        if (empty($value)) {
            return $value;
        }

        // Lowercase and trim
        $email = strtolower(trim($value));

        // Remove dots from Gmail addresses
        if (str_ends_with($email, '@gmail.com')) {
            $local = str_replace('.', '', explode('@', $email)[0]);
            $email = $local . '@gmail.com';
        }

        return $email;
    }
}

$userDefinition->defineMapperForUpdate(
    UserDefinition::FIELD_EMAIL,
    EmailNormalizationMapper::class
);
```

### Example: JSON Serialization Mappers

```php
<?php
use ByJG\MicroOrm\Interface\MapperFunctionInterface;

class JsonEncodeMapper implements MapperFunctionInterface
{
    public function processedValue(mixed $value, mixed $instance): mixed
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }
        return $value;
    }
}

class JsonDecodeMapper implements MapperFunctionInterface
{
    public function processedValue(mixed $value, mixed $instance): mixed
    {
        if (is_string($value)) {
            return json_decode($value, true);
        }
        return $value;
    }
}

// Store arrays as JSON in database
$userDefinition->defineMapperForUpdate('preferences', JsonEncodeMapper::class);
$userDefinition->defineMapperForSelect('preferences', JsonDecodeMapper::class);
```

### Example: Date Formatting Mapper

```php
<?php
use ByJG\MicroOrm\Interface\MapperFunctionInterface;

class DateFormatMapper implements MapperFunctionInterface
{
    public function processedValue(mixed $value, mixed $instance): mixed
    {
        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value)) {
            return date('Y-m-d H:i:s', $value);
        }
        return $value;
    }
}

class DateParseMapper implements MapperFunctionInterface
{
    public function processedValue(mixed $value, mixed $instance): mixed
    {
        if (empty($value)) {
            return null;
        }
        try {
            return new \DateTime($value);
        } catch (\Exception $e) {
            return $value;
        }
    }
}

$userDefinition->defineMapperForUpdate('created', DateFormatMapper::class);
$userDefinition->defineMapperForSelect('created', DateParseMapper::class);
```

## Entity Processors

Entity Processors transform the **entire entity** (UserModel) before insert or update operations.

### Entity Processor Interface

```php
<?php
namespace ByJG\MicroOrm\Interface;

interface EntityProcessorInterface
{
    /**
     * @param mixed $instance The model instance to process
     * @return void
     */
    public function process(mixed $instance): void;
}
```

### Built-in Entity Processors

#### PassThroughEntityProcessor

Default processor that does nothing:

```php
<?php
use ByJG\Authenticate\EntityProcessors\PassThroughEntityProcessor;

$userDefinition->setBeforeInsert(new PassThroughEntityProcessor());
```

### Custom Entity Processors

#### Example: Auto-Set Created Timestamp

```php
<?php
use ByJG\MicroOrm\Interface\EntityProcessorInterface;
use ByJG\Authenticate\Model\UserModel;

class CreatedTimestampProcessor implements EntityProcessorInterface
{
    public function process(mixed $instance): void
    {
        if ($instance instanceof UserModel) {
            if (empty($instance->getCreated())) {
                $instance->setCreated(date('Y-m-d H:i:s'));
            }
        }
    }
}

$userDefinition->setBeforeInsert(new CreatedTimestampProcessor());
```

#### Example: Username Validation

```php
<?php
use ByJG\MicroOrm\Interface\EntityProcessorInterface;
use ByJG\Authenticate\Model\UserModel;

class UsernameValidationProcessor implements EntityProcessorInterface
{
    public function process(mixed $instance): void
    {
        if ($instance instanceof UserModel) {
            $username = $instance->getUsername();

            if (strlen($username) < 3) {
                throw new \InvalidArgumentException('Username must be at least 3 characters');
            }

            if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                throw new \InvalidArgumentException('Username can only contain letters, numbers, and underscores');
            }
        }
    }
}

$userDefinition->setBeforeInsert(new UsernameValidationProcessor());
$userDefinition->setBeforeUpdate(new UsernameValidationProcessor());
```

#### Example: Audit Trail

```php
<?php
use ByJG\MicroOrm\Interface\EntityProcessorInterface;

class AuditProcessor implements EntityProcessorInterface
{
    private $userId;

    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    public function process(mixed $instance): void
    {
        if ($instance instanceof UserModel) {
            $instance->set('modified_by', $this->userId);
            $instance->set('modified_at', date('Y-m-d H:i:s'));
        }
    }
}

$userDefinition->setBeforeUpdate(new AuditProcessor($currentUserId));
```

## Using Closures (Legacy)

For backward compatibility, you can use closures instead of dedicated mapper classes:

```php
<?php
use ByJG\Authenticate\MapperFunctions\ClosureMapper;

// Update mapper
$userDefinition->defineMapperForUpdate(
    UserDefinition::FIELD_EMAIL,
    new ClosureMapper(function ($value, $instance) {
        return strtolower(trim($value));
    })
);

// Select mapper
$userDefinition->defineMapperForSelect(
    UserDefinition::FIELD_CREATED,
    new ClosureMapper(function ($value, $instance) {
        return date('Y', strtotime($value));
    })
);
```

:::warning Deprecated Methods
The following methods are deprecated but still work:
- `defineClosureForUpdate()` - Use `defineMapperForUpdate()` with `ClosureMapper`
- `defineClosureForSelect()` - Use `defineMapperForSelect()` with `ClosureMapper`
- `getClosureForUpdate()` - Use `getMapperForUpdate()`
- `getClosureForSelect()` - Use `getMapperForSelect()`
:::

## Complete Example

```php
<?php
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\Interface\MapperFunctionInterface;
use ByJG\MicroOrm\Interface\EntityProcessorInterface;

// Custom Mappers
class TrimMapper implements MapperFunctionInterface
{
    public function processedValue(mixed $value, mixed $instance): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }
}

class LowercaseMapper implements MapperFunctionInterface
{
    public function processedValue(mixed $value, mixed $instance): mixed
    {
        return is_string($value) ? strtolower($value) : $value;
    }
}

// Custom Entity Processor
class DefaultsProcessor implements EntityProcessorInterface
{
    public function process(mixed $instance): void
    {
        if ($instance instanceof UserModel) {
            if (empty($instance->getCreated())) {
                $instance->setCreated(date('Y-m-d H:i:s'));
            }
            if (empty($instance->getRole())) {
                $instance->setRole('user');
            }
        }
    }
}

// Configure User Definition
$userDefinition = new UserDefinition();

// Apply mappers
$userDefinition->defineMapperForUpdate('name', TrimMapper::class);
$userDefinition->defineMapperForUpdate('email', LowercaseMapper::class);
$userDefinition->defineMapperForUpdate('username', LowercaseMapper::class);

// Apply entity processors
$userDefinition->setBeforeInsert(new DefaultsProcessor());

// Initialize
$users = new UsersDBDataset($dbDriver, $userDefinition);
```

## Best Practices

1. **Keep mappers simple** - Each mapper should do one thing
2. **Chain mappers** - Use composition for complex transformations
3. **Handle null values** - Always check for null/empty values
4. **Be idempotent** - Applying mapper multiple times should be safe
5. **Use entity processors for validation** - Validate complete entities
6. **Document side effects** - Make it clear what each mapper does

## Next Steps

- [Custom Fields](custom-fields.md) - Extending UserModel
- [Password Validation](password-validation.md) - Password policies
- [Database Storage](database-storage.md) - Schema configuration
