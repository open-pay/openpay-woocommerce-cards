//import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import Form from './form';

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