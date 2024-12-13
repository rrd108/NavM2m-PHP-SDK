<?php

declare(strict_types=1);

namespace Rrd108\NavM2m;

use Ramsey\Uuid\Uuid;

class NavM2m
{
    private $client;
    private $mode;
    private $API_URL;
    private $xsdFile = __DIR__ . '/schema.xsd';
    private $sandboxApiUrl = 'https://m2m-dev.nav.gov.hu/rest-api/1.1/';
    private $productionApiUrl = 'https://m2m.nav.gov.hu/rest-api/1.1/';
    private $endpoints = [
        'createToken' => 'NavM2mCommon/tokenService/Token',
        'userNonce' => 'NavM2mCommon/userregistrationService/Nonce',
        'userActivation' => 'NavM2mCommon/userregistrationService/Activation',
        'addFile' => 'NavM2mCommon/filestoreUploadService/File',
        'getFileStatus' => 'NavM2mDocument/filestoreDownloadService/File',
        'createDocument' => 'NavM2mDocument/documentService/Document',
        'updateDocument' => 'NavM2mDocument/documentService/Document',
    ];
    public $logger = true;
    private $log = [];

    public function __construct(string $mode = 'sandbox', array $client)
    {
        if (!$client['id'] || !$client['secret']) {
            throw new \Exception("Client ID, client secret, username and password are required");
        }

        $this->client = $client;
        $this->mode = $mode;
        $this->API_URL = $mode == 'production' ? $this->productionApiUrl : $this->sandboxApiUrl;

        $this->log('NavM2m:constructor initialized in ' . $this->mode . ' mode');
    }

    /**
     * @return array{
     *     id: string,
     *     password: string,
     *     signingKeyFirstPart: string,
     *     nonce: string
     * }
     */
    public function getInactiveUser(string $temporaryUserApiKey)
    {
        $this->log('NavM2m:getInactiveUser Getting inactive user with temporary user API key: ' . $temporaryUserApiKey);
        $data = explode('-', $temporaryUserApiKey);
        return [
            'name' => $data[0],
            'password' => $data[1],
            'signingKeyFirstPart' => $data[2],
            'nonce' => $data[3],
        ];
    }

