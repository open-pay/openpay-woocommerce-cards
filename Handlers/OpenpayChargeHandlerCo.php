<?php
namespace OpenpayCards\Handlers;
use OpenpayCards\Includes\OpenpayImpoconsumo;
use OpenpayCards\Services\PaymentSettings\Openpay3dSecure;
use OpenpayCards\Services\PaymentSettings\OpenpayIVA;
class OpenpayChargeHandlerCo
{

    public function __construct()
    {
        $this->logger = wc_get_logger();
    }
    public function applyPaymentSettings($charge_request, $payment_settings, $order)
    {

        // CUOTAS
        if (isset($payment_settings['openpay_payment_plan']) && $payment_settings['openpay_payment_plan'] != 1) {
            $charge_request["payment_plan"] = array("payments" => $payment_settings['openpay_payment_plan']);
        }

        // 3D SECURE
        if ($payment_settings['openpay_charge_type'] == '3d') {
            $charge_request['use_3d_secure'] = true;
            $charge_request['redirect_url'] = Openpay3dSecure::redirect_url_3d();
        }

        // SOLO APLICA CARGO DIRECTO (capture=true)
        if (isset($payment_settings['capture'])) {
            $charge_request["capture"] = $payment_settings['capture'];
        }

        // APLICA IVA / IMPOCONSUMO
        $iva_enabled = isset($payment_settings['iva']) && $payment_settings['iva'] === 'yes';
        $impoconsumo_enabled = isset($payment_settings['impoconsumo']) && $payment_settings['impoconsumo'] === 'yes';

        $iva_amount = 0.0;
        $impoconsumo_amount = 0.0;

        if ($iva_enabled) {
            $IVA = new OpenpayIVA();
            $iva_amount = (float) $IVA->getTotalIVA();
        }

        if ($impoconsumo_enabled) {
            $impoconsumo_amount = (float) OpenpayImpoconsumo::get_cart_impoconsumo_total();
        }

        $has_iva = $iva_enabled && $iva_amount > 0;
        $has_impoconsumo = $impoconsumo_enabled && $impoconsumo_amount > 0;

        if ($has_iva && $has_impoconsumo) {
            $base_amount = $order->get_total() - $iva_amount - $impoconsumo_amount;
            $charge_request['taxes'] = [
                'base_amount' => $base_amount,
                'iva' => number_format($iva_amount, 2, '.', ''),
                'consumption_tax_amount' => number_format($impoconsumo_amount, 2, '.', ''),
            ];
        } elseif ($has_iva) {
            $charge_request['iva'] = number_format($iva_amount, 2, '.', '');
        } elseif ($has_impoconsumo) {
            $base_amount = $order->get_total() - $impoconsumo_amount;
            $charge_request['taxes'] = [
                'base_amount' => $base_amount,
                'consumption_tax_amount' => number_format($impoconsumo_amount, 2, '.', ''),
            ];
        }

        $this->logger->info("[OpenpayChargeHandlerCo.applyPaymentSettings] => " . json_encode($charge_request));
        return $charge_request;
    }

}