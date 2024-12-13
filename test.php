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
$navM2m = new NavM2m(mode: 'production', client: $client);

// user aktiválása - csak egyszer per user, nonce a userből
/*
$user = $navM2m->getInactiveUser($_ENV['NAV2M2M_USER_TEMPORARY_API_KEY']);
$token = $navM2m->createToken($user);
// list($token, $signingKey) = $navM2m->activateUser($user, $token['accessToken']);
// TODO el kell tárolni a signingKey-t az adatbázisban a userhez
*/

// TODO user adatainak lekérdezése az adatbázisból
// INFO csak teszteléshez, mivel itt nincs adatbázis
$user = [
    'signatureKey' => $_ENV['NAV2M2M_USER_SIGNATUREKEY']
];

$result = $navM2m->addFile(
    file: './09teszt.xml',
    signatureKey: $user['signatureKey'],
    accessToken: $token['accessToken']
);

if (!$result['virusScanResultCode']) {
    // a vírus ellenőrzés tovább tartott mint 30 másodperc, külön le kell kérdezni
    // sleep(30);
    $result = $navM2m->getFileStatus($result['fileId'], $token['accessToken']);
    $result['virusScanResultCode'] = $result['resultCode'];
    $result = $navM2m->createDocument($result['fileId'], $token['accessToken']);
    if ($result['documentStatus'] == 'CREATE_DOCUMENT_SUCCESS') {
        echo '👉 documentStatus: CREATE_DOCUMENT_SUCCESS' . "\n";
        $result = $navM2m->updateDocument($result['fileId'], $token['accessToken']);
    }
}

if ($result['virusScanResultCode'] == 'PASSED') {
    echo '👉 virusScanResultCode: PASSED' . "\n";
    unset($result['virusScanResultCode']);
}
if ($result['virusScanResultCode'] == 'WAITING') {
    echo '👉 virusScanResultCode: WAITING' . "\n";
}
if ($result['virusScanResultCode'] == 'FAILED') {
    echo '👉 virusScanResultCode: FAILED' . "\n";
}
if ($result['virusScanResultCode'] == 'OTHER_ERROR') {
    echo '👉 virusScanResultCode: OTHER_ERROR' . "\n";
}
