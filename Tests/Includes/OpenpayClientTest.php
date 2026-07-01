<?php

use Openpay\Data\Openpay;
use OpenpayCards\Includes\OpenpayClient;
use PHPUnit\Framework\TestCase;
use Openpay\Data\OpenpayApi;
use ReflectionClass;

class OpenpayClientTest extends TestCase
{
    protected function tearDown(): void
    {
        // Limpiamos variables globales después de cada test
        unset($_SERVER['HTTP_CLIENT_IP']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testGetInstanceReturnsOpenpayApi()
    {
        // Simulamos la IP
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        // Creamos un mock de OpenpayApi
        $mockApi = $this->createMock(OpenpayApi::class);

        // Ejecutamos el método
        $result = OpenpayClient::getInstance('merchant123', 'key456', 'MX', true);

        // Verificamos que devuelve una instancia de OpenpayApi
        $this->assertInstanceOf(OpenpayApi::class, $result);

        // Verificamos que el UserAgent se configuró correctamente
        $this->assertEquals("Openpay-WOOCMX/v2", Openpay::getUserAgent());
    }

    public function testGetClientIpFromHttpClientIp()
    {
        $_SERVER['HTTP_CLIENT_IP'] = '192.168.1.10';

        $reflection = new ReflectionClass(OpenpayClient::class);
        $method = $reflection->getMethod('getClientIp');
        $method->setAccessible(true);

        $ip = $method->invoke(null); // null porque es estático
        $this->assertEquals('192.168.1.10', $ip);
    }

    public function testGetClientIpFromForwardedFor()
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.5, 198.51.100.7';

        $reflection = new ReflectionClass(OpenpayClient::class);
        $method = $reflection->getMethod('getClientIp');
        $method->setAccessible(true);

        $ip = $method->invoke(null);
        $this->assertEquals('203.0.113.5', $ip);
    }

    public function testGetClientIpFromRemoteAddr()
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $reflection = new ReflectionClass(OpenpayClient::class);
        $method = $reflection->getMethod('getClientIp');
        $method->setAccessible(true);

        $ip = $method->invoke(null);
        $this->assertEquals('10.0.0.1', $ip);
    }
}