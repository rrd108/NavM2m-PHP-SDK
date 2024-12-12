<?php

namespace Rrd108\NavM2m;

use Ramsey\Uuid\Uuid;

class NavM2m
{
    private $secrets;
    private $mode;
    private $API_URL;
    private $file;
    private $xsdFile = __DIR__ . '/schema.xsd';

    private $endpoints = [
        'createToken' => 'NavM2mCommon/tokenService/Token',
    ];

    public function __construct(string $file, array $secrets, string $mode = 'sandbox')
    {
        $this->secrets = $secrets;
        $this->mode = $mode;
        $this->API_URL = $mode == 'sandbox' ? 'https://m2m-dev.nav.gov.hu/rest-api/1.1/' : 'https://???api.nav.gov.hu/m2m/rest-api/';

        if (!file_exists($file)) {
            throw new \Exception("A {$file} fájl nem található!");
        }
        $this->file = $file;

        if (!$this->isValidXML($file, $this->xsdFile)) {
            throw new \Exception("A {$file} fájl nem valid XML!");
        }
    }

    private function isValidXML($xmlFile, $xsdFile)
    {
        $dom = new \DOMDocument();
        $dom->load($xmlFile);
        return $dom->schemaValidate($xsdFile);
    }

    private function getXmlContent()
    {
        $xmlContent = file_get_contents($this->file);
        if ($xmlContent === false) {
            throw new \Exception("Nem sikerült betölteni az XML fájlt!");
        }
        return $xmlContent;
    }

    public function createToken()
    {
        $endpoint = $this->API_URL . $this->endpoints['createToken'];

        $data = [
            'clientId' => $this->secrets['clientId'],
            'clientSecret' => $this->secrets['clientSecret'],
            'username' => $this->secrets['username'],
            'password' => $this->secrets['password']
        ];

        return $this->post($endpoint, $data);
    }

    private function post($endpoint, $data)
    {
        $messageId = Uuid::uuid4()->toString();
        $requestBody = [
            'requestData' => $data,
        ];

        $options = [
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestBody),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'messageId: ' . $messageId
            ],
            CURLOPT_RETURNTRANSFER => true
        ];

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new \Exception("Curl error: " . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("HTTP error: " . $httpCode . " Response: " . $response);
        }

        return $response;
    }
}

    /*
    public function generateHash()
    {
        $messageId = Uuid::uuid4()->toString();
        $timestamp = gmdate('YmdHis');
        $xmlContent = $this->getXmlContent();
        return hash('sha256', $xmlContent);
    }
}




function redeemNonce($nonce)
{
    $endpoint = API_URL . 'NavM2mCommon/userregistrationService/Nonce';

    $data = [
        'nonce' => $nonce
    ];

    return post($endpoint, $data);
}


/*
function uploadDocument($xmlFile)
{
    // Fájlfeltöltési végpont URL-je
    $uploadUrl = API_URL . '/file/upload';
    $accessToken = getToken();

    $xmlContent = getXmlContent($xmlFile);
    $hash = generateHash($xmlContent);

    // Az HTTP kérés adatai
    $headers = [
        'Content-Type: application/xml',
        'Authorization: Bearer ' . $accessToken
    ];
    $data = [
        'file' => new \CURLFile($xmlFile, 'application/xml'),
        'hash' => $hash
    ];

    // CURL inicializálása
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $uploadUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Kérés elküldése
    $response = curl_exec($ch);

    // Hibakezelés
    if (curl_errno($ch)) {
        echo "Hiba: " . curl_error($ch);
    } else {
        echo "Fájlfeltöltés válasza: $response";
    }

    // CURL bezárása
    curl_close($ch);
}
*/