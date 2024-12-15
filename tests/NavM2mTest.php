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
    public function isValidXML(): void
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
}
