---
sidebar_position: 12
title: Complete Examples
---

# Complete Examples

This page contains complete, working examples for common use cases.

## Simple Web Application

### Setup

```php
<?php
// config.php
require_once 'vendor/autoload.php';

use ByJG\Authenticate\Enum\LoginField;
use ByJG\Authenticate\Service\UsersService;
use ByJG\Authenticate\Repository\UsersRepository;
use ByJG\Authenticate\Repository\UserPropertiesRepository;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\Authenticate\SessionContext;
use ByJG\Cache\Factory;
use ByJG\AnyDataset\Db\Factory as DbFactory;
use ByJG\AnyDataset\Db\DatabaseExecutor;

// Database connection
$dbDriver = DbFactory::getDbInstance('mysql://user:password@localhost/myapp');
$db = DatabaseExecutor::using($dbDriver);

// Initialize repositories
$usersRepo = new UsersRepository($db, UserModel::class);
$propsRepo = new UserPropertiesRepository($db, UserPropertiesModel::class);

// Initialize user service
$users = new UsersService($usersRepo, $propsRepo, LoginField::Username);

// Initialize session
$sessionContext = new SessionContext(Factory::createSessionPool());
```

### Login Page

```php
<?php
// login.php
require_once 'config.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $user = $users->isValidUser($username, $password);

        if ($user !== null) {
            $sessionContext->registerLogin($user->getUserid());
            $sessionContext->setSessionData('login_time', time());

            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    } catch (Exception $e) {
        $error = 'An error occurred: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
    <h1>Login</h1>

    <?php if (isset($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div>
            <label>Username:</label>
            <input type="text" name="username" required>
        </div>
        <div>
            <label>Password:</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit">Login</button>
    </form>

    <p><a href="register.php">Create an account</a></p>
</body>
</html>
```

### Registration Page

```php
<?php
// register.php
require_once 'config.php';

use ByJG\Authenticate\Definition\PasswordDefinition;
use ByJG\Authenticate\Exception\UserExistsException;

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    try {
        // Validate input
        if ($password !== $confirmPassword) {
            throw new Exception('Passwords do not match');
        }

        // Password validation
        $passwordDef = new PasswordDefinition([
            PasswordDefinition::MINIMUM_CHARS => 8,
            PasswordDefinition::REQUIRE_UPPERCASE => 1,
            PasswordDefinition::REQUIRE_LOWERCASE => 1,
            PasswordDefinition::REQUIRE_NUMBERS => 1,
        ]);

        $result = $passwordDef->matchPassword($password);
        if ($result !== PasswordDefinition::SUCCESS) {
            throw new Exception('Password does not meet requirements');
        }

        // Create user
        $user = $users->addUser($name, $username, $email, $password);

        // Auto-login
        $sessionContext->registerLogin($user->getUserid());

        header('Location: dashboard.php');
        exit;

    } catch (UserExistsException $e) {
        $error = 'Username or email already exists';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
</head>
<body>
    <h1>Create Account</h1>

    <?php if (isset($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div>
            <label>Full Name:</label>
            <input type="text" name="name" required>
        </div>
        <div>
            <label>Email:</label>
            <input type="email" name="email" required>
        </div>
        <div>
            <label>Username:</label>
            <input type="text" name="username" required>
        </div>
        <div>
            <label>Password:</label>
            <input type="password" name="password" required>
            <small>Minimum 8 characters, at least 1 uppercase, 1 lowercase, and 1 number</small>
        </div>
        <div>
            <label>Confirm Password:</label>
            <input type="password" name="confirm_password" required>
        </div>
        <button type="submit">Register</button>
    </form>

    <p><a href="login.php">Already have an account?</a></p>
</body>
</html>
```

### Protected Dashboard

