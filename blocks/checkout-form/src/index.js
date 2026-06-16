//import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import Form from './form';
import {registerPlugin} from "@wordpress/plugins";
import React from "react";
import { ExperimentalOrderMeta } from '@woocommerce/blocks-checkout';
import { TotalsItem } from '@woocommerce/blocks-checkout';

const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { getSetting } = window.wc.wcSettings

const settings = getSetting( 'wc_openpay_gateway_data', {} )

//const label = decodeEntities( settings.title )
const label = decodeEntities( 'Pago con tarjeta (Openpay)' )


/*
const Content = () => {
	return decodeEntities( settings.description || '' )
}
*/

const Label = ( props ) => {
	const { PaymentMethodLabel } = props.components
	return <PaymentMethodLabel text={ label } />
}

registerPaymentMethod( {
	name: "wc_openpay_gateway",
	label: <Label />,
	content:<Form/>,
	edit:<Form/>,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports,
	}
} )

const MiRenglonOpenpay = () => {
	// Leemos el dato que enviaste desde PHP.
	// Asegúrate de que la llave se llame exactamente así en tu array de PHP.
	const miTotal = 60; //settings.total_campo_personalizado || 0;

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
				<span className="wc-block-components-totals-item__label"
					  style={{padding: '0 16px'}}>IVA Total (Informativo)</span>
				<span className="wc-block-components-totals-item__value"
					  style={{padding: '0 16px'}}>{valorFormateado}</span>
			</div>
		</ExperimentalOrderMeta>
	);
};

// 4. REGISTRAMOS EL COMPONENTE COMO UN PLUGIN DE BLOQUES
registerPlugin('renglon-personalizado-openpay', {
	render: () => <MiRenglonOpenpay/>,
	// Esto asegura que solo se intente pintar en la pantalla de Checkout
	scope: 'woocommerce-checkout',
});