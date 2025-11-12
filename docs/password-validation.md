---
sidebar_position: 8
title: Password Validation
---

# Password Validation

The `PasswordDefinition` class provides comprehensive password strength validation and generation capabilities.

## Basic Usage

### Creating a Password Definition

```php
<?php
use ByJG\Authenticate\Definition\PasswordDefinition;
use ByJG\Authenticate\Model\UserModel;

// Create password definition with default rules
$passwordDefinition = new PasswordDefinition();

// Attach to user model
$userModel = new UserModel();
$userModel->withPasswordDefinition($passwordDefinition);

// Now password is validated when set
$userModel->setPassword('WeakPwd');  // Throws InvalidArgumentException
```

## Password Rules

### Default Rules

The default password policy requires:

| Rule                | Default Value | Description                              |
|---------------------|---------------|------------------------------------------|
| `minimum_chars`     | 8             | Minimum password length                  |
| `require_uppercase` | 0             | Number of uppercase letters required     |
| `require_lowercase` | 1             | Number of lowercase letters required     |
| `require_symbols`   | 0             | Number of symbols required               |
| `require_numbers`   | 1             | Number of digits required                |
| `allow_whitespace`  | 0             | Allow whitespace characters (0 = no)     |
| `allow_sequential`  | 0             | Allow sequential characters (0 = no)     |
| `allow_repeated`    | 0             | Allow repeated patterns (0 = no)         |

### Custom Rules

```php
<?php
use ByJG\Authenticate\Definition\PasswordDefinition;

$passwordDefinition = new PasswordDefinition([
    PasswordDefinition::MINIMUM_CHARS => 12,
    PasswordDefinition::REQUIRE_UPPERCASE => 2,
    PasswordDefinition::REQUIRE_LOWERCASE => 2,
    PasswordDefinition::REQUIRE_SYMBOLS => 1,
    PasswordDefinition::REQUIRE_NUMBERS => 2,
    PasswordDefinition::ALLOW_WHITESPACE => 0,
    PasswordDefinition::ALLOW_SEQUENTIAL => 0,
    PasswordDefinition::ALLOW_REPEATED => 0
]);
```

### Setting Individual Rules

```php
<?php
$passwordDefinition = new PasswordDefinition();
$passwordDefinition->setRule(PasswordDefinition::MINIMUM_CHARS, 10);
$passwordDefinition->setRule(PasswordDefinition::REQUIRE_UPPERCASE, 1);
$passwordDefinition->setRule(PasswordDefinition::REQUIRE_SYMBOLS, 1);
```

## Validating Passwords

### Validation Result Codes

The `matchPassword()` method returns a bitwise result:

```php
<?php
$result = $passwordDefinition->matchPassword('weak');

if ($result === PasswordDefinition::SUCCESS) {
    echo "Password is valid";
} else {
    // Check specific failures
    if ($result & PasswordDefinition::FAIL_MINIMUM_CHARS) {
        echo "Password is too short\n";
    }
    if ($result & PasswordDefinition::FAIL_UPPERCASE) {
        echo "Missing uppercase letters\n";
    }
    if ($result & PasswordDefinition::FAIL_LOWERCASE) {
        echo "Missing lowercase letters\n";
    }
    if ($result & PasswordDefinition::FAIL_NUMBERS) {
        echo "Missing numbers\n";
    }
    if ($result & PasswordDefinition::FAIL_SYMBOLS) {
        echo "Missing symbols\n";
    }
    if ($result & PasswordDefinition::FAIL_WHITESPACE) {
        echo "Whitespace not allowed\n";
    }
    if ($result & PasswordDefinition::FAIL_SEQUENTIAL) {
        echo "Sequential characters detected\n";
    }
    if ($result & PasswordDefinition::FAIL_REPEATED) {
        echo "Repeated patterns detected\n";
    }
}
```

### Available Failure Codes

| Constant                  | Value | Description                      |
|---------------------------|-------|----------------------------------|
| `SUCCESS`                 | 0     | Password is valid                |
| `FAIL_MINIMUM_CHARS`      | 1     | Password too short               |
| `FAIL_UPPERCASE`          | 2     | Missing uppercase letters        |
| `FAIL_LOWERCASE`          | 4     | Missing lowercase letters        |
| `FAIL_SYMBOLS`            | 8     | Missing symbols                  |
| `FAIL_NUMBERS`            | 16    | Missing numbers                  |
| `FAIL_WHITESPACE`         | 32    | Whitespace not allowed           |
| `FAIL_SEQUENTIAL`         | 64    | Sequential characters detected   |
| `FAIL_REPEATED`           | 128   | Repeated patterns detected       |

