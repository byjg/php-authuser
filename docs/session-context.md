---
sidebar_position: 5
title: Session Context
---

# Session Context

The `SessionContext` class manages user authentication state using PSR-6 compatible cache storage.

## Creating a Session Context

```php
<?php
use ByJG\Authenticate\SessionContext;
use ByJG\Cache\Factory;

// Using PHP Session (recommended for most cases)
$sessionContext = new SessionContext(Factory::createSessionPool());

// With a custom key (optional)
$sessionContext = new SessionContext(Factory::createSessionPool(), 'myapp');
```

## Session Storage Options

The library uses PSR-6 CachePool for session storage, allowing flexibility in how sessions are stored.

### PHP Session (Default)

```php
<?php
$sessionContext = new SessionContext(Factory::createSessionPool());
```

### Memcached

```php
<?php
use ByJG\Cache\Factory;

// IMPORTANT: You MUST provide a unique prefix per user
$uniquePrefix = session_id(); // or any other unique identifier
$cachePool = Factory::createMemcachedPool();
$sessionContext = new SessionContext($cachePool, $uniquePrefix);
```

:::danger Unique Prefixes Required
When using Memcached or other shared cache storage, you **MUST** define a **UNIQUE** prefix for each session. If two users share the same prefix, they will have unexpected authentication issues.

**If you cannot create/manage unique prefixes reliably, use the regular PHP Session storage instead.**
:::

### Redis

```php
<?php
$cachePool = Factory::createRedisPool('redis://localhost:6379');
$sessionContext = new SessionContext($cachePool, $uniquePrefix);
```

## Managing Authentication

### Register Login

```php
<?php
// After validating user credentials
$sessionContext->registerLogin($userId);

// With additional session data
$sessionContext->registerLogin($userId, ['ip' => $_SERVER['REMOTE_ADDR']]);
```

### Check Authentication Status

```php
<?php
if ($sessionContext->isAuthenticated()) {
    echo "User is logged in";
} else {
    echo "User is not authenticated";
}
```

### Get Current User Info

```php
<?php
if ($sessionContext->isAuthenticated()) {
    $userId = $sessionContext->userInfo();
    // Use $userId to fetch user details
}
```

### Logout

```php
<?php
$sessionContext->registerLogout();
```

## Storing Session Data

You can store custom data in the user's session. This data exists only while the user is logged in.

### Store Data

```php
<?php
$sessionContext->setSessionData('shopping_cart', [
    'item1' => 'Product A',
    'item2' => 'Product B'
]);

$sessionContext->setSessionData('last_page', '/products');
```

:::warning Authentication Required
The user must be authenticated to use `setSessionData()`. If not, a `NotAuthenticatedException` will be thrown.
:::

### Retrieve Data

```php
<?php
$cart = $sessionContext->getSessionData('shopping_cart');
$lastPage = $sessionContext->getSessionData('last_page');
```

Returns `false` if:
- The user is not authenticated
- The key doesn't exist

### Session Data Lifecycle

- Session data is stored when the user logs in
- It persists across requests while the user remains logged in
- It is automatically deleted when the user logs out
- It is lost if the session expires

## Complete Example

```php
<?php
use ByJG\Authenticate\UsersDBDataset;
use ByJG\Authenticate\SessionContext;
use ByJG\Cache\Factory;

// Initialize
$users = new UsersDBDataset($dbDriver);
$sessionContext = new SessionContext(Factory::createSessionPool());

// Login flow
if (isset($_POST['login'])) {
    $user = $users->isValidUser($_POST['username'], $_POST['password']);

    if ($user !== null) {
        $sessionContext->registerLogin($user->getUserid());
        $sessionContext->setSessionData('login_time', time());
        header('Location: /dashboard');
        exit;
    }
}

// Protected pages
if (!$sessionContext->isAuthenticated()) {
    header('Location: /login');
    exit;
}

$userId = $sessionContext->userInfo();
$user = $users->getById($userId);
$loginTime = $sessionContext->getSessionData('login_time');

echo "Welcome, " . $user->getName();
echo "Logged in at: " . date('Y-m-d H:i:s', $loginTime);

// Logout
if (isset($_POST['logout'])) {
    $sessionContext->registerLogout();
    header('Location: /login');
    exit;
}
```

## Best Practices

1. **Use PHP Session storage** unless you have specific requirements for distributed sessions
2. **Always check authentication** before accessing protected resources
3. **Clear sensitive session data** when no longer needed
4. **Set appropriate session timeouts** based on your security requirements
5. **Regenerate session IDs** after login to prevent session fixation attacks

## Next Steps

- [Authentication](authentication.md) - User authentication methods
- [User Properties](user-properties.md) - Store persistent user data
