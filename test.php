<?php
require_once './vendor/autoload.php';

use Rrd108\NavM2m\NavM2m;
use Dotenv\Dotenv;

echo '👀 test' . "\n";

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$client = [
    'id' => $_ENV['NAV2M2M_CLIENT_ID'], // a kliens program azonosítója az UPO-nál
    'secret' => $_ENV['NAV2M2M_CLIENT_SECRET'], // a kliens program titkos kulcsa az UPO-nál
];
$navM2m = new NavM2m(mode: 'sandbox', client: $client); // mode: 'production'

// INFO a $_ENV['NAV2M2M_USER_TEMPORARY_API_KEY'] a usernek az UPO-ról a user tárhelyére kiküldött API kulcs
// user aktiválása - csak egyszer per user
/*
$user = $navM2m->getInactiveUser($_ENV['NAV2M2M_USER_TEMPORARY_API_KEY']);
$token = $navM2m->createToken($user);
list($token, $signingKey) = $navM2m->activateUser($user, $token['accessToken']);
print_r($token);
print_r($signingKey);
*/
// TODO el kell tárolni a username, password és signingKey-t az adatbázisban a userhez

// TODO user adatainak lekérdezése az adatbázisból
// INFO csak teszteléshez, mivel itt nincs adatbázis
list($userName, $userPassword) = explode('-', $_ENV['NAV2M2M_USER_TEMPORARY_API_KEY']);
$user = [
    'name' => $userName,
    'password' => $userPassword,
    'signatureKey' => $_ENV['NAV2M2M_USER_SIGNATUREKEY']
];
// INFO adatbázis faking vége

$token = $navM2m->createToken($user);

if ($token['resultCode'] != 'TOKEN_CREATION_SUCCESSFUL') {
    echo '👀 Sikertelen token létrehozás' . "\n";
    print_r($token);
    die;
}

if ($token['resultCode'] == 'TOKEN_CREATION_SUCCESSFUL') {
    $result = $navM2m->addFile(
        file: './09teszt.xml',
        signatureKey: $user['signatureKey'],
        accessToken: $token['accessToken'],
    );

    if ($result['result_code'] != 'UPLOAD_SUCCESS') {
        echo '👀 upload failed' . "\n";
        print_r($result);
        die;
    }

    if ($result['result_code'] == 'UPLOAD_SUCCESS') {
        $fileId = $result['fileId'];
        $correlationId = $result['correlationId'];

        if (!isset($result['virusScanResultCode'])) {
            // a vírus ellenőrzés tovább tartott mint 30 másodperc, külön le kell kérdezni
            // sleep(30);
            $result = $navM2m->getFileStatus($fileId, $token['accessToken']);
            $result['virusScanResultCode'] = $result['resultCode'];
        }

        if ($result['virusScanResultCode'] == 'WAITING') {
            echo '👀 virusScanResultCode: WAITING' . "\n";
            die;
        }

        if ($result['virusScanResultCode'] == 'FAILED') {
            echo '👀 virusScanResultCode: FAILED' . "\n";
            die;
        }

        if ($result['virusScanResultCode'] == 'OTHER_ERROR') {
            echo '👀 virusScanResultCode: OTHER_ERROR' . "\n";
            die;
        }

        if ($result['virusScanResultCode'] == 'PASSED') {
            echo '👀 virusScanResultCode: PASSED' . "\n";
            $result = $navM2m->createDocument(
                fileId: $fileId,
                correlationId: $correlationId,
                signatureKey: $user['signatureKey'],
                accessToken: $token['accessToken']
            );


            if ($result['resultCode'] != 'CREATE_DOCUMENT_SUCCESS') {
                echo '👀 documentStatus: ' . $result['documentStatus'] . "\n";
                if ($result['documentStatus'] == 'UNDER_PREVALIDATION' || $result['documentStatus'] == 'UNDER_VALIDATION') {
                    echo '👀 documentStatus: ' . $result['documentStatus'] . "\n";
                    // TODO we have to wait for the document to be validated and then call the getDocument endpoint
                }
            }

            if ($result['resultCode'] == 'CREATE_DOCUMENT_SUCCESS') {
                echo '👀 documentStatus: CREATE_DOCUMENT_SUCCESS' . "\n";

                if ($result['documentStatus'] != 'VALIDATED') {
                    echo '👀 documentStatus: ' . $result['documentStatus'] . "\n";
                    // TODO we have to wait for the document to be validated and then call the getDocument endpoint
                }

                if ($result['documentStatus'] == 'VALIDATED') {
                    $result = $navM2m->updateDocument(
                        fileId: $fileId,
                        correlationId: $correlationId,
                        signatureKey: $user['signatureKey'],
                        accessToken: $token['accessToken']
                    );

                    if ($result['documentStatus'] != 'SUBMITTED' || !$result['arrivalNumber']) {
                        echo "👀 documentStatus: {$result['documentStatus']}, resultCode: {$result['resultCode']} \n";
                        die;
                    }

                    if ($result['documentStatus'] == 'SUBMITTED' && $result['arrivalNumber']) {
                        echo "💥 Sikeres bizonylat beküldés, érkezési szám: {$result['arrivalNumber']} \n";
                    }
                }
            }
        }
    }
}
