<?php

require "vendor/autoload.php";

$users = new ByJG\Authenticate\UsersAnyDataset('/tmp/pass.anydata.xml');

$users->addUser('Some User Full Name', 'someuser', 'someuser@someemail.com', '12345');
//$users->save();

$user = $users->isValidUser('someuser', '12345');
var_dump($user);
if (!is_null($user))
{
    $session = new \ByJG\Authenticate\SessionContext();
    $session->registerLogin($userId);

    echo "Authenticated: " . $session->isAuthenticated();
    print_r($session->userInfo());
}


