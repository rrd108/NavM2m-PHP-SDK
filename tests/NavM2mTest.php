<?php

namespace Tests;

use Rrd108\NavM2m\NavM2m;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestStatus\Warning;
use PHPUnit\Framework\Attributes\BeforeClass;

class NavM2mTest extends TestCase
{
    private static NavM2m $navM2m;
    private static array $client;

    #[BeforeClass]
    public static function setUpNavM2m(): void
    {
        self::$client = [
            'id' => 'test-client-id',
            'secret' => 'test-client-secret'
        ];
        self::$navM2m = new NavM2m('sandbox', self::$client);
    }

    #[Test]
    public function constructorThrowsExceptionWithInvalidClient(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Client ID, client secret, username and password are required');

        new NavM2m('sandbox', ['id' => '', 'secret' => '']);
    }

    #[Test]
    public function getInactiveUserReturnsCorrectData(): void
    {
        $temporaryUserApiKey = 'testuser-testpass-signingkey-nonce123';
        $expectedResult = [
            'name' => 'testuser',
            'password' => 'testpass',
            'signingKeyFirstPart' => 'signingkey',
            'nonce' => 'nonce123'
        ];

        $result = self::$navM2m->getInactiveUser($temporaryUserApiKey);
        $this->assertEquals($expectedResult, $result);
    }

    #[Test]
    public function testIsValidXML(): void
    {
        // Create a test double to access protected method
        $navM2mTest = new class('sandbox', self::$client) extends NavM2m {
            public function isValidXML($xml, $schemaFile): bool
            {
                return parent::isValidXML($xml, $schemaFile);
            }
        };

        $fixturesPath = __DIR__ . '/fixtures/';

        $this->assertTrue($navM2mTest->isValidXML($fixturesPath . '09teszt.xml', $fixturesPath . 'schema.xsd'));
        $this->assertFalse($navM2mTest->isValidXML($fixturesPath . 'invalid.xml', $fixturesPath . 'schema.xsd'));
    }

    #[Test]
    public function testGenerateSignatureWithEmptyData(): void
    {
        $messageId = '123e4567-e89b-12d3-a456-426614174000';
        $data = '';
        $signatureKey = 'test_key';
        $timestamp = '20240101000000';
        $expectedHash = 'E6E2803E2B4D68842267DDA92D3F4B53BC985813A9FE21E0C02F4774E0D82749';

        // Create a mock object that returns fixed timestamp
        $navM2mMock = $this->getMockBuilder(NavM2m::class)
            ->setConstructorArgs(['sandbox', self::$client])
            ->onlyMethods(['getCurrentUTCTimestamp'])
            ->getMock();

        $navM2mMock->method('getCurrentUTCTimestamp')
            ->willReturn($timestamp);

        // Use ReflectionMethod to access protected method
        $reflection = new \ReflectionMethod(NavM2m::class, 'generateSignature');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($navM2mMock, $messageId, $data, $signatureKey, 'text');
        $this->assertEquals($expectedHash, $result);

        $expectedHash = '5UKAPITNAIQIZ92PLT9LU7YYWBOP/IHGWC9HDODYJ0K=';
        $result = $reflection->invoke($navM2mMock, $messageId, $data, $signatureKey, 'binary');
        $this->assertEquals($expectedHash, $result);
    }
}
