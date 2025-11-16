---
sidebar_position: 9
title: JWT Tokens
---

# JWT Tokens

The library provides built-in support for JWT (JSON Web Token) authentication through integration with [byjg/jwt-wrapper](https://github.com/byjg/jwt-wrapper).

## What is JWT?

JWT (JSON Web Tokens) is a compact, URL-safe means of representing claims to be transferred between two parties. JWTs are commonly used for:

- **Stateless authentication** - No server-side session storage needed
- **API authentication** - Perfect for REST APIs and microservices
- **Single Sign-On (SSO)** - Share authentication across domains
- **Mobile apps** - Efficient token-based authentication

## Setup

### Creating a JWT Wrapper

```php
<?php
use ByJG\JwtWrapper\JwtHashHmacSecret;
use ByJG\JwtWrapper\JwtWrapper;

$secret = getenv('JWT_SECRET') ?: JwtWrapper::generateSecret(64); // base64 encoded value
$jwtKey = new JwtHashHmacSecret($secret);
$jwtWrapper = new JwtWrapper('api.example.com', $jwtKey);
```

Use your API hostname (or any issuer string you want to validate) as the first argument to `JwtWrapper`.

:::danger Secret Key Security
- Keep your secret key **confidential** and **secure**
- Use a strong, random secret key (minimum 256 bits recommended)
- Store the key in environment variables, not in code
- Rotate keys periodically
:::

## Creating JWT Tokens

### Basic Token Creation

```php
<?php
$userToken = $users->createAuthToken(
    'johndoe',           // Login (username or email)
    'password123',       // Password
    $jwtWrapper,         // JWT wrapper instance
    3600                 // Expires in 1 hour (seconds)
);

if ($userToken !== null) {
    // Return token to client
    echo json_encode(['token' => $userToken->token]);
} else {
    // Authentication failed
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
}
```

### Token with Custom Data

You can include additional data in the JWT payload:

```php
<?php
$userToken = $users->createAuthToken(
    'johndoe',
    'password123',
    $jwtWrapper,
    3600,
    [],                              // Update user properties (optional)
    [                                // Additional token data
        'role' => 'admin',
        'permissions' => ['read', 'write'],
        'tenant_id' => '12345'
    ]
);

// Access the token string
$token = $userToken->token;
```

### Update User Properties on Login

```php
<?php
$userToken = $users->createAuthToken(
    'johndoe',
    'password123',
    $jwtWrapper,
    3600,
    [                                // User properties to update
        'last_login' => date('Y-m-d H:i:s'),
        'login_count' => $loginCount + 1
    ],
    [                                // Token data
        'role' => 'admin'
    ]
);
```

### Copy User Fields Automatically

Instead of manually adding every field to `$updateTokenInfo`, pass a seventh argument with an array of `User` enum values or string property names. The service will read the corresponding getters (or custom properties) from the `UserModel` and copy them into the token payload.

```php
<?php
use ByJG\Authenticate\Enum\UserField;

$userToken = $users->createAuthToken(
    'johndoe',
    'password123',
    $jwtWrapper,
    3600,
    [],
    [],
    [UserField::Name, UserField::Email, 'department']
);
```

In the example above, the token payload receives the user's `name`, `email`, and the value returned by `$user->get('department')` automatically.

### Creating Tokens Without Password Validation

The `createInsecureAuthToken()` method creates JWT tokens without validating the user's password. This is useful for:
- Creating tokens after social authentication (OAuth, SAML, etc.)
- Implementing "remember me" functionality
- Token refresh mechanisms
- Administrative token generation

:::warning Security Warning
Use `createInsecureAuthToken()` with caution. Only call it after you've verified the user's identity through another secure method.
:::

#### Using Login String

```php
<?php
$userToken = $users->createInsecureAuthToken(
    'johndoe',              // Login (username or email)
    $jwtWrapper,
    3600,                   // Expires in 1 hour
    [],                     // Update user properties (optional)
    ['auth_method' => 'oauth']  // Additional token data
);

echo "Token: " . $userToken->token;
```

#### Using UserModel Object

```php
<?php
// After social authentication
$user = $users->getByEmail($oauthEmail);

if ($user !== null) {
    $userToken = $users->createInsecureAuthToken(
        $user,                  // Pass UserModel directly
        $jwtWrapper,
        3600,
        ['last_oauth_login' => date('Y-m-d H:i:s')],
        ['oauth_provider' => 'google']
    );

    echo "Token: " . $userToken->token;
}
```

#### Comparison: createAuthToken vs createInsecureAuthToken

| Feature             | createAuthToken   | createInsecureAuthToken                |
|---------------------|-------------------|----------------------------------------|
| Password validation | ✅ Required        | ❌ Skipped                              |
| First parameter     | Login string only | Login string OR UserModel              |
| Use case            | Normal login      | OAuth, token refresh, admin operations |
| Security level      | High              | Use with caution                       |

## Validating JWT Tokens

### Token Validation

```php
<?php
try {
    $userToken = $users->isValidToken('johndoe', $jwtWrapper, $token);

    if ($userToken !== null) {
        $user = $userToken->user;        // UserModel instance
        $tokenData = $userToken->data;   // Token payload data

        echo "Authenticated: " . $user->getName();
        echo "Role: " . $tokenData['role'];
    }

} catch (\ByJG\Authenticate\Exception\UserNotFoundException $e) {
    echo "User not found";
} catch (\ByJG\Authenticate\Exception\NotAuthenticatedException $e) {
    echo "Token validation failed: " . $e->getMessage();
} catch (\ByJG\JwtWrapper\JwtWrapperException $e) {
    echo "JWT error: " . $e->getMessage();
}
```

### Validation Checks

The `isValidToken()` method performs the following checks:

1. **User exists** - Verifies the user account exists
2. **Token hash matches** - Compares stored token hash
3. **JWT signature** - Validates the token signature
4. **Token expiration** - Checks if token has expired

## Token Storage and Invalidation

### How Tokens Are Stored

When a token is created:

```php
<?php
// A SHA-1 hash of the token is stored as a user property
$tokenHash = sha1($token);
$user->set('TOKEN_HASH', $tokenHash);
```

This allows you to invalidate tokens without maintaining a token blacklist.

### Invalidating Tokens

#### Logout (Invalidate Current Token)

```php
<?php
// Remove the token hash
$users->removeProperty($userId, 'TOKEN_HASH');
```

#### Force Re-authentication (Invalidate All Tokens)

```php
<?php
// The next createAuthToken() call will overwrite the old hash
$newToken = $users->createAuthToken($login, $password, $jwtWrapper, 3600);
```

## Complete API Example

### Login Endpoint

```php
<?php
// login.php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

try {
    $userToken = $users->createAuthToken(
        $input['username'],
        $input['password'],
        $jwtWrapper,
        3600,  // 1 hour expiration
        [
            'last_login' => date('Y-m-d H:i:s'),
            'last_ip' => $_SERVER['REMOTE_ADDR']
        ],
        [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ]
    );

    if ($userToken === null) {
        throw new Exception('Authentication failed');
    }

    echo json_encode([
        'success' => true,
        'token' => $userToken->token,
        'expires_in' => 3600
    ]);

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
```

### Protected Endpoint

```php
<?php
// api.php
header('Content-Type: application/json');

// Extract token from Authorization header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['error' => 'No token provided']);
    exit;
}

$token = $matches[1];

// Extract username from token (you need to decode it first)
try {
    $jwtData = $jwtWrapper->extractData($token);
    $username = $jwtData->data['login'] ?? null;

    if (!$username) {
        throw new Exception('Invalid token structure');
    }

    // Validate token
    $userToken = $users->isValidToken($username, $jwtWrapper, $token);

    if ($userToken === null) {
        throw new Exception('Invalid token');
    }

    $user = $userToken->user;

    // Process request
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user->getUserid(),
            'name' => $user->getName(),
            'email' => $user->getEmail()
        ]
    ]);

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => $e->getMessage()]);
}
```

### Logout Endpoint

```php
<?php
// logout.php
header('Content-Type: application/json');

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];

    try {
        $jwtData = $jwtWrapper->extractData($token);
        $username = $jwtData->data['login'] ?? null;

        $user = $users->getByLogin($username);
        if ($user !== null) {
            $users->removeProperty($user->getUserid(), 'TOKEN_HASH');
        }

        echo json_encode(['success' => true, 'message' => 'Logged out']);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'No token provided']);
}
```

## Token Expiration

### Setting Expiration Time

```php
<?php
// 15 minutes
$token = $users->createAuthToken($login, $password, $jwtWrapper, 900);

// 1 hour
$token = $users->createAuthToken($login, $password, $jwtWrapper, 3600);

// 24 hours
$token = $users->createAuthToken($login, $password, $jwtWrapper, 86400);

// 7 days
$token = $users->createAuthToken($login, $password, $jwtWrapper, 604800);
```

### Refresh Tokens

For long-lived sessions, implement a refresh token pattern:

```php
<?php
// Create short-lived access token
$accessUserToken = $users->createAuthToken(
    $login,
    $password,
    $jwtWrapper,
    900,  // 15 minutes
    [],
    ['type' => 'access']
);

// Create long-lived refresh token
$refreshUserToken = $users->createAuthToken(
    $login,
    $password,
    $jwtWrapperRefresh,  // Different wrapper/key
    604800,  // 7 days
    [],
    ['type' => 'refresh']
);

echo json_encode([
    'access_token' => $accessUserToken->token,
    'refresh_token' => $refreshUserToken->token
]);
```

## Security Best Practices

1. **Use HTTPS** - Always transmit tokens over HTTPS
2. **Short expiration times** - Use short-lived tokens (15-60 minutes)
3. **Implement refresh tokens** - For longer sessions
4. **Validate on every request** - Don't trust the client
5. **Store securely** - Don't store tokens in localStorage if possible
6. **Include audience claims** - Limit token usage scope
7. **Monitor for abuse** - Track token usage patterns
8. **Rotate secrets** - Periodically rotate JWT secrets

## Common Pitfalls

❌ **Don't store sensitive data in JWT payload** - It's not encrypted, only signed

❌ **Don't use weak secret keys** - Use cryptographically random keys

❌ **Don't skip expiration** - Always set reasonable expiration times

❌ **Don't forget to invalidate** - Provide logout functionality

❌ **Don't use HTTP** - Always use HTTPS in production

## Next Steps

- [Authentication](authentication.md) - Other authentication methods
- [Session Context](session-context.md) - Session-based authentication
- [User Properties](user-properties.md) - Managing user data