```php
<?php
// dashboard.php
require_once 'config.php';

session_start();

// Check authentication
if (!$sessionContext->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

// Get current user
$userId = $sessionContext->userInfo();
$user = $users->getById($userId);
$loginTime = $sessionContext->getSessionData('login_time');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
    <h1>Welcome, <?= htmlspecialchars($user->getName()) ?></h1>

    <p>Email: <?= htmlspecialchars($user->getEmail()) ?></p>
    <p>Logged in at: <?= date('Y-m-d H:i:s', $loginTime) ?></p>

    <?php if ($user->hasRole('admin')): ?>
        <p><strong>You are an administrator</strong></p>
        <p><a href="admin.php">Admin Panel</a></p>
    <?php endif; ?>

    <p><a href="profile.php">Edit Profile</a></p>
    <p><a href="logout.php">Logout</a></p>
</body>
</html>
```

### Logout

```php
<?php
// logout.php
require_once 'config.php';

session_start();

$sessionContext->registerLogout();
session_destroy();

header('Location: login.php');
exit;
```

## REST API with JWT

### API Configuration

```php
<?php
// api-config.php
require_once 'vendor/autoload.php';

use ByJG\Authenticate\Enum\LoginField;
use ByJG\Authenticate\Service\UsersService;
use ByJG\Authenticate\Repository\UsersRepository;
use ByJG\Authenticate\Repository\UserPropertiesRepository;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\AnyDataset\Db\Factory as DbFactory;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\JwtWrapper\JwtHashHmacSecret;
use ByJG\JwtWrapper\JwtWrapper;

// Database
$dbDriver = DbFactory::getDbInstance('mysql://user:password@localhost/api_db');
$db = DatabaseExecutor::using($dbDriver);

// Initialize repositories and service
$usersRepo = new UsersRepository($db, UserModel::class);
$propsRepo = new UserPropertiesRepository($db, UserPropertiesModel::class);
$users = new UsersService($usersRepo, $propsRepo, LoginField::Username);

// JWT
$jwtSecret = getenv('JWT_SECRET') ?: 'base64-encoded-secret-goes-here=='; // Store this in environment variables
$jwtWrapper = new JwtWrapper('api.example.com', new JwtHashHmacSecret($jwtSecret));

// Helper function
function jsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
```

### Login Endpoint

```php
<?php
// api/login.php
require_once '../api-config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

try {
    $userToken = $users->createAuthToken(
        $username,
        $password,
        $jwtWrapper,
        3600,  // 1 hour
        [
            'last_login' => date('Y-m-d H:i:s'),
            'last_ip' => $_SERVER['REMOTE_ADDR']
        ],
        [
            'ip' => $_SERVER['REMOTE_ADDR']
        ]
    );

    if ($userToken === null) {
        jsonResponse(['error' => 'Invalid credentials'], 401);
    }

    jsonResponse([
        'success' => true,
        'token' => $userToken->token,
        'expires_in' => 3600
    ]);

} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
```

### Protected Endpoint

```php
<?php
// api/user.php
require_once '../api-config.php';

// Extract token
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    jsonResponse(['error' => 'No token provided'], 401);
}

$token = $matches[1];

try {
    // Decode token to get username
    $jwtData = $jwtWrapper->extractData($token);
    $username = $jwtData->data['login'] ?? null;

    if (!$username) {
        jsonResponse(['error' => 'Invalid token'], 401);
    }

    // Validate token
    $userToken = $users->isValidToken($username, $jwtWrapper, $token);

    if ($userToken === null) {
        jsonResponse(['error' => 'Token validation failed'], 401);
    }

    $user = $userToken->user;

    // Handle request
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get user info
        jsonResponse([
            'id' => $user->getUserid(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'role' => $user->getRole()
        ]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update user info
        $input = json_decode(file_get_contents('php://input'), true);

        if (isset($input['name'])) {
            $user->setName($input['name']);
        }
        if (isset($input['email'])) {
            $user->setEmail($input['email']);
        }

        $users->save($user);

        jsonResponse(['success' => true, 'message' => 'User updated']);
    } else {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }

} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
```

## Multi-Tenant Application