## Password Generation

### Generate a Random Password

```php
<?php
$password = $passwordDefinition->generatePassword();
echo $password;  // e.g., "aB3dE7fG9"
```

### Generate Longer Passwords

```php
<?php
// Generate a password 5 characters longer than the minimum
$password = $passwordDefinition->generatePassword(5);
```

The generated password will:
- Meet all defined rules
- Be cryptographically random
- Avoid sequential and repeated patterns

## User Registration with Password Validation

### Complete Example

```php
<?php
use ByJG\Authenticate\Definition\PasswordDefinition;
use ByJG\Authenticate\Model\UserModel;

// Define password rules
$passwordDefinition = new PasswordDefinition([
    PasswordDefinition::MINIMUM_CHARS => 10,
    PasswordDefinition::REQUIRE_UPPERCASE => 1,
    PasswordDefinition::REQUIRE_LOWERCASE => 1,
    PasswordDefinition::REQUIRE_SYMBOLS => 1,
    PasswordDefinition::REQUIRE_NUMBERS => 1,
]);

// Create user with password validation
try {
    $user = new UserModel();
    $user->withPasswordDefinition($passwordDefinition);

    $user->setName('John Doe');
    $user->setEmail('john@example.com');
    $user->setUsername('johndoe');
    $user->setPassword($_POST['password']);  // Validated automatically

    $users->save($user);
    echo "User created successfully";

} catch (InvalidArgumentException $e) {
    echo "Password validation failed: " . $e->getMessage();
}
```

## User-Friendly Error Messages

```php
<?php
function getPasswordErrors(int $result): array
{
    $errors = [];

    if ($result & PasswordDefinition::FAIL_MINIMUM_CHARS) {
        $errors[] = "Password must be at least 8 characters long";
    }
    if ($result & PasswordDefinition::FAIL_UPPERCASE) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    if ($result & PasswordDefinition::FAIL_LOWERCASE) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    if ($result & PasswordDefinition::FAIL_NUMBERS) {
        $errors[] = "Password must contain at least one number";
    }
    if ($result & PasswordDefinition::FAIL_SYMBOLS) {
        $errors[] = "Password must contain at least one symbol (!@#$%^&*()...)";
    }
    if ($result & PasswordDefinition::FAIL_WHITESPACE) {
        $errors[] = "Password cannot contain whitespace";
    }
    if ($result & PasswordDefinition::FAIL_SEQUENTIAL) {
        $errors[] = "Password cannot contain sequential characters (abc, 123, etc.)";
    }
    if ($result & PasswordDefinition::FAIL_REPEATED) {
        $errors[] = "Password cannot contain repeated patterns";
    }

    return $errors;
}

// Usage
$result = $passwordDefinition->matchPassword($_POST['password']);
if ($result !== PasswordDefinition::SUCCESS) {
    $errors = getPasswordErrors($result);
    foreach ($errors as $error) {
        echo "- " . $error . "\n";
    }
}
```

## Sequential and Repeated Patterns

### Sequential Characters

Sequential patterns that are detected include:
- **Alphabetic**: abc, bcd, cde, xyz, etc. (case-insensitive)
- **Numeric**: 012, 123, 234, 789, 890, etc.
- **Reverse**: 987, 876, 765, 321, etc.

### Repeated Patterns

Repeated patterns include:
- **Repeated characters**: aaa, 111, etc.
- **Repeated sequences**: ababab, 123123, etc.

## Password Change Flow

```php
<?php
// Password change with validation
try {
    $user = $users->get($userId);
    $user->withPasswordDefinition($passwordDefinition);

    // Verify old password
    $existingUser = $users->isValidUser($user->getUsername(), $_POST['old_password']);
    if ($existingUser === null) {
        throw new Exception("Current password is incorrect");
    }

    // Set new password (validated automatically)
    $user->setPassword($_POST['new_password']);
    $users->save($user);

    echo "Password changed successfully";

} catch (InvalidArgumentException $e) {
    echo "New password validation failed: " . $e->getMessage();
}
```

## Best Practices

1. **Balance security and usability** - Don't make rules too restrictive
2. **Educate users** - Provide clear error messages
3. **Use password generation** - Offer to generate strong passwords
4. **Consider passphrases** - Allow longer passwords with spaces if appropriate
5. **Combine with rate limiting** - Prevent brute force attacks

## Next Steps

- [Authentication](authentication.md) - Validating credentials
- [User Management](user-management.md) - Managing users
- [Mappers](mappers.md) - Custom password hashing
