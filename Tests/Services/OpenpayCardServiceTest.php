<?php

// 1. Stub de la clase padre (Esto sí va en el namespace global)
namespace {
    if (!class_exists('WC_Openpay_Gateway')) {
        class WC_Openpay_Gateway {
            protected $logger;
            protected $openpay;
            protected $order;
            protected $country;
            protected $sandbox;
            public function __construct() {}
        }
    }
}

// 2. ¡EL TRUCO! Declaramos las funciones de WP dentro del namespace de tu servicio
// Así interceptamos las llamadas antes de que lleguen al core de WordPress.
namespace OpenpayCards\Services {

    if (!function_exists('OpenpayCards\Services\is_user_logged_in')) {
        function is_user_logged_in() {
            return $GLOBALS['mock_is_user_logged_in'] ?? true;
        }
    }

    if (!function_exists('OpenpayCards\Services\get_current_user_id')) {
        function get_current_user_id() {
            return 1;
        }
    }

    if (!function_exists('OpenpayCards\Services\get_user_meta')) {
        function get_user_meta($user_id, $key, $single = false) {
            return $GLOBALS['mock_user_meta'][$key] ?? '';
        }
    }

    if (!function_exists('OpenpayCards\Services\wc_add_notice')) {
        function wc_add_notice($message, $type) {
            $GLOBALS['mock_wc_notice'] = $message;
        }
    }

    if (!function_exists('OpenpayCards\Services\__')) {
        function __($text, $domain = 'default') {
            return $text;
        }
    }
}

// 3. Tus Pruebas Unitarias Normales
namespace OpenpayCards\Tests {

    use PHPUnit\Framework\TestCase;
    use OpenpayCards\Services\OpenpayCardService;
    use Exception;
    use ReflectionClass;

    class DummyOpenpay { public $customers; }
    class DummyCustomers { public function get($id) {} }
    class DummyCustomer { public $cards; }
    class DummyCards {
        public function getList($params) {}
        public function add($data) {}
    }

    /**
     * @covers \OpenpayCards\Services\OpenpayCardService
     */
    class OpenpayCardServiceTest extends \WP_UnitTestCase
    {
        private $mock_logger;
        private $service;
        private $mock_openpay;

        /**
         * Función Helper para modificar propiedades protegidas
         */
        protected function setProtectedProperty($object, $propertyName, $value) {
            $reflection = new ReflectionClass($object);

            while (!$reflection->hasProperty($propertyName) && $reflection->getParentClass()) {
                $reflection = $reflection->getParentClass();
            }

            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $property->setValue($object, $value);
        }

        public function setUp(): void
        {
            parent::setUp();

            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

            $this->mock_logger = $this->getMockBuilder(\stdClass::class)
                ->addMethods(['info', 'error'])
                ->getMock();

            $this->mock_openpay = new DummyOpenpay();
            $this->mock_openpay->customers = $this->createMock(DummyCustomers::class);

            $this->service = new OpenpayCardService();

            $this->setProtectedProperty($this->service, 'logger', $this->mock_logger);
            $this->setProtectedProperty($this->service, 'openpay', $this->mock_openpay);

            // Forzamos los valores simulados
            $GLOBALS['mock_is_user_logged_in'] = true;
            $GLOBALS['mock_user_meta'] = [];

            $GLOBALS['woocommerce'] = new \stdClass();
            $GLOBALS['woocommerce']->add_error = function($msg) {};
        }

        public function tearDown(): void
        {
            parent::tearDown();
            unset($_SERVER['REMOTE_ADDR']);
            unset($GLOBALS['mock_is_user_logged_in']);
            unset($GLOBALS['mock_user_meta']);
            unset($GLOBALS['mock_wc_notice']);
        }

        public function test_getCreditCardList_user_not_logged_in()
        {
            $GLOBALS['mock_is_user_logged_in'] = false;

            $result = $this->service->getCreditCardList();

            $this->assertCount(1, $result);
            $this->assertEquals('new', $result[0]['value']);
        }

        public function test_getCreditCardList_sandbox_empty_customer()
        {
            $this->setProtectedProperty($this->service, 'sandbox', true);
            $GLOBALS['mock_user_meta']['_openpay_customer_test_id'] = '';

            $result = $this->service->getCreditCardList();

            $this->assertCount(1, $result);
            $this->assertEquals('new', $result[0]['value']);
        }

        public function test_getCreditCardList_production_empty_customer()
        {
            $this->setProtectedProperty($this->service, 'sandbox', false);
            $GLOBALS['mock_user_meta']['_openpay_customer_live_id'] = null;

            $result = $this->service->getCreditCardList();

            $this->assertCount(1, $result);
            $this->assertEquals('new', $result[0]['value']);
        }