```php
<?php
// multi-tenant-example.php
require_once 'vendor/autoload.php';

use ByJG\Authenticate\Enum\LoginField;
use ByJG\Authenticate\Service\UsersService;
use ByJG\Authenticate\Repository\UsersRepository;
use ByJG\Authenticate\Repository\UserPropertiesRepository;
use ByJG\Authenticate\Model\UserModel;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\AnyDataset\Db\Factory as DbFactory;
use ByJG\AnyDataset\Db\DatabaseExecutor;

$dbDriver = DbFactory::getDbInstance('mysql://user:password@localhost/multitenant_db');
$db = DatabaseExecutor::using($dbDriver);

$usersRepo = new UsersRepository($db, UserModel::class);
$propsRepo = new UserPropertiesRepository($db, UserPropertiesModel::class);
$users = new UsersService($usersRepo, $propsRepo, LoginField::Username);

// Add user to organization
function addUserToOrganization($users, $userId, $orgId, $role = 'member')
{
    $users->addProperty($userId, 'organization', $orgId);
    $users->addProperty($userId, "org_{$orgId}_role", $role);
}

// Check if user has access to organization
function hasOrganizationAccess($users, $userId, $orgId)
{
    return $users->hasProperty($userId, 'organization', $orgId);
}

// Get user's role in organization
function getOrganizationRole($users, $userId, $orgId)
{
    return $users->getProperty($userId, "org_{$orgId}_role");
}

// Get all users in organization
function getOrganizationUsers($users, $orgId)
{
    return $users->getUsersByProperty('organization', $orgId);
}

// Usage
$userId = 1;
$orgId = 'org-123';

// Add user to organization
addUserToOrganization($users, $userId, $orgId, 'admin');

// Check access
if (hasOrganizationAccess($users, $userId, $orgId)) {
    $role = getOrganizationRole($users, $userId, $orgId);
    echo "User has access as: $role\n";

    // Get all members
    $members = getOrganizationUsers($users, $orgId);
    foreach ($members as $member) {
        echo "- " . $member->getName() . "\n";
    }
}
```

## Permission System

```php
<?php
// permission-system-example.php
require_once 'vendor/autoload.php';

use ByJG\Authenticate\Service\UsersService;

class PermissionManager
{
    private UsersService $users;

    public function __construct(UsersService $users)
    {
        $this->users = $users;
    }

    public function grantPermission($userId, $resource, $action)
    {
        $permission = "$resource:$action";
        $this->users->addProperty($userId, 'permission', $permission);
    }

    public function revokePermission($userId, $resource, $action)
    {
        $permission = "$resource:$action";
        $this->users->removeProperty($userId, 'permission', $permission);
    }

    public function hasPermission($userId, $resource, $action)
    {
        $permission = "$resource:$action";
        return $this->users->hasProperty($userId, 'permission', $permission);
    }

    public function getPermissions($userId)
    {
        $permissions = $this->users->getProperty($userId, 'permission');
        return is_array($permissions) ? $permissions : [$permissions];
    }
}

// Usage
$permissionManager = new PermissionManager($users);

// Grant permissions
$permissionManager->grantPermission($userId, 'posts', 'create');
$permissionManager->grantPermission($userId, 'posts', 'edit');
$permissionManager->grantPermission($userId, 'posts', 'delete');
$permissionManager->grantPermission($userId, 'users', 'view');

// Check permissions
if ($permissionManager->hasPermission($userId, 'posts', 'delete')) {
    echo "User can delete posts\n";
}

// Get all permissions
$permissions = $permissionManager->getPermissions($userId);
print_r($permissions);

// Revoke permission
$permissionManager->revokePermission($userId, 'posts', 'delete');
```

## Next Steps

- [Getting Started](getting-started.md) - Basic concepts
- [User Management](user-management.md) - Managing users
- [Authentication](authentication.md) - Authentication methods
- [JWT Tokens](jwt-tokens.md) - Token-based authentication