    /**
     * @return array{
     *     accessToken: string,
     *     expires: int,
     *     resultMessage: ?string,
     *     resultCode: string
     * }
     */
    public function createToken(array $user): array
    {
        $endpoint = $this->API_URL . $this->endpoints['createToken'];

        $data = [
            'clientId' => $this->client['id'],
            'clientSecret' => $this->client['secret'],
            'username' => $user['name'],
            'password' => $user['password']
        ];

        return $this->sendRequest(
            type: 'POST',
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
    private function sendRequest(string $type, string $endpoint, array|\CURLFile $data, string $messageId, string $accessToken = null)
    {
        if ($type != 'POST' && $type != 'PATCH') {
            throw new \Exception("Invalid request type: " . $type);
        }

        $this->log("  NavM2m:sendRequest Sending {$type} request to {$endpoint}");

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'messageId: ' . $messageId
        ];

        if ($accessToken) {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }

        if ($data instanceof \CURLFile) {
            $requestBody = ['file' => $data];
        }

        $requestBody = json_encode(['requestData' => $data]);

        $this->log('  NavM2m:sendRequest Headers: ' . json_encode($headers));
        $this->log('  NavM2m:sendRequest Request body: ' . json_encode($requestBody));

        $options = [
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $requestBody,
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

        $this->log("  NavM2m:sendRequest Received {$type} response from {$endpoint}: " . $response);
        return json_decode($response, true);
    }

    /**
     * @return array{
     * }
     */
    private function get(string $endpoint, string $messageId, string $accessToken = null)
    {
        $this->log('  NavM2m:get Sending GET request to ' . $endpoint);

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'messageId: ' . $messageId
        ];

        $this->log('  NavM2m:get Headers: ' . json_encode($headers));

        $options = [
            CURLOPT_URL => $endpoint,
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

        $this->log('  NavM2m:get Received GET response from ' . $endpoint . ': ' . $response);
        return json_decode($response, true);
    }

    /**
     * @return array{
     *     token: array{
     *         accessToken: string,
     *         expires: int,
     *         resultMessage: ?string,
     *         resultCode: string
     *     },
     *     signatureKey: string
     * }
     */
    public function activateUser(array $user, string $accessToken)
    {
        $this->log('NavM2m:activateUser Activating user with nonce: ' . $user['nonce']);
        $endpoint = $this->API_URL . $this->endpoints['userNonce'];

        $data = ['nonce' => $user['nonce']];

        $response = $this->sendRequest(
            type: 'POST',
            endpoint: $endpoint,
            data: $data,
            messageId: $this->createMessageId(),
            accessToken: $accessToken
        );

        if (!isset($response['signatureKeySecondPart'])) {
            throw new \Exception('Signature key second part not received');
        }
        $this->log('NavM2m:activateUser Signature key second part received: ' . $response['signatureKeySecondPart']);

        $signatureKey = $user['signingKeyFirstPart'] . $response['signatureKeySecondPart'];
        $this->log('NavM2m:activateUser Signature key: ' . $signatureKey);

        $endpoint = $this->API_URL . $this->endpoints['userActivation'];
        $messageId = $this->createMessageId();
        $data = ['signature' => $this->generateSignature(
            messageId: $messageId,
            data: '',
            signatureKey: $signatureKey
        )];
        $response = $this->sendRequest(
            type: 'POST',
            endpoint: $endpoint,
            data: $data,
            messageId: $messageId,
            accessToken: $accessToken
        );
        $this->log('NavM2m:activateUser Received response from ' . $endpoint . ': ' . json_encode($response));
        $this->log('NavM2m:activateUser Successfull user activation');

        $token = $this->createToken($user);
        $this->log('NavM2m:activateUser New token: ' . json_encode($token));
        return ['token' => $token, 'signatureKey' => $signatureKey];
    }

    /**
     * @return array{
     *     fileId: string,
     *     virusScanResultCode: string,
     *     resultCode: string,
     *     resultMessage: string,
     * }
     */
    public function addFile(string $file, string $signatureKey, string $accessToken)
    {
        $this->log('NavM2m:addFile Adding file: ' . $file);
        if (!file_exists($file)) {
            throw new \Exception("A {$file} fájl nem található!");
        }

        if (!$this->isValidXML($file, $this->xsdFile)) {
            throw new \Exception("A {$file} fájl nem valid bizonylat XML!");
        }

        $fileContent = $this->getXmlContent($file);
        $hash = hash('sha256', $fileContent);
        $signature = $this->generateSignature(
            messageId: $this->createMessageId(),
            data: '',
            signatureKey: $signatureKey
        );

        $curlFile = new \CURLFile($file, 'application/xml', basename($file));

        $endpoint = $this->API_URL . $this->endpoints['addFile'] . '?sha256hash=' . $hash . '&signature=' . $signature;
        $response = $this->sendRequest(
            type: 'POST',
            endpoint: $endpoint,
            data: $curlFile,
            messageId: $this->createMessageId(),
            accessToken: $accessToken
        );

        return $response;
    }

    /**
     * @return array{
     *     retentionTime: string,
     *     resultCode: string,
     *     resultMessage: string,
     * }
     */
    public function getFileStatus(string $fileId, string $accessToken)
    {
        $this->log('NavM2m:getFileStatus for ' . $fileId);
        $endpoint = $this->API_URL . $this->endpoints['getFileStatus'] . '?fileId=' . $fileId;
        return $this->get(
            endpoint: $endpoint,
            messageId: $this->createMessageId(),
            accessToken: $accessToken
        );
    }

    /**
     * @return array{
     *     documentStatus: 'CREATE_DOCUMENT_SUCCESS' | 'UNKNOWN_FILE_ID' | 'FILE_ID_ALREADY_USED' | 'UNSUCCESSFUL_VALIDATION' | 'INVALID_SENDER' | 'INVALID_TAXPAYER' | 'SENDER_HAS_NO_RIGHT' | 'INVALID_DOCUMENT_TYPE' | 'INVALID_DOCUMENT_VERSION' | 'FILE_CONTAINS_VIRUS' | 'INVALID_SIGNATURE' | 'OTHER_ERROR'
     *     errors: string,
     *     resultCode: string,
     *     resultMessage: string,
     * }
     */
    public function createDocument(string $fileId, string $accessToken)
    {
        $this->log('NavM2m:createDocument Creating document for ' . $fileId);
        $endpoint = $this->API_URL . $this->endpoints['createDocument'];
        $signature = $this->generateSignature(
            messageId: $this->createMessageId(),
            data: $fileId,
            signatureKey: $fileId
        );
        $data = [
            'documentField' => $fileId,
            'signature' => $signature
        ];

        $result = $this->sendRequest(
            type: 'POST',
            endpoint: $endpoint,
            data: $data,
            messageId: $this->createMessageId(),
            accessToken: $accessToken
        );
        return $result;
    }

    /**
     * @return array{
     *     arrivalNumber: string,
     *     documentStatus: 'UPDATE_DOCUMENT_SUCCESS' | 'UNKNOWN_FILE_ID' | 'STATUS_CHANGE_NOT_ENABLED' | 'SUBMIT_ERROR' | 'TOO_BIG_KR_FILE' | 'INVALID_SENDER' | 'INVALID_TAXPAYER' | 'SENDER_HAS_NO_RIGHT' | 'INVALID_DOCUMENT_TYPE' | 'INVALID_DOCUMENT_VERSION' | 'INVALID_SIGNATURE' | 'OTHER_ERROR',
     *     resultCode: string,
     *     resultMessage: string,
     * }
     */
    public function updateDocument(string $fileId, string $accessToken)
    {
        $this->log('NavM2m:updateDocument Updating document for ' . $fileId);
        $endpoint = $this->API_URL . $this->endpoints['updateDocument'];
        $signature = $this->generateSignature(
            messageId: $this->createMessageId(),
            data: $fileId,
            signatureKey: $fileId
        );
        $data = [
            'documentField' => $fileId,
            'signature' => $signature,
            "documentStatus" => "UNDER_SUBMIT"
        ];
        return $this->sendRequest(
            type: 'PATCH',
            endpoint: $endpoint,
            data: $data,
            messageId: $this->createMessageId(),
            accessToken: $accessToken
        );
    }

    private function isValidXML($xmlFile, $xsdFile)
    {
        $dom = new \DOMDocument();
        $dom->load($xmlFile);
        return $dom->schemaValidate($xsdFile);
    }

    private function getXmlContent(string $file)
    {
        $xmlContent = file_get_contents($file);
        if ($xmlContent === false) {
            throw new \Exception("Nem sikerült betölteni az XML fájlt!");
        }
        return $xmlContent;
    }


    private function generateSignature(string $messageId, $data, string $signatureKey)
    {
        $timestamp = date("YmdHis", time());
        $signatureData = $messageId . $timestamp . $data . $signatureKey;
        $this->log('  NavM2m:generateSignature Signature data: ' . $signatureData);
        $signatureHash = hash('sha256', $signatureData, true);
        return base64_encode($signatureHash);
    }

    private function log(string $message)
    {
        if ($this->logger) {
            $message = preg_replace('/"accessToken":"[a-zA-Z0-9+\/=]+"/', '"accessToken":"*ACCESS_TOKEN*"', $message);
            $message = preg_replace('/Authorization: Bearer [a-zA-Z0-9+\/\\=]+/', 'Authorization: *AUTHORIZATION_TOKEN*', $message);
            $message = preg_replace('/"clientSecret":"[^"]+/', '"clientSecret":"*CLIENT_SECRET*"', $message);
            $message = preg_replace('/"password":"[^"]+/', '"password":"*PASSWORD*"', $message);
            echo '  👉 ' . $message . "\n";
        }
    }
}