        public function test_getCreditCardList_success_returns_cards()
        {
            $this->setProtectedProperty($this->service, 'sandbox', false);
            $GLOBALS['mock_user_meta']['_openpay_customer_live_id'] = 'cus_123';

            $card = new \stdClass();
            $card->id = 'card_abc';
            $card->brand = 'visa';
            $card->card_number = '411111XXXXXX1111';

            $mock_cards = $this->createMock(DummyCards::class);
            $mock_cards->method('getList')->willReturn([$card]);

            $mock_customer = new DummyCustomer();
            $mock_customer->cards = $mock_cards;

            $this->mock_openpay->customers->expects($this->once())
                ->method('get')
                ->with('cus_123')
                ->willReturn($mock_customer);

            $result = $this->service->getCreditCardList();

            $this->assertCount(2, $result);
            $this->assertEquals('card_abc', $result[1]['value']);
            $this->assertEquals('VISA 411111XXXXXX1111', $result[1]['name']);
        }

        public function test_getCreditCards_throws_exception()
        {
            $reflection = new ReflectionClass(OpenpayCardService::class);
            $method = $reflection->getMethod('getCreditCards');
            $method->setAccessible(true);

            $mock_cards = $this->createMock(DummyCards::class);
            $mock_cards->method('getList')->willThrowException(new Exception("API Error"));

            $mock_customer = new DummyCustomer();
            $mock_customer->cards = $mock_cards;

            $this->mock_logger->expects($this->once())->method('error');
            $this->expectException(Exception::class);
            $this->expectExceptionMessage("API Error");

            $method->invokeArgs($this->service, [$mock_customer]);
        }

        public function test_validateNewCard_card_already_exists()
        {
            $card = new \stdClass();
            $card->card_number = '41111111XXXX1111';

            $mock_cards = $this->createMock(DummyCards::class);
            $mock_cards->method('getList')->willReturn([$card]);

            $mock_customer = new DummyCustomer();
            $mock_customer->cards = $mock_cards;

            $result = $this->service->validateNewCard($mock_customer, 'tok_123', 'dev_123', '4111111100001111', '1');

            $this->assertFalse($result);
            $this->assertNotNull($GLOBALS['mock_wc_notice']);
        }

        public function test_validateNewCard_creates_frequent_card_for_pe()
        {
            $this->setProtectedProperty($this->service, 'country', 'PE');

            $mock_cards = $this->createMock(DummyCards::class);
            $mock_cards->method('getList')->willReturn([]);

            $new_card = new \stdClass();
            $new_card->id = 'card_new_123';

            $mock_cards->expects($this->once())
                ->method('add')
                ->with($this->callback(function($data) {
                    return isset($data['register_frequent']) && $data['register_frequent'] === true;
                }))
                ->willReturn($new_card);

            $mock_customer = new DummyCustomer();
            $mock_customer->cards = $mock_cards;

            $result = $this->service->validateNewCard($mock_customer, 'tok_123', 'dev_123', '4111111100001111', '2');

            $this->assertEquals('card_new_123', $result);
        }

        public function test_validateNewCard_creates_standard_card_for_other_countries()
        {
            $this->setProtectedProperty($this->service, 'country', 'MX');

            $mock_cards = $this->createMock(DummyCards::class);
            $mock_cards->method('getList')->willReturn([]);

            $new_card = new \stdClass();
            $new_card->id = 'card_new_456';

            $mock_cards->expects($this->once())
                ->method('add')
                ->with($this->callback(function($data) {
                    return !isset($data['register_frequent']);
                }))
                ->willReturn($new_card);

            $mock_customer = new DummyCustomer();
            $mock_customer->cards = $mock_cards;

            $result = $this->service->validateNewCard($mock_customer, 'tok_123', 'dev_123', '5555555500001111', '2');

            $this->assertEquals('card_new_456', $result);
        }

        public function test_createCreditCard_throws_exception()
        {
            $reflection = new ReflectionClass(OpenpayCardService::class);
            $method = $reflection->getMethod('createCreditCard');
            $method->setAccessible(true);

            $mock_cards = $this->createMock(DummyCards::class);
            $mock_cards->method('add')->willThrowException(new Exception("Creation Error"));

            $mock_customer = new DummyCustomer();
            $mock_customer->cards = $mock_cards;

            $this->mock_logger->expects($this->once())->method('error');
            $this->expectException(Exception::class);
            $this->expectExceptionMessage("Creation Error");

            $method->invokeArgs($this->service, [$mock_customer, []]);
        }
    }
}