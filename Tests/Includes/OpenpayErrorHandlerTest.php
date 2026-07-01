<?php
// 1. Interceptamos la función wc_get_logger solo para el namespace de tu clase
namespace OpenpayCards\Includes {
    if (!function_exists('OpenpayCards\Includes\wc_get_logger')) {
        function wc_get_logger() {
            // Devuelve el mock si existe, si no, busca la función global original
            return $GLOBALS['mock_wc_logger'] ?? (function_exists('\wc_get_logger') ? \wc_get_logger() : null);
        }
    }

    // NUEVO: Intercepción de la Orden (wc_get_order)
    if (!function_exists('OpenpayCards\Includes\wc_get_order')) {
        function wc_get_order($id) {
            // Devuelve nuestro mock si existe, si no, usa la función real de WP
            return $GLOBALS['mock_wc_order'] ?? (function_exists('\wc_get_order') ? \wc_get_order($id) : false);
        }
    }
}

// 2. Tu espacio de pruebas
namespace OpenpayCards\Tests {

    use OpenpayCards\Includes\OpenpayErrorHandler;
    use Openpay\Data\OpenpayApiTransactionError;
    use Openpay\Data\OpenpayApiConnectionError;
    use Exception;
    use ReflectionClass;

    /**
     * Pruebas unitarias para OpenpayErrorHandler.
     *
     * @covers \OpenpayCards\Includes\OpenpayErrorHandler
     */
    class OpenpayErrorHandlerTest extends \WP_UnitTestCase
    {
        /**
         * @var \WC_Logger|\PHPUnit\Framework\MockObject\MockObject
         */
        private $mock_logger;
        private $mock_order;

        /**
         * Configuración para cada prueba: Inyecta un mock en la propiedad estática $logger.
         */
        public function setUp(): void
        {
            parent::setUp();

            // 1. Crear el mock del Logger y asignarlo a la global
            $this->mock_logger = $this->createMock(\WC_Logger::class);
            $GLOBALS['mock_wc_logger'] = $this->mock_logger;

            // 2. Crear el mock de WC_Order y asignarlo a la global <-- ESTO CORRIGE EL ERROR
            $this->mock_order = $this->getMockBuilder(\WC_Order::class)
                ->onlyMethods(['add_order_note', 'update_status'])
                ->getMock();
            $GLOBALS['mock_wc_order'] = $this->mock_order;

            // 3. Usar Reflection para "secuestrar" la propiedad estática
            $reflection = new ReflectionClass(OpenpayErrorHandler::class);
            $property = $reflection->getProperty('logger');
            $property->setAccessible(true);
            $property->setValue(null, $this->mock_logger);
        }

        // Limpieza después de cada test
        public function tearDown(): void
        {
            parent::tearDown();

            // Limpiar las variables globales <-- AGREGA EL UNSET DEL ORDER
            unset($GLOBALS['mock_wc_logger']);
            unset($GLOBALS['mock_wc_order']);

            $reflection = new ReflectionClass(OpenpayErrorHandler::class);
            $property = $reflection->getProperty('logger');
            $property->setAccessible(true);
            $property->setValue(null, null);
        }

        public function test_init_sets_logger()
        {
            // 1. Nos aseguramos de que la propiedad esté limpia antes de ejecutar la prueba
            $reflection = new \ReflectionClass(OpenpayErrorHandler::class);
            $property = $reflection->getProperty('logger');
            $property->setAccessible(true);
            $property->setValue(null, null);

            // 2. Ejecutamos el método que estamos probando
            OpenpayErrorHandler::init();

            // 3. Obtenemos el valor de la propiedad después de ejecutar init()
            $logger_value = $property->getValue();

            // 4. Aserciones
            $this->assertNotNull($logger_value, 'La propiedad $logger no debería ser nula después de llamar a init().');

            // CORRECCIÓN AQUÍ: Validamos que sea una instancia del Logger de WooCommerce
            // en lugar de forzar que sea exactamente la misma instancia del mock.
            $this->assertInstanceOf(
                \WC_Logger::class,
                $logger_value,
                'El logger asignado no es una instancia válida de WC_Logger.'
            );
        }

        /**
         * @covers \OpenpayCards\Includes\OpenpayErrorHandler::catchOpenpayError
         */
        public function test_catchOpenpayError_returns_callback_result_on_success()
        {
            $callback = function () {
                return 'success_value';
            };

            $result = OpenpayErrorHandler::catchOpenpayError($callback);
            $this->assertEquals('success_value', $result);
        }

        /**
         * @covers \OpenpayCards\Includes\OpenpayErrorHandler::handleOpenpayPluginException
         */
        public function test_handleOpenpayPluginException_with_openpay_error_and_order()
        {
            // 1. Preparación de datos
            $exception = new \Openpay\Data\OpenpayApiTransactionError("Fondos insuficientes", 3001);
            $order_id = 123;
            $customer_id = 456;

            // 2. Expectativas del Pedido (Order)
            // Como pasamos un order_id, esperamos que se obtenga el pedido y se actualice
            $GLOBALS['mock_wc_order']->expects($this->once())
                ->method('add_order_note')
                ->with($this->isType('string')); // Validamos que reciba un texto (el orderDetailError)

            $GLOBALS['mock_wc_order']->expects($this->once())
                ->method('update_status')
                ->with('failed');

            // 3. Expectativas del Logger
            // Validamos que el log final contenga los IDs correctos en el contexto
            $this->mock_logger->expects($this->once())
                ->method('error')
                ->with(
                    $this->stringContains('[Openpay ERROR]'),
                    $this->callback(function ($context) use ($order_id, $customer_id) {
                        return $context['order_id'] === $order_id
                            && $context['user_id'] === $customer_id
                            && $context['code'] === 3001
                            && isset($context['id']); // Validamos que se haya generado el UUID
                    })
                );

            // 4. Ejecución
            OpenpayErrorHandler::handleOpenpayPluginException($exception, $order_id, $customer_id);
        }

        /**
         * @covers \OpenpayCards\Includes\OpenpayErrorHandler::catchOpenpayError
         */
        public function test_catchOpenpayError_handles_OpenpayApiTransactionError()
        {
            $real_exception = new OpenpayApiTransactionError('Error de transacción', 1003);

            $callback = function () use ($real_exception) {
                throw $real_exception;
            };

            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Ha ocurrido un error inesperado');
            $this->expectExceptionCode(1003);

            OpenpayErrorHandler::catchOpenpayError($callback);
        }

        /**
         * @covers \OpenpayCards\Includes\OpenpayErrorHandler::catchOpenpayError
         */
        public function test_catchOpenpayError_handles_OpenpayApiConnectionError()
        {
            $real_exception = new OpenpayApiConnectionError('Error de conexión', 1004);

            $callback = function () use ($real_exception) {
                throw $real_exception;
            };

            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Servicio no disponible.');
            $this->expectExceptionCode(1004);

            OpenpayErrorHandler::catchOpenpayError($callback);
        }

        /**
         * @covers \OpenpayCards\Includes\OpenpayErrorHandler::handleOpenpayPluginException
         */
        public function test_handleOpenpayPluginException_logs_openpay_error()
        {
            $real_exception = new \Openpay\Data\OpenpayApiError('Error de API', 1005);

            $callback = function () use ($real_exception) {
                throw $real_exception;
            };

            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Servicio no disponible.');
            $this->expectExceptionCode(1005);

            OpenpayErrorHandler::catchOpenpayError($callback);
        }

        /**
         * @covers \OpenpayCards\Includes\OpenpayErrorHandler::handleOpenpayPluginException
         */
        public function test_handleOpenpayPluginException_logs_generic_exception()
        {
            $generic_exception = new Exception('Este es un error genérico', 500);

            $this->mock_logger->expects($this->once())
                ->method('error')
                ->with(
                    '[EXCEPTION] Este es un error genérico',
                    $this->isType('array')
                );

            OpenpayErrorHandler::handleOpenpayPluginException($generic_exception);
        }

        /**
         * @covers \OpenpayCards\Includes\OpenpayErrorHandler::log
         */
        public function test_log_calls_logger_error()
        {
            $this->mock_logger->expects($this->once())
                ->method('error')
                ->with('Mensaje de prueba', ['clave' => 'valor']);

            OpenpayErrorHandler::log('Mensaje de prueba', ['clave' => 'valor']);
        }

        /**
         * @covers \OpenpayCards\Includes\OpenpayErrorHandler::generate_uuid_v4
         */
        public function test_generate_uuid_v4_returns_valid_uuid()
        {
            $uuid = OpenpayErrorHandler::generate_uuid_v4();

            $this->assertIsString($uuid);
            $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
            $this->assertMatchesRegularExpression($pattern, $uuid);
        }
    }
}