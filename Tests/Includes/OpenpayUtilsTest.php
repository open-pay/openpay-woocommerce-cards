<?php


// 1. Interceptamos dependencias de WP y cURL en el namespace de tu clase
namespace OpenpayCards\Includes {

    // Mock de WooCommerce Logger
    if (!function_exists('OpenpayCards\Includes\wc_get_logger')) {
        function wc_get_logger()
        {
            return $GLOBALS['mock_wc_logger'] ?? (function_exists('\wc_get_logger') ? \wc_get_logger() : null);
        }
    }

    // Mocks de funciones cURL nativas
    if (!function_exists('OpenpayCards\Includes\curl_init')) {
        function curl_init($url = null)
        {
            return 'mock_curl_handle';
        }

        function curl_setopt($ch, $option, $value)
        {
            $GLOBALS['mock_curl_options'][$option] = $value;
            return true;
        }

        function curl_exec($ch)
        {
            return $GLOBALS['mock_curl_result'] ?? json_encode(['status' => 'success']);
        }

        function curl_errno($ch)
        {
            return $GLOBALS['mock_curl_errno'] ?? 0;
        }

        function curl_error($ch)
        {
            return $GLOBALS['mock_curl_error'] ?? '';
        }

        function curl_getinfo($ch)
        {
            return $GLOBALS['mock_curl_info'] ?? ['http_code' => 200, 'url' => 'https://mock.url'];
        }

        function curl_close($ch)
        {
            return true;
        }
    }
}

// 2. Tu espacio de pruebas
namespace OpenpayCards\Tests {

    use PHPUnit\Framework\TestCase;
    use OpenpayCards\Includes\OpenpayUtils;

    /**
     * Pruebas unitarias para OpenpayUtils.
     *
     * @covers \OpenpayCards\Includes\OpenpayUtils
     */
    class OpenpayUtilsTest extends \WP_UnitTestCase
    {
        /**
         * @var \WC_Logger|\PHPUnit\Framework\MockObject\MockObject
         */
        private $mock_logger;

        public function setUp(): void
        {
            parent::setUp();

            // Configuramos el mock del logger
            $this->mock_logger = $this->createMock(\WC_Logger::class);
            $GLOBALS['mock_wc_logger'] = $this->mock_logger;

            // Limpiamos el estado global de cURL antes de cada prueba
            $GLOBALS['mock_curl_options'] = [];
            $GLOBALS['mock_curl_result'] = '{"status": "ok"}';
            $GLOBALS['mock_curl_errno'] = 0;
            $GLOBALS['mock_curl_error'] = '';
            $GLOBALS['mock_curl_info'] = ['http_code' => 200, 'url' => 'https://api.openpay.mx'];
        }

        public function tearDown(): void
        {
            parent::tearDown();

            unset($GLOBALS['mock_wc_logger']);
            unset($GLOBALS['mock_curl_options']);
            unset($GLOBALS['mock_curl_result']);
            unset($GLOBALS['mock_curl_errno']);
            unset($GLOBALS['mock_curl_error']);
            unset($GLOBALS['mock_curl_info']);
        }

        // --- PRUEBAS PARA getUrlScripts() ---

        public function test_getUrlScripts_mx()
        {
            $result = OpenpayUtils::getUrlScripts('MX');
            $this->assertEquals('mx_openpay_js', $result['openpay_js']['tag']);
            $this->assertEquals('assets/js/mx-openpay.v1.min.js', $result['openpay_js']['script']);
            $this->assertEquals('https://openpay.s3.amazonaws.com/openpay-data.v1.min.js', $result['openpay_fraud_js']);
        }

        public function test_getUrlScripts_co()
        {
            $result = OpenpayUtils::getUrlScripts('CO');
            $this->assertEquals('co_openpay_js', $result['openpay_js']['tag']);
            $this->assertEquals('https://resources.openpay.co/openpay-data.v1.min.js', $result['openpay_fraud_js']);
        }

        public function test_getUrlScripts_pe()
        {
            $result = OpenpayUtils::getUrlScripts('PE');
            $this->assertEquals('pe_openpay_js', $result['openpay_js']['tag']);
            $this->assertEquals('https://js.openpay.pe/openpay-data.v1.min.js', $result['openpay_fraud_js']);
        }

        public function test_getUrlScripts_default_returns_false()
        {
            $this->assertFalse(OpenpayUtils::getUrlScripts('US'));
            $this->assertFalse(OpenpayUtils::getUrlScripts(''));
        }

