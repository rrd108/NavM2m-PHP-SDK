<?php
require_once './vendor/autoload.php';

use Rrd108\NavM2m\NavM2m;
use Dotenv\Dotenv;

echo '👉 test' . "\n";

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// echo Ramsey\Uuid\Uuid::uuid4()->toString(); die;
$client = [
    'id' => $_ENV['NAV2M2M_CLIENT_ID'],
    'secret' => $_ENV['NAV2M2M_CLIENT_SECRET'],
];
$navM2m = new NavM2m(file: './09teszt.xml', mode: 'production', client: $client);
$user = $navM2m->getUser($_ENV['NAV2M2M_USER_TEMPORARY_API_KEY']);
$token = $navM2m->createToken($user);
// user activation - should be done only once per user, nonce is coming from the user
$newToken = $navM2m->activateUser($user, $token['accessToken']);
