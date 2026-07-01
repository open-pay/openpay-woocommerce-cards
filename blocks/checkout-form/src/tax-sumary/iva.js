import React from "react";
import { ExperimentalOrderMeta } from '@woocommerce/blocks-checkout';
const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { getSetting } = window.wc.wcSettings;

// Aquí se recuperan los datos enviados desde tu clase PHP (get_payment_method_data)
const settings = getSetting( 'wc_openpay_gateway_data', {} );

const IvaOpenpay = () => {
    // Leemos el dato que enviaste desde PHP.
    // Asegúrate de que la llave se llame exactamente así en tu array de PHP.
    const miTotal = settings.iva; // Si en el futuro necesitas usar `settings`, deberás pasarlo como prop o importarlo aquí.

    // Si no hay cobro o el total es cero, no dibujamos nada
    if ( miTotal <= 0 ) {
        return null;
    }

    // Le damos formato de moneda para que se vea estético.
    // Ajusta 'es-MX' y 'MXN' a la moneda de tu tienda.
    const valorFormateado = miTotal.toLocaleString('es-CO', { style: 'currency', currency: 'COP' });

    // 3. ENVOLVEMOS NUESTRO HTML EN EL SLOT Y USAMOS CLASES DE WOOCOMMERCE
    return (
        <ExperimentalOrderMeta>
            <div className="wc-block-components-checkout-order-summary__title" style={{marginTop: '12px'}}>
                <p className="wc-block-components-checkout-order-summary__title-text">Resumen de Impuestos</p>
            </div>
            <div className="wc-block-components-totals-item"  style={{paddingTop: '0'}} >
                <span className="wc-block-components-totals-item__label" style={{padding: '0 16px'}}>
                    IVA Total (Informativo)
                </span>
                <span className="wc-block-components-totals-item__value" style={{padding: '0 16px'}}>
                    {valorFormateado}
                </span>
            </div>
        </ExperimentalOrderMeta>
    );
};

// Exportamos el componente por defecto para poder importarlo en el archivo principal
export default IvaOpenpay;