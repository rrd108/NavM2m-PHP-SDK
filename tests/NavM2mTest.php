<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\BeforeClass;
use Rrd108\NavM2m\NavM2m;

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

    /*#[Test]
    public function createTokenReturnsExpectedResponse(): void
    {
        $user = [
            'name' => 'testuser',
            'password' => 'testpass'
        ];

        $navM2mMock = $this->getMockBuilder(NavM2m::class)
            ->setConstructorArgs(['sandbox', self::$client])
            ->onlyMethods(['sendRequest'])
            ->getMock();

        $expectedResponse = [
            'accessToken' => 'test-token',
            'expires' => 3600,
            'resultMessage' => null,
            'resultCode' => 'TOKEN_CREATION_SUCCESSFUL'
        ];

        $navM2mMock->expects($this->once())
            ->method('sendRequest')
            ->willReturn($expectedResponse);

        $result = $navM2mMock->createToken($user);
        $this->assertEquals($expectedResponse, $result);
    }*/

    /*#[Test]
    public function addFileHandlesValidFileCorrectly(): void
    {
        $tempXmlFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempXmlFile, '<?xml version="1.0"?><root></root>');

        $navM2mMock = $this->getMockBuilder(NavM2m::class)
            ->setConstructorArgs(['sandbox', self::$client])
            ->onlyMethods(['sendRequest', 'isValidXML'])
            ->getMock();

        $expectedResponse = [
            'fileId' => 'test-file-id',
            'virusScanResultCode' => 'PASSED',
            'resultCode' => 'UPLOAD_SUCCESS',
            'resultMessage' => 'Success',
            'correlationId' => 'test-correlation-id'
        ];

        $navM2mMock->expects($this->once())
            ->method('isValidXML')
            ->willReturn(true);

        $navM2mMock->expects($this->once())
            ->method('sendRequest')
            ->willReturn($expectedResponse);

        $result = $navM2mMock->addFile(
            $tempXmlFile,
            'test-signature-key',
            'test-access-token'
        );

        $this->assertEquals($expectedResponse, $result);
        unlink($tempXmlFile);
    }*/

    #[Test]
    public function addFileThrowsExceptionForNonExistentFile(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('A non-existent-file.xml fájl nem található!');

        self::$navM2m->addFile(
            'non-existent-file.xml',
            'test-signature-key',
            'test-access-token'
        );
    }

    /*#[Test]
    public function getFileStatusReturnsExpectedResponse(): void
    {
        $navM2mMock = $this->getMockBuilder(NavM2m::class)
            ->setConstructorArgs(['sandbox', self::$client])
            ->onlyMethods(['get'])
            ->getMock();

        $expectedResponse = [
            'retentionTime' => '2024-01-01',
            'resultCode' => 'PASSED',
            'resultMessage' => 'Success'
        ];

        $navM2mMock->expects($this->once())
            ->method('get')
            ->willReturn($expectedResponse);

        $result = $navM2mMock->getFileStatus('test-file-id', 'test-access-token');
        $this->assertEquals($expectedResponse, $result);
    }*/
}
