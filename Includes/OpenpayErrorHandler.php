<?php
namespace OpenpayCards\Includes;

use Exception;
use Openpay\Data\OpenpayApiError;
use Openpay\Data\OpenpayApiRequestError;
use Openpay\Data\OpenpayApiConnectionError;
use Openpay\Data\OpenpayApiAuthError;
use Openpay\Data\OpenpayApiTransactionError;
use OpenpayCards\Includes\OpenpayErrorManager;

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
            self::$logger->error($message, $context);
        }
    }

    //** OPCIONAL SI DESEAMOS LOGGEAR LOS ERRORRES DE PHP */
    /*public static function handlePhpError($errno, $errstr, $errfile, $errline) {
        //$message = "[PHP ERROR] $errstr en $errfile:$errline";
        //self::log($message);
        return false; // Deja que PHP siga su curso normal si no quieres suprimir el error
    }*/

    public static function handleOpenpayPluginException($exception, $order_id = null, $customer_id = null) {
        if ($exception instanceof OpenpayApiTransactionError || $exception instanceof OpenpayApiError || $exception instanceof OpenpayApiConnectionError ) {
            $openpayErrorManager = new OpenpayErrorManager();
            $errorMessage = $openpayErrorManager::getErrorMessages($exception->getCode());
            $message = "[Openpay ERROR] " . $errorMessage['logError'];
            $uuid = self::generate_uuid_v4();
             $context = [
                'id' => $uuid,
                'order_id' => ($order_id == null) ? 'no_order_id' : $order_id,
                'code' => $exception->getCode(),
                'user_id' => ($customer_id === null) ? 'guest' : $customer_id,
                'gateway' => 'wc_openpay_cards',
            ];

            if($order_id != null ) {
                $order = wc_get_order( $order_id );
                $order->add_order_note( $errorMessage['orderDetailError'] );
                $order->update_status('failed');
            }
        } else {
            $message = "[EXCEPTION] " . $exception->getMessage();
        }

       

        self::log($message, $context);
    }

    public static function catchOpenpayError($callback, $order_id = null, $customer_id = null) {
        try {
            return $callback();
        } catch (OpenpayApiConnectionError $e) {
            self::handleOpenpayPluginException($e, $order_id, $customer_id);
            $openpayErrorManager = new OpenpayErrorManager();
            $errorMessage = $openpayErrorManager::getErrorMessages($e->getCode());
            throw new Exception($errorMessage['clientError'], $e->getCode());  // ** SE CREA UNA EXCEPCION PERSONALIZADA PRESERVANDO EL CODE DE LA EXCEPCION ORIGINAL
        } catch (OpenpayApiTransactionError $e) {
            self::handleOpenpayPluginException($e, $order_id, $customer_id);
            if($e->getCode() == '3005') return 3005;
            $openpayErrorManager = new OpenpayErrorManager();
            $errorMessage = $openpayErrorManager::getErrorMessages($e->getCode());
            throw new Exception($errorMessage['clientError'], $e->getCode());
        } catch (OpenpayApiError $e) {
            self::handleOpenpayPluginException($e, $order_id, $customer_id);
            $openpayErrorManager = new OpenpayErrorManager();
            $errorMessage = $openpayErrorManager::getErrorMessages($e->getCode());
            throw new Exception($errorMessage['clientError'], $e->getCode());
        }
        return false;
    }

    public static function generate_uuid_v4() {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}