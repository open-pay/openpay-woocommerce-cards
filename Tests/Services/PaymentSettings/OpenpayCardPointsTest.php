<?php

// =========================================================================
// 1. STUBS DE DEPENDENCIAS (WooCommerce Base Gateway)
// =========================================================================

// =========================================================================
// 2. CLASE DE PRUEBAS UNITARIAS
// =========================================================================

namespace OpenpayCards\Tests {

    use PHPUnit\Framework\TestCase;
    use OpenpayCards\Services\PaymentSettings\OpenpayCardPoints;
    use ReflectionClass;

    /**
     * @covers \OpenpayCards\Services\PaymentSettings\OpenpayCardPoints
     */
    class OpenpayCardPointsTest extends \WP_UnitTestCase
    {
        private $cardPointsService;

        /**
         * Helper para modificar propiedades protegidas/privadas mediante Reflection
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

            // Blindaje contra entornos de consola CLI de PHPUnit
            if (!isset($_SERVER['REMOTE_ADDR'])) {
                $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
            }

            // Instancia del objeto bajo prueba
            $this->cardPointsService = new OpenpayCardPoints();
        }

        // =====================================================================
        // CASO 1: El país es MX y se pasa una confirmación válida (Camino Exitoso)
        // =====================================================================
        public function test_dataValidationAssignement_assigns_points_when_country_is_mx_and_param_is_set()
        {
            // Seteamos la propiedad protegida country a 'MX'
            $this->setProtectedProperty($this->cardPointsService, 'country', 'MX');

            $charge_request = ['amount' => 500, 'currency' => 'MXN'];
            $openpay_card_points_confirm = 'true';

            // Ejecutamos la asignación (pasa por referencia)
            $this->cardPointsService->dataValidationAssignement($charge_request, $openpay_card_points_confirm);

            // Aserciones
            $this->assertArrayHasKey('use_card_points', $charge_request);
            $this->assertEquals('true', $charge_request['use_card_points']);
        }

        // =====================================================================
        // CASO 2: El país es MX pero el parámetro es NULL (No debe asignar la llave)
        // =====================================================================
        public function test_dataValidationAssignement_does_nothing_if_param_is_null_even_in_mx()
        {
            $this->setProtectedProperty($this->cardPointsService, 'country', 'MX');

            $charge_request = ['amount' => 500];
            $openpay_card_points_confirm = null; // Parámetro no definido / inválido para isset()

            $this->cardPointsService->dataValidationAssignement($charge_request, $openpay_card_points_confirm);

            $this->assertArrayNotHasKey('use_card_points', $charge_request);
        }

        // =====================================================================
        // CASO 3: El país NO es MX (Debe ignorar por completo el bloque)
        // =====================================================================
        public function test_dataValidationAssignement_does_nothing_if_country_is_not_mx()
        {
            // Forzamos un país distinto a México (ej. Colombia o Perú)
            $this->setProtectedProperty($this->cardPointsService, 'country', 'CO');

            $charge_request = ['amount' => 35000];
            $openpay_card_points_confirm = 'true';

            $this->cardPointsService->dataValidationAssignement($charge_request, $openpay_card_points_confirm);

            // Verificamos que el arreglo original no haya sido mutado ni tenga la llave
            $this->assertArrayNotHasKey('use_card_points', $charge_request);
        }
    }
}