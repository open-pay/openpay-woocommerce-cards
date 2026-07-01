<?php
use Openpay\Data\Openpay;
use OpenpayCards\Includes\OpenpayClient;
use PHPUnit\Framework\TestCase;
use Openpay\Data\OpenpayApi;

class OpenpayClientTest extends TestCase
{
    protected function tearDown(): void
    {
        // Limpiamos las variables globales después de cada test para evitar contaminación
        unset($_SERVER['HTTP_CLIENT_IP']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testGetOpenpayInstanceSandboxMode()
    {
        // Simulamos la IP para la llamada interna a getClientIp()
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        // Ejecutamos el método con sandbox = true
        // Firma: getOpenpayInstance($sandbox, $merchant_id, $private_key, $country)
        $result = OpenpayClient::getOpenpayInstance(true, 'merchant123', 'key456', 'MX');

        // Verificamos que devuelve una instancia de OpenpayApi
        $this->assertInstanceOf(OpenpayApi::class, $result);

        // Verificamos que el UserAgent se configuró correctamente para México
        $this->assertEquals("Openpay-WOOCMX/v2", Openpay::getUserAgent());

        // Opcional: Si el SDK de Openpay expone el getter de producción, verificamos el modo
        $this->assertFalse(Openpay::getProductionMode());
    }

    public function testGetOpenpayInstanceProductionMode()
    {
        // Simulamos la IP
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        // Ejecutamos el método con sandbox = false y país = CO
        $result = OpenpayClient::getOpenpayInstance(false, 'merchant123', 'key456', 'CO');

        // Verificamos que devuelve una instancia de OpenpayApi
        $this->assertInstanceOf(OpenpayApi::class, $result);

        // Verificamos que el UserAgent cambió dinámicamente al país proporcionado
        $this->assertEquals("Openpay-WOOCCO/v2", Openpay::getUserAgent());

        // Opcional: Verificamos el modo (sandbox = false -> production = true)
        $this->assertTrue(Openpay::getProductionMode());
    }

    public function testGetClientIpFromHttpClientIp()
    {
        // Simulamos que la IP viene desde la cabecera HTTP_CLIENT_IP
        $_SERVER['HTTP_CLIENT_IP'] = '192.168.1.10';

        $reflection = new ReflectionClass(OpenpayClient::class);
        $method = $reflection->getMethod('getClientIp');
        $method->setAccessible(true);

        $ip = $method->invoke(null); // Usamos null porque el método es estático
        $this->assertEquals('192.168.1.10', $ip);
    }

    public function testGetClientIpFromForwardedFor()
    {
        // Simulamos que la IP viene de un Proxy y tiene múltiples valores
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.5, 198.51.100.7';

        $reflection = new ReflectionClass(OpenpayClient::class);
        $method = $reflection->getMethod('getClientIp');
        $method->setAccessible(true);

        $ip = $method->invoke(null);

        // Verificamos que hizo el explode y trajo el primer valor sin espacios
        $this->assertEquals('203.0.113.5', $ip);
    }

    public function testGetClientIpFromRemoteAddr()
    {
        // Simulamos el caso base donde solo tenemos REMOTE_ADDR
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $reflection = new ReflectionClass(OpenpayClient::class);
        $method = $reflection->getMethod('getClientIp');
        $method->setAccessible(true);

        $ip = $method->invoke(null);
        $this->assertEquals('10.0.0.1', $ip);
    }
}