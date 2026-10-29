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
The `isValidUser()` method uses the login field configured in your `UsersService` constructor. Pass `LoginField::Email`, `LoginField::Username` or `LoginField::EmailOrUsername` when creating the service.
:::
`LoginField` lives in the `ByJG\Authenticate\Enum` namespace.

## Password Hashing

By default, passwords are hashed with bcrypt when saved, by the `PasswordBcryptMapper` of
`UserModel`. The password column needs 60 characters; `VARCHAR(255)` leaves room for other algorithms.

```php
<?php
// Password is automatically hashed when saved
$user->setPassword('plaintext password');
$users->save($user);

// The password is stored as a bcrypt hash ($2y$...) in the database
```

The bcrypt mapper also reads the SHA-1 hashes of `PasswordSha1Mapper`, the default before 7.0: a
stored 40-character hash still logs in. At login, `createAuthToken()` replaces it with a bcrypt hash,
so users move to bcrypt as they log in, without a password reset. A bcrypt hash with a lower cost than
PHP's current one is replaced the same way. `isValidUser()` only checks the password and never writes.

A login of your own (without `createAuthToken()`) upgrades the hash with `rehashPasswordIfNeeded()`:

```php
<?php
$user = $users->isValidUser($login, $password);
if ($user !== null) {
    $users->rehashPasswordIfNeeded($user, $password);
}
```

`rehashPasswordIfNeeded()` reads the new hash back. If the password column is too short for it (the
`CHAR(40)` of a SHA-1 schema), the new hash is rejected or truncated by the database; the method then
keeps the old hash and returns `false`, so the user can still log in. Widen the column to upgrade.

To keep SHA-1, select its mapper in your user model:

```php
<?php
use ByJG\Authenticate\MapperFunctions\PasswordSha1Mapper;
use ByJG\Authenticate\Model\UserModel;
use ByJG\MicroOrm\Attributes\FieldAttribute;

class User extends UserModel
{
    #[FieldAttribute(updateFunction: PasswordSha1Mapper::class)]
    protected ?string $password = null;
}
```

:::warning SHA-1
`PasswordSha1Mapper` stores an unsalted SHA-1 hash, which is fast to brute-force. Keep it only for a
system that cannot store longer hashes.
:::

:::tip Enforce Password Strength
To enforce password policies (minimum length, complexity rules, etc.), see [Password Validation](password-validation.md).
:::

## JWT Token Authentication (Recommended)

For modern, stateless authentication, use JWT tokens. This is the **recommended approach** for new applications as it provides better security and scalability.

```php
<?php
use ByJG\JwtWrapper\JwtHashHmacSecret;
use ByJG\JwtWrapper\JwtWrapper;

// Create JWT wrapper
$jwtSecret = getenv('JWT_SECRET') ?: JwtWrapper::generateSecret(64);  // base64 encoded secret
$jwtWrapper = new JwtWrapper('your-server.com', new JwtHashHmacSecret($jwtSecret));

// Create authentication token
$userToken = $users->createAuthToken(
    'johndoe',              // Login
    'SecurePass123',        // Password
    $jwtWrapper,
    3600,                   // Expires in 1 hour (seconds)
    [],                     // Additional user info to save
    ['role' => 'admin']     // Additional token data
);

if ($userToken !== null) {
    echo "Token: " . $userToken->token;
}
```

Need to include standard user columns (name, email, etc.) automatically? Pass the optional seventh argument with `UserField` enum values or strings. See [JWT Tokens](jwt-tokens.md#copy-user-fields-automatically) for details.

:::tip UserToken Return Type
Both `createAuthToken()` and `createInsecureAuthToken()` return a `UserToken` object with three properties:
- `token` - The JWT token string
- `user` - The UserModel instance
- `data` - Array of token payload data

This provides immediate access to both the token and user information without additional database queries.
:::

### Creating Tokens Without Password Validation

Use `createInsecureAuthToken()` when you need to create tokens without password validation:

```php
<?php
// After OAuth authentication or token refresh
$user = $users->getByEmail($email);

$userToken = $users->createInsecureAuthToken(
    $user,              // Can pass UserModel or login string
    $jwtWrapper,
    3600
);

echo "Token: " . $userToken->token;
```

See [JWT Tokens](jwt-tokens.md#creating-tokens-without-password-validation) for complete details and use cases.

### Validating JWT Tokens

```php
<?php
$userToken = $users->isValidToken('johndoe', $jwtWrapper, $token);

if ($userToken !== null) {
    $user = $userToken->user;
    $tokenData = $userToken->data;

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

## Session-Based Authentication

The `SessionContext` class persists the authenticated user ID inside a PSR-6 cache pool. Use this when you need server-managed sessions (for example, classic PHP applications).

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
    $user = $users->getById($userId);
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
