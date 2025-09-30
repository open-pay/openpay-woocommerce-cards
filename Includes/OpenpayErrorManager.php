<?php
namespace OpenpayCards\Includes;

class OpenpayErrorManager {
    
    private static $errors  = [
        1000 => [
            'clientError' => 'Servicio no disponible.',
            'adjustedError' => 'El pago no fue exitoso por un error en el sistema, puedes intentar de nuevo.',
            'orderDetailError' => 'Ocurrió un error interno en el servidor de Openpay',
            'logError' => 'Internal server error, contact support'
        ],
        1004 => [
            'clientError' => 'Servicio no disponible.',
            'adjustedError' => 'El pago no fue exitoso por un error en el sistema, puedes intentar de nuevo.',
            'orderDetailError' => 'Un servicio necesario para el procesamiento de la transacción no se encuentra disponible.',
            'logError' => 'The resource is unavailable at this moment. Please try again later'
        ],
        1005 => [
            'clientError' => 'Servicio no disponible.',
            'adjustedError' => 'El pago no fue exitoso por un error en el sistema, puedes intentar de nuevo.',
            'orderDetailError' => 'Uno de los recursos requeridos no existe.',
            'logError' => 'The requested resource doesnt exist'
        ],
        3001 => [
            'clientError' => 'La tarjeta fue rechazada.',
            'adjustedError' => 'Tu tarjeta fue rechazada. Por favor intenta con otra.',
            'orderDetailError' => 'La tarjeta fue declinada por el banco.',
            'logError' => 'The card was declined by the bank'
        ],
        3002 => [
            'clientError' => 'La tarjeta ha expirado.',
            'adjustedError' => 'Tu tarjeta fue rechazada. Por favor intenta con otra.',
            'orderDetailError' => 'La tarjeta ha expirado.',
            'logError' => 'The card has expired'
        ],
        3003 => [
            'clientError' => 'La tarjeta no tiene fondos suficientes.',
            'adjustedError' => 'Tu tarjeta fue rechazada. Por favor intenta con otra.',
            'orderDetailError' => 'La tarjeta no tiene fondos suficientes.',
            'logError' => 'The card doesnt have sufficient funds'
        ],
        3004 => [
            'clientError' => 'La tarjeta fue rechazada.',
            'adjustedError' => 'Tu tarjeta fue rechazada. Por favor intenta con otra.',
            'orderDetailError' => 'La tarjeta ha sido identificada como una tarjeta robada.',
            'logError' => 'The card was reported as stolen'
        ],
        3005 => [
            'clientError' => 'La tarjeta fue rechazada.',
            'adjustedError' => 'Tu tarjeta fue rechazada. Por favor intenta con otra.',
            'orderDetailError' => 'La tarjeta ha sido rechazada por el sistema antifraude.',
            'logError' => 'Fraud risk detected by anti-fraud system --- Found in blacklist'
        ],
        3006 => [
            'clientError' => 'La operación no esta permitida para este cliente o esta transacción.',
            'adjustedError' => 'Tu tarjeta fue rechazada. Por favor intenta con otra.',
            'orderDetailError' => 'La operación no esta permitida para este cliente o esta transacción.',
            'logError' => 'Request not allowed'
        ],
        3007 => [
            'clientError' => 'La tarjeta fue rechazada.',
            'adjustedError' => 'Tu tarjeta fue rechazada. Por favor intenta con otra.',
            'orderDetailError' => '',
            'logError' => ''
        ],
        3008 => [
            'clientError' => 'La tarjeta no es soportada en transacciones en línea.',
            'adjustedError' => 'Tu tarjeta fue rechazada. Por favor intenta con otra.',
            'orderDetailError' => '',
            'logError' => ''
        ],
        3009 => [
            'clientError' => 'La tarjeta fue reportada como perdida.',
            'adjustedError' => 'Tu tarjeta fue rechazada. Por favor intenta con otra.',
            'orderDetailError' => 'La tarjeta fue reportada como perdida.',
            'logError' => 'The card was reported as lost'
        ],
        3010 => [
            'clientError' => 'El banco ha restringido la tarjeta.',
            'adjustedError' => 'Tu tarjeta fue rechazada. Por favor intenta con otra.',
            'orderDetailError' => 'El banco ha restringido la tarjeta.',
            'logError' => 'The bank has restricted the card'
        ],
        3011 => [
            'clientError' => 'El banco ha solicitado que la tarjeta sea retenida. Contacte al banco.',
            'adjustedError' => 'Tu tarjeta fue rechazada. Por favor intenta con otra.',
            'orderDetailError' => 'El banco ha solicitado que la tarjeta sea retenida. Contacte al banco.',
            'logError' => 'The bank has requested the card to be retained'
        ],
        3012 => [
            'clientError' => 'Se requiere solicitar al banco autorización para realizar este pago.',
            'adjustedError' => '',
            'orderDetailError' => 'Se requiere solicitar al banco autorización para realizar este pago.',
            'logError' => 'Bank authorization is required for this charge'
        ]
    ];

    public static function getErrorMessages($code) {
         if (isset(self::$errors[$code])) {
            return self::$errors[$code];
        }

        return [
            'clientError' => 'Ha ocurrido un error inesperado',
            'adjustedError' => 'Ha ocurrido un error inesperado',
            'orderDetailError' => 'Ha ocurrido un error inesperado',
            'logError' => 'Ha ocurrido un error inesperado'
        ];
    }
}