        // --- PRUEBAS PARA getCountryName() ---

        public function test_getCountryName()
        {
            $this->assertEquals('Mexico', OpenpayUtils::getCountryName('MX'));
            $this->assertEquals('Colombia', OpenpayUtils::getCountryName('CO'));
            $this->assertEquals('Peru', OpenpayUtils::getCountryName('PE'));
            $this->assertFalse(OpenpayUtils::getCountryName('US'));
        }

        // --- PRUEBAS PARA isNullOrEmptyString() ---

        public function test_isNullOrEmptyString()
        {
            $this->assertTrue(OpenpayUtils::isNullOrEmptyString(null));
            $this->assertTrue(OpenpayUtils::isNullOrEmptyString(''));
            $this->assertTrue(OpenpayUtils::isNullOrEmptyString('   ')); // Espacios en blanco
            $this->assertFalse(OpenpayUtils::isNullOrEmptyString('Openpay'));
            $this->assertFalse(OpenpayUtils::isNullOrEmptyString('0')); // El cero es válido
        }

        // --- PRUEBAS PARA requestOpenpay() ---

        public function test_requestOpenpay_sandbox_with_params_and_auth()
        {
            // 1. Preparamos datos simulados
            $GLOBALS['mock_curl_result'] = '{"response":"sandbox_success"}';

            // El logger debe ser llamado con información general
            $this->mock_logger->expects($this->exactly(4))->method('info');

            // 2. Ejecutamos (is_sandbox = true)
            $result = OpenpayUtils::requestOpenpay(
                '/charges',
                'MX',
                true,
                'POST',
                ['amount' => 100],
                'sk_test_123'
            );

            // 3. Aserciones
            $this->assertEquals('sandbox_success', $result->response);

            // Validamos que se usó la URL de sandbox correcta (CURLOPT_URL es 10002 en constantes de cURL)
            // Se usa el valor numérico estándar de PHP para CURLOPT_URL
            $this->assertStringContainsString('sandbox-api.openpay.mx', $GLOBALS['mock_curl_options'][CURLOPT_URL]);

            // Validamos que se incluyeron los parámetros (CURLOPT_POSTFIELDS es 10015)
            $this->assertEquals(json_encode(['amount' => 100]), $GLOBALS['mock_curl_options'][CURLOPT_POSTFIELDS]);

            // Validamos las cabeceras (CURLOPT_HTTPHEADER es 10023)
            $headers = $GLOBALS['mock_curl_options'][CURLOPT_HTTPHEADER];
            $this->assertContains('Content-Type:application/json', $headers);
            $this->assertContains('Authorization: Basic c2tfdGVzdF8xMjM6', $headers); // base64 de 'sk_test_123:'
        }

        public function test_requestOpenpay_production_without_params_and_auth()
        {
            // 1. Preparamos datos simulados
            $GLOBALS['mock_curl_result'] = '{"response":"prod_success"}';
            $this->mock_logger->expects($this->atLeastOnce())->method('info');

            // 2. Ejecutamos (is_sandbox = false, params vacíos, auth null)
            $result = OpenpayUtils::requestOpenpay('/customers', 'CO', false);

            // 3. Aserciones
            $this->assertEquals('prod_success', $result->response);

            // Validamos URL de producción
            $this->assertStringContainsString('api.openpay.co', $GLOBALS['mock_curl_options'][CURLOPT_URL]);

            // Validamos que no se enviaron POSTFIELDS ni cabeceras extra si están vacías
            $this->assertArrayNotHasKey(CURLOPT_POSTFIELDS, $GLOBALS['mock_curl_options']);

            // Las cabeceras deben ser un array vacío si no hay auth ni params
            $this->assertEmpty($GLOBALS['mock_curl_options'][CURLOPT_HTTPHEADER]);
        }

        public function test_requestOpenpay_handles_curl_error()
        {
            // 1. Preparamos la respuesta falsa simulando falla de red
            $GLOBALS['mock_curl_result'] = false;
            $GLOBALS['mock_curl_errno'] = 28;
            $GLOBALS['mock_curl_error'] = 'Operation timed out';

            // El logger debe registrar un error específico
            $this->mock_logger->expects($this->once())
                ->method('error')
                ->with('Curl error 28: Operation timed out');

            // 2. Ejecución
            $result = OpenpayUtils::requestOpenpay('/ping', 'PE', true);

            // 3. json_decode(false) devuelve null en PHP, verificamos ese comportamiento
            $this->assertNull($result);
        }
    }
}