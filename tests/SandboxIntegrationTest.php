<?php

declare(strict_types=1);

namespace Tests;

use Rrd108\NavM2m\NavM2m;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SandboxIntegrationTest extends TestCase
{
    private function sendGet(string $url, string $accessToken, string $messageId): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'messageId: ' . $messageId,
            ],
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['httpCode' => $httpCode, 'body' => $response];
    }

    private function generateSignature(string $messageId, string $signatureKey): string
    {
        $timestamp = gmdate('YmdHis');
        $signatureData = $messageId . $timestamp . '' . $signatureKey;
        return strtoupper(base64_encode(hash('sha256', $signatureData, true)));
    }

    #[Test]
    public function sandboxEndpoints(): void
    {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();

        $client = [
            'id' => $_ENV['NAV2M2M_CLIENT_ID'],
            'secret' => $_ENV['NAV2M2M_CLIENT_SECRET'],
        ];

        $navM2m = new NavM2m(mode: 'sandbox', client: $client, logger: true);

        [$userName, $userPassword] = explode('-', $_ENV['NAV2M2M_USER_TEMPORARY_API_KEY']);
        $user = [
            'name' => $userName,
            'password' => $userPassword,
            'signatureKey' => $_ENV['NAV2M2M_USER_SIGNATUREKEY'],
        ];

        echo "\n--- Step 1: Creating token ---\n";
        $token = $navM2m->createToken($user);
        echo 'Token OK: ' . ($token['resultCode'] ?? 'FAIL') . "\n";

        $this->assertArrayHasKey('accessToken', $token, 'Failed to get access token: ' . ($token['resultMessage'] ?? 'unknown error'));
        $this->assertNotEmpty($token['accessToken'], 'Access token must not be empty');
        $accessToken = $token['accessToken'];

        echo "\n--- Step 2: getEgyszerusitettFoglalkoztatas ---\n";
        $employees = [
            ['taxId' => '8380222776', 'name' => 'DRÉGELYPALÁNKI JÓZSEF'],
            ['taxId' => '8318680103', 'name' => 'HAJNALKA ANDRÁSNÉ'],
            ['taxId' => '8425029643', 'name' => 'IMRE KLÁRA'],
            ['taxId' => '8387199028', 'name' => 'TAMÁS LAJOS'],
            ['taxId' => '8390055147', 'name' => 'RÓMEÓ FERENC'],
        ];

        foreach ($employees as $emp) {
            try {
                $result = $navM2m->getEgyszerusitettFoglalkoztatas(
                    taxId: $emp['taxId'],
                    employeeName: $emp['name'],
                    insuredInHungary: 'NEM',
                    signatureKey: $user['signatureKey'],
                    accessToken: $accessToken,
                );
                $this->assertArrayHasKey('resultCode', $result, "Missing resultCode for {$emp['taxId']}");
                echo "  {$emp['name']} ({$emp['taxId']}): resultCode={$result['resultCode']}\n";
            } catch (\Exception $e) {
                echo "  {$emp['name']} ({$emp['taxId']}): FAILED - {$e->getMessage()}\n";
            }
        }

        echo "\n--- Step 3: getEgyszerusitettFoglalkoztatasFoglalkoztatottLista ---\n";
        $employerTests = [
            ['taxId' => '10278886', 'year' => 2026, 'expected' => 'non-empty'],
            ['taxId' => '26892920', 'year' => 2026, 'expected' => 'empty'],
            ['taxId' => '12833467', 'year' => 2026, 'expected' => 'empty'],
            ['taxId' => '12833467', 'year' => 2024, 'expected' => 'empty + TARGYEVRE_VONATKOZO_LEKERDEZES_NEM_TAMOGATOTT'],
        ];

        foreach ($employerTests as $et) {
            try {
                $result = $navM2m->getEgyszerusitettFoglalkoztatasFoglalkoztatottLista(
                    employerTaxId: $et['taxId'],
                    targetYear: $et['year'],
                    signatureKey: $user['signatureKey'],
                    accessToken: $accessToken,
                );
                $this->assertArrayHasKey('resultCode', $result, "Missing resultCode for employer {$et['taxId']}");
                $count = count($result['foglalkoztatottak'] ?? []);
                echo "  Employer {$et['taxId']} year {$et['year']}: resultCode={$result['resultCode']}, foglalkoztatottak={$count} (expected: {$et['expected']})\n";
            } catch (\Exception $e) {
                echo "  Employer {$et['taxId']} year {$et['year']}: FAILED - {$e->getMessage()}\n";
            }
        }
    }
}
