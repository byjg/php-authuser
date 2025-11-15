<?php

require "vendor/autoload.php";

use ByJG\AnyDataset\Db\Factory as DbFactory;
use ByJG\Authenticate\SessionContext;
use ByJG\Authenticate\UsersDBDataset;
use ByJG\Cache\Factory;

// Create database connection (using SQLite for this example)
$dbDriver = DbFactory::getDbInstance('sqlite:///tmp/users.db');

// Initialize user management
$users = new UsersDBDataset($dbDriver);

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
    $currentUser = $users->get($session->userInfo());
    echo "Welcome, " . $currentUser->getName() . "\n";
}


