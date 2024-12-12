<?php

declare(strict_types=1);

namespace Rrd108\NavM2m;

use Ramsey\Uuid\Uuid;

class NavM2m
{
    private $secrets;
    private $mode;
    private $API_URL;
    private $file;
    private $xsdFile = __DIR__ . '/schema.xsd';
    private $sandboxApiUrl = 'https://m2m-dev.nav.gov.hu/rest-api/1.1/';
    private $productionApiUrl = 'https://???api.nav.gov.hu/m2m/rest-api/';
    private $endpoints = [
        'createToken' => 'NavM2mCommon/tokenService/Token',
        'userNonce' => 'NavM2mCommon/userregistrationService/Nonce',
        'userActivation' => 'NavM2mCommon/userregistrationService/Activation',
    ];
    public $logger = true;
    private $log = [];

    public function __construct(string $file, array $secrets, string $mode = 'sandbox')
    {
        if (!file_exists($file)) {
            throw new \Exception("A {$file} fájl nem található!");
        }
        $this->file = $file;

        if (!$this->isValidXML($file, $this->xsdFile)) {
            throw new \Exception("A {$file} fájl nem valid XML!");
        }

        if (!$secrets['clientId'] || !$secrets['clientSecret'] || !$secrets['username'] || !$secrets['password']) {
            throw new \Exception("Client ID, client secret, username and password are required");
        }

        $this->secrets = $secrets;
        $this->mode = $mode;
        $this->API_URL = $mode == 'sandbox' ? $this->sandboxApiUrl : $this->productionApiUrl;

        $this->log('NavM2m initialized in ' . $this->mode . ' mode');
    }

    private function log(string $message)
    {
        if ($this->logger) {
            echo '  👉 ' . $message . "\n";
            //$this->log[] = $message;
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

    /**
     * @return array{
     *     resultMessage: ?string,
     *     accessToken: string,
     *     expires: int,
     *     resultCode: string
     * }
     */
    public function createToken(): array
    {
        $endpoint = $this->API_URL . $this->endpoints['createToken'];

        $data = [
            'clientId' => $this->secrets['clientId'],
            'clientSecret' => $this->secrets['clientSecret'],
            'username' => $this->secrets['username'],
            'password' => $this->secrets['password']
        ];

        return $this->post(
            endpoint: $endpoint,
            data: $data,
            messageId: $this->createMessageId()
        );
    }

    private function createMessageId()
    {
        return Uuid::uuid4()->toString();
    }

    /**
     * @return array{
     * }
     */
    private function post(string $endpoint, array $data, string $messageId, string $accessToken = null)
    {
        $this->log('Sending POST request to ' . $endpoint);
        $requestBody = ['requestData' => $data];

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'messageId: ' . $messageId
        ];

        if ($accessToken) {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }

        $this->log('Headers: ' . json_encode($headers));
        $this->log('Request body: ' . json_encode($requestBody));

        $options = [
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestBody),
            CURLOPT_HTTPHEADER => $headers,
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

        $this->log('Received response from ' . $endpoint . ': ' . $response);
        return json_decode($response, true);
    }

    public function activateUser(string $nonce, string $accessToken, string $signatureKeyFirstPart)
    {
        $this->log('Activating user with nonce: ' . $nonce);
        $endpoint = $this->API_URL . $this->endpoints['userNonce'];

        $data = ['nonce' => $nonce];

        $response = $this->post(
            endpoint: $endpoint,
            data: $data,
            messageId: $this->createMessageId(),
            accessToken: $accessToken
        );

        if (!isset($response['signatureKeySecondPart'])) {
            throw new \Exception('Signature key second part not received');
        }
        $this->log('Signature key second part received: ' . $response['signatureKeySecondPart']);

        $signatureKey = $signatureKeyFirstPart . $response['signatureKeySecondPart'];
        $this->log('Signature key: ' . $signatureKey);

        $endpoint = $this->API_URL . $this->endpoints['userActivation'];
        $messageId = $this->createMessageId();
        $data = ['signature' => $this->generateSignature(
            messageId: $messageId,
            data: '',
            signatureKey: $signatureKey
        )];
        $response = $this->post(
            endpoint: $endpoint,
            data: $data,
            messageId: $messageId,
            accessToken: $accessToken
        );
        $this->log('Received response from ' . $endpoint . ': ' . json_encode($response));
        $this->log('Successfull user activation');

        $token = $this->createToken();
        $this->log('New token: ' . $token);
        return $token;
    }

    private function generateSignature(string $messageId, $data, string $signatureKey)
    {
        $timestamp = date("YmdHis", time());
        $signatureData = $messageId . $timestamp . $data . $signatureKey;
        $this->log('Signature data: ' . $signatureData);
        $signatureHash = hash('sha256', $signatureData, true);
        return base64_encode($signatureHash);
    }
}
