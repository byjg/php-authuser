<?php

require "vendor/autoload.php";

use ByJG\Authenticate\UsersAnyDataset;
use ByJG\Authenticate\SessionContext;
use ByJG\AnyDataset\Core\AnyDataset;
use ByJG\Cache\Factory;

// Create or load AnyDataset from XML file
$anyDataset = new AnyDataset('/tmp/users.xml');

// Initialize user management
$users = new UsersAnyDataset($anyDataset);

// Add a new user
$user = $users->addUser('Some User Full Name', 'someuser', 'someuser@someemail.com', '12345');
echo "User created with ID: " . $user->getUserid() . "\n";

// Validate user credentials
$authenticatedUser = $users->isValidUser('someuser', '12345');
var_dump($authenticatedUser);

if ($authenticatedUser !== null) {
    // Create session context
    $session = new SessionContext(Factory::createSessionPool());

    // Register login
    $session->registerLogin($authenticatedUser->getUserid());

    echo "Authenticated: " . ($session->isAuthenticated() ? 'yes' : 'no') . "\n";
    echo "User ID: " . $session->userInfo() . "\n";

    // Store some session data
    $session->setSessionData('login_time', time());

    // Get the user info
    $currentUser = $users->getById($session->userInfo());
    echo "Welcome, " . $currentUser->getName() . "\n";
}


