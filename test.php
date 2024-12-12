<?php
require_once './vendor/autoload.php';

use Rrd108\NavM2m\NavM2m;
use Dotenv\Dotenv;

echo '👉 test' . "\n";

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$secrets = [
    'clientId' => $_ENV['NAV2M2M_CLIENT_ID'],
    'clientSecret' => $_ENV['NAV2M2M_CLIENT_SECRET'],
    'username' => $_ENV['NAV2M2M_USERNAME'],
    'password' => $_ENV['NAV2M2M_PASSWORD'],
];

$navM2m = new NavM2m('./09teszt.xml', $secrets, 'sandbox');

$token = $navM2m->createToken();
var_dump($token);

// user activation - should be done only once per user, nonce is coming from the user
$newToken = $navM2m->activateUser($_ENV['NAV2M2M__USER__NONCE'], $token['accessToken'], $_ENV['NAV2M2M_SIGNING_KEY_FIRST_PART']);
var_dump($newToken);
