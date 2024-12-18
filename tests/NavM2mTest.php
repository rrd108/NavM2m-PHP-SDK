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
        $schemaFile = __DIR__ . '/../resources/schema.xsd';

        $this->assertTrue($navM2mTest->isValidXML($fixturesPath . 'valid.xml', $schemaFile));
        $this->assertFalse($navM2mTest->isValidXML($fixturesPath . 'invalid.xml', $schemaFile));
    }

    #[Test]
    public function testGenerateSignature(): void
    {
        $messageId = '123e4567-e89b-12d3-a456-426614174000';
        $data = '';
        $signatureKey = 'test_key';
        $timestamp = '20240101000000';
        $expectedHash = '5UKAPITNAIQIZ92PLT9LU7YYWBOP/IHGWC9HDODYJ0K=';

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

        $result = $reflection->invoke($navM2mMock, $messageId, $data, $signatureKey);
        $this->assertEquals($expectedHash, $result);

        // example from the docs
        /*
        $messageId = '7eae9ecf-f735-4a4f-aa49-e85ea411a313';
        $timestamp = '20240510123847';
        $data = '26549118-0ddc-4e30-81bc-eaddd6f54b21';
        $signatureKey = 'FA12BC4567CA12BC4588';
        $expectedHash = '2e81f124c0ee66be1e4cca1af72eb198b1a1c02ad1dffa0943a4fa8db0e440e8';
        $result = $reflection->invoke($navM2mMock, $messageId, $data, $signatureKey);
        $this->assertEquals($expectedHash, $result);
        */
    }
}
