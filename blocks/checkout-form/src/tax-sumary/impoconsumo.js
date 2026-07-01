import React from "react";
import { ExperimentalOrderMeta } from '@woocommerce/blocks-checkout';

const ImpoconsumoOpenpay = ({ extensions = {}, context = "" }) => {
    let data = extensions?.openpay_cards_impoconsumo || {};
    console.log("Impoconsumo data:" + data);
    data.total = 21;
    const total = parseFloat(data.total || "0");
/*
    if (
        context !== "woocommerce/checkout" ||
        data.enabled !== true ||
        total <= 0
    ) {
        return null;
    }
 */

    // Le damos formato de moneda para que se vea estético.
    // Ajusta 'es-MX' y 'MXN' a la moneda de tu tienda.
    const valorFormateado = total.toLocaleString('es-CO', { style: 'currency', currency: 'COP' });

    // 3. ENVOLVEMOS NUESTRO HTML EN EL SLOT Y USAMOS CLASES DE WOOCOMMERCE
    return (
        <ExperimentalOrderMeta>
            <div className="wc-block-components-totals-item"  style={{paddingTop: '0'}} >
                <span className="wc-block-components-totals-item__label" style={{padding: '0 16px'}}>
                    Impoconsumo Total (Informativo)
                </span>
                <span className="wc-block-components-totals-item__value" style={{padding: '0 16px'}}>
                    {valorFormateado}
                </span>
            </div>
        </ExperimentalOrderMeta>
    );
};

// Exportamos el componente por defecto para poder importarlo en el archivo principal
export default ImpoconsumoOpenpay;