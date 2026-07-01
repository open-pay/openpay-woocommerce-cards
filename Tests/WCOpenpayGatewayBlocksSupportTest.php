<?php
namespace OpenpayCards\Tests;

use PHPUnit\Framework\TestCase;
use WC_Openpay_Gateway_Blocks_Support;
use stdClass;

if (!class_exists('WCOpenpayGatewayBlocksSupportTest')) {

    /**
     * @covers \WC_Openpay_Gateway_Blocks_Support
     */
    class WCOpenpayGatewayBlocksSupportTest extends \WP_UnitTestCase
    {
        private $temp_asset_file;

        public function setUp(): void
        {
            parent::setUp();
            $GLOBALS['mock_wp_options'] = [];
            $GLOBALS['mock_user_logged_in'] = false;

            $this->temp_asset_file = sys_get_temp_dir() . '/index.asset.php';
            file_put_contents($this->temp_asset_file, "<?php return ['version' => '1.0', 'dependencies' => []];");
            $GLOBALS['mock_plugin_dir_path'] = sys_get_temp_dir() . '/';
        }

        public function tearDown(): void
        {
            parent::tearDown();
            unset($GLOBALS['mock_wp_options']);
            unset($GLOBALS['mock_plugin_dir_path']);
            unset($GLOBALS['mock_user_logged_in']);
            if (file_exists($this->temp_asset_file)) {
                unlink($this->temp_asset_file);
            }
        }

        public function testInitializeRegistersCallbackAndRunsAction()
        {
            // 1. Seteamos la opción en la BD simulada antes de instanciar e inicializar el gateway
            $GLOBALS['mock_wp_options']['woocommerce_wc_openpay_gateway_settings'] = ['enabled' => 'yes'];

            $gateway = new WC_Openpay_Gateway_Blocks_Support();
            $gateway->initialize();

            // 2. Simulamos el Contexto del Checkout de bloques con AMBAS llaves requeridas por producción
            $context = new stdClass();
            $context->payment_method = 'wc-openpay-gateway';
            $context->payment_data = [
                'myGatewayCustomData' => 'custom_data_payload_test', // <-- AGREGAR ESTA LÍNEA
                'openpayHolderName'   => 'Juan Rulfo'
            ];
            $result = new stdClass();

            // 3. Capturamos el var_dump de la función interna mediante un buffer de salida
            ob_start();
            do_action('woocommerce_rest_checkout_process_payment_with_context', $context, $result);
            $output = ob_get_clean();

            // Verificamos que el hook se haya ejecutado procesando los datos correctamente
            $this->assertStringContainsString('Juan Rulfo', $output);
        }

        public function testIsActiveStatusVariations()
        {
            $gateway = new WC_Openpay_Gateway_Blocks_Support();
            $refClass = new \ReflectionClass($gateway);
            $prop = $refClass->getProperty('settings');
            $prop->setAccessible(true);

            // Activo
            $prop->setValue($gateway, ['enabled' => 'yes']);
            $this->assertTrue($gateway->is_active());

            // Inactivo
            $prop->setValue($gateway, ['enabled' => 'no']);
            $this->assertFalse($gateway->is_active());
        }

        public function testGetPaymentMethodScriptHandles()
        {
            $gateway = new WC_Openpay_Gateway_Blocks_Support();
            $handles = $gateway->get_payment_method_script_handles();
            $this->assertEquals(['wc-openpay-gateway-blocks-integration'], $handles);
        }

        // =====================================================================
        // ESCENARIO PERFECTO: Mocks directos sin colisiones globales
        // =====================================================================
        public function testGetPaymentMethodDataWithFullConstructorMocks()
        {
            // 1. Mocks de los servicios auxiliares
            $mockIVA = $this->createMock(\OpenpayCards\Services\PaymentSettings\OpenpayIVA::class);
            $mockIVA->method('getTotalIVA')->willReturn(15.50);

            $mockCards = $this->createMock(\OpenpayCards\Services\OpenpayCardService::class);
            $mockCards->method('getCreditCardList')->willReturn(['visa_mock']);

            $mockInstallments = $this->createMock(\OpenpayCards\Services\PaymentSettings\OpenpayInstallments::class);
            $mockInstallments->method('getInstallments')->willReturn(['12_msi']);

            // 2. Mock de la Pasarela Base (Evita errores de lectura en BD o propiedades vacías)
            $mockGatewayBase = $this->getMockBuilder(\WC_Openpay_Gateway::class)
                ->disableOriginalConstructor()
                ->getMock();

            // Asignamos las propiedades dinámicas esperadas al objeto Mock
            $mockGatewayBase->merchant_id = 'merchant_phpunit_999';
            $mockGatewayBase->public_key  = 'pk_phpunit_999';
            $mockGatewayBase->country     = 'PE';

            // 3. Pasamos todo al constructor de forma ordenada
            $gateway = new WC_Openpay_Gateway_Blocks_Support($mockIVA, $mockCards, $mockInstallments, $mockGatewayBase);

            // Simulamos la respuesta de get_setting() mediante Reflection
            $refClass = new \ReflectionClass($gateway);
            $prop = $refClass->getProperty('settings');
            $prop->setAccessible(true);
            $prop->setValue($gateway, [
                'sandbox' => 'no',
                'country' => 'PE',
                'card_points' => 'yes',
                'save_card_mode' => 'embed'
            ]);

            $data = $gateway->get_payment_method_data();

            // 4. Aserciones exactas contra los MockObjects independientes
            $this->assertEquals('merchant_phpunit_999', $data['merchantId']);
            $this->assertEquals('pk_phpunit_999', $data['publicKey']);
            $this->assertEquals('PE', $data['country']);
            $this->assertEquals('https://api.openpay.pe/v1', $data['openpayAPI']);
            $this->assertEquals(15.50, $data['iva']);
            $this->assertEquals(['12_msi'], $data['installments']);
            $this->assertEquals(['visa_mock'], $data['savedCardsList']);
        }
    }
}