---
sidebar_position: 4
title: Authentication
---

# Authentication

## Validating User Credentials

Use the `isValidUser()` method to validate a username/email and password combination:

```php
<?php
$user = $users->isValidUser('johndoe', 'SecurePass123');

if ($user !== null) {
    echo "Authentication successful!";
    echo "User ID: " . $user->getUserid();
} else {
    echo "Invalid credentials";
}
```

:::tip Login Field
The `isValidUser()` method uses the login field defined in your `UserDefinition`. This can be either the email or username field.
:::

## Password Hashing

By default, passwords are automatically hashed using SHA-1 when saved. The library uses the `PasswordSha1Mapper` for this purpose.

```php
<?php
// Password is automatically hashed when saved
$user->setPassword('plaintext password');
$users->save($user);

// The password is stored as SHA-1 hash in the database
```

:::warning SHA-1 Deprecation
SHA-1 is used for backward compatibility. For new projects, consider implementing a custom password hasher using bcrypt or Argon2. See [Mappers](mappers.md#example-bcrypt-password-mapper) for details.
:::

:::tip Enforce Password Strength
To enforce password policies (minimum length, complexity rules, etc.), see [Password Validation](password-validation.md).
:::

## JWT Token Authentication (Recommended)

For modern, stateless authentication, use JWT tokens. This is the **recommended approach** for new applications as it provides better security and scalability.

```php
<?php
use ByJG\JwtWrapper\JwtKeySecret;
use ByJG\JwtWrapper\JwtWrapper;

// Create JWT wrapper
$jwtKey = new JwtKeySecret('your-secret-key');
$jwtWrapper = new JwtWrapper($jwtKey);

// Create authentication token
$token = $users->createAuthToken(
    'johndoe',              // Login
    'SecurePass123',        // Password
    $jwtWrapper,
    3600,                   // Expires in 1 hour (seconds)
    [],                     // Additional user info to save
    ['role' => 'admin']     // Additional token data
);

if ($token !== null) {
    echo "Token: " . $token;
}
```

### Validating JWT Tokens

```php
<?php
$result = $users->isValidToken('johndoe', $jwtWrapper, $token);

if ($result !== null) {
    $user = $result['user'];
    $tokenData = $result['data'];

    echo "User: " . $user->getName();
    echo "Role: " . $tokenData['role'];
}
```

:::info Token Storage
When a JWT token is created, a hash of the token is stored in the user's properties as `TOKEN_HASH`. This ensures tokens can be invalidated if needed.
:::

:::tip Why JWT?
JWT tokens provide stateless authentication, better scalability, and easier integration with modern frontend frameworks and mobile applications. They're also more secure than traditional PHP sessions.
:::

## Session-Based Authentication (Legacy)

:::warning Deprecated
SessionContext relies on traditional PHP sessions and is less secure than JWT tokens. It's maintained for backward compatibility only. **For new projects, use JWT tokens instead.**
:::

### Basic Authentication Flow

```php
<?php
use ByJG\Authenticate\SessionContext;
use ByJG\Cache\Factory;

// 1. Validate user credentials
$user = $users->isValidUser('johndoe', 'SecurePass123');

if ($user !== null) {
    // 2. Create session context
    $sessionContext = new SessionContext(Factory::createSessionPool());

    // 3. Register login
    $sessionContext->registerLogin($user->getUserid());

    // 4. User is now authenticated
    echo "Welcome, " . $user->getName();
}
```

### Checking Authentication Status

```php
<?php
$sessionContext = new SessionContext(Factory::createSessionPool());

if ($sessionContext->isAuthenticated()) {
    $userId = $sessionContext->userInfo();
    $user = $users->get($userId);
    echo "Hello, " . $user->getName();
} else {
    echo "Please log in";
}
```

### Logging Out

```php
<?php
$sessionContext->registerLogout();
```

## Security Best Practices

1. **Always use HTTPS** in production to prevent credential theft
2. **Implement rate limiting** to prevent brute force attacks
3. **Use strong passwords** - see [Password Validation](password-validation.md)
4. **Set appropriate session timeouts**
5. **Validate and sanitize** all user inputs

## Next Steps

- [Session Context](session-context.md) - Manage user sessions
- [JWT Tokens](jwt-tokens.md) - Deep dive into JWT authentication
- [Password Validation](password-validation.md) - Enforce password policies
