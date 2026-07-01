<?php

namespace OpenpayCards\Tests;

use PHPUnit\Framework\TestCase;
use OpenpayCards\Includes\OpenpayErrorManager;

/**
 * Pruebas unitarias para OpenpayErrorManager.
 *
 * @covers \OpenpayCards\Includes\OpenpayErrorManager
 */
class OpenpayErrorManagerTest extends TestCase
{
    /**
     * Prueba que el método devuelva el arreglo correcto cuando se envía un código existente.
     * @covers \OpenpayCards\Includes\OpenpayErrorManager::getErrorMessages
     */
    public function test_getErrorMessages_returns_known_error_array()
    {
        // 1. Preparación: Tomamos un código que sabemos que está en el diccionario (ej. 3001)
        $code = 3001;

        // 2. Ejecución
        $result = OpenpayErrorManager::getErrorMessages($code);

        // 3. Aserciones
        $this->assertIsArray($result, 'El resultado debe ser un arreglo.');
        $this->assertArrayHasKey('clientError', $result);
        $this->assertArrayHasKey('adjustedError', $result);
        $this->assertArrayHasKey('orderDetailError', $result);
        $this->assertArrayHasKey('logError', $result);

        // Validamos el contenido exacto de ese código
        $this->assertEquals('La tarjeta fue rechazada.', $result['clientError']);
        $this->assertEquals('Tu tarjeta fue rechazada. Por favor intenta con otra.', $result['adjustedError']);
        $this->assertEquals('La tarjeta fue declinada por el banco.', $result['orderDetailError']);
        $this->assertEquals('The card was declined by the bank', $result['logError']);
    }

    /**
     * Prueba que el método devuelva el arreglo por defecto cuando el código NO existe.
     * @covers \OpenpayCards\Includes\OpenpayErrorManager::getErrorMessages
     */
    public function test_getErrorMessages_returns_default_error_on_unknown_code()
    {
        // 1. Preparación: Pasamos un código inventado o desconocido
        $code = 9999;

        // 2. Ejecución
        $result = OpenpayErrorManager::getErrorMessages($code);

        // 3. Aserciones
        $this->assertIsArray($result, 'El resultado debe ser un arreglo.');
        $this->assertEquals('Ha ocurrido un error inesperado', $result['clientError']);
        $this->assertEquals('Ha ocurrido un error inesperado', $result['adjustedError']);
        $this->assertEquals('Ha ocurrido un error inesperado', $result['orderDetailError']);
        $this->assertEquals('Ha ocurrido un error inesperado', $result['logError']);
    }

    /**
     * Prueba el comportamiento cuando el código llega como String en lugar de Integer.
     * Al usar claves numéricas en arrays, PHP maneja el cast automáticamente,
     * pero es una buena práctica validarlo para prevenir falsos negativos.
     * @covers \OpenpayCards\Includes\OpenpayErrorManager::getErrorMessages
     */
    public function test_getErrorMessages_handles_string_code()
    {
        // 1. Preparación: Pasamos un código existente pero como cadena de texto
        $code = "1004";

        // 2. Ejecución
        $result = OpenpayErrorManager::getErrorMessages($code);

        // 3. Aserciones
        $this->assertEquals('Servicio no disponible.', $result['clientError']);
        $this->assertEquals('The resource is unavailable at this moment. Please try again later', $result['logError']);
    }

    /**
     * Prueba el comportamiento cuando el código es null o vacío.
     * @covers \OpenpayCards\Includes\OpenpayErrorManager::getErrorMessages
     */
    public function test_getErrorMessages_handles_null_code()
    {
        // 1. Preparación
        $code = null;

        // 2. Ejecución
        $result = OpenpayErrorManager::getErrorMessages($code);

        // 3. Aserciones (Debe caer en el caso por defecto)
        $this->assertEquals('Ha ocurrido un error inesperado', $result['clientError']);
    }
}