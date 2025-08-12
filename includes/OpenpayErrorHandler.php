<?php
namespace OpenpayCards\Includes;

use Exception;
use Openpay\Data\OpenpayApiError;
use Openpay\Data\OpenpayApiRequestError;
use Openpay\Data\OpenpayApiConnectionError;
use Openpay\Data\OpenpayApiAuthError;
use Openpay\Data\OpenpayApiTransactionError;

class OpenpayErrorHandler {
    protected static $logger;

    public static function init() {
        //set_error_handler([__CLASS__, 'handlePhpError']);  ** OPCIONAL SI DESEAMOS CAPTURAR LOS ERRORES DE PHP **
        self::$logger = wc_get_logger(); // Logger de WooCommerce
    }

    public static function log($message, $context = []) {
        self::$logger = wc_get_logger();
        $context = is_array($context) ? $context : [];
        if (self::$logger) {
            self::$logger->error($message, array_merge(['source' => 'plugin-openpay-woocommerce-cards'], $context));
        }
    }

    //** OPCIONAL SI DESEAMOS LOGGEAR LOS ERRORRES DE PHP */
    /*public static function handlePhpError($errno, $errstr, $errfile, $errline) {
        //$message = "[PHP ERROR] $errstr en $errfile:$errline";
        //self::log($message);
        return false; // Deja que PHP siga su curso normal si no quieres suprimir el error
    }*/

    public static function handleOpenpayPluginException($exception) {
        if ($exception instanceof OpenpayApiTransactionError || $exception instanceof OpenpayApiError || $exception instanceof OpenpayApiConnectionError ) {
            $message = "[Openpay ERROR] " . $exception->getMessage();
        } else {
            $message = "[EXCEPTION] " . $exception->getMessage();
        }

        self::log($message, ['trace' => $exception->getTraceAsString(),]);
    }

    public static function catchOpenpayError($callback) {
        $log = wc_get_logger();
        try {
            return $callback();
        } catch (OpenpayApiConnectionError $e) {
            self::handleOpenpayPluginException($e);
            throw new Exception("Error al conectarse al api de openpay.", $e->getCode());  // ** SE CREA UNA EXCEPCION PERSONALIZADA PRESERVANDO EL CODE DE LA EXCEPCION ORIGINAL
        } catch (OpenpayApiTransactionError $e) {
            self::handleOpenpayPluginException($e);
            throw new Exception("Ocurrió un error con la transacción. Por favor intenta nuevamente.", $e->getCode());
        } catch (OpenpayApiError $e) {
            self::handleOpenpayPluginException($e);
            throw new Exception("Error en la conexión con Openpay. Intenta más tarde.", $e->getCode());
        }
        return false;
    }
}