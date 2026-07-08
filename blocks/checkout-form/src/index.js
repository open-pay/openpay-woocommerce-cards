import { decodeEntities } from "@wordpress/html-entities";
import Form from "./form";
import { registerPlugin } from "@wordpress/plugins";
import React from "react";
import TaxSummaryOpenpay from "./tax-sumary/tax-sumary";
import "./tax-sumary/propina";

const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { getSetting } = window.wc.wcSettings;

const settings = getSetting("wc_openpay_gateway_data", {});

//const label = decodeEntities( settings.title )
const label = decodeEntities("Pago con tarjeta (Openpay)");

/*
const Content = () => {
	return decodeEntities( settings.description || '' )
}
*/

const Label = (props) => {
  const { PaymentMethodLabel } = props.components;
  return <PaymentMethodLabel text={label} />;
};

registerPaymentMethod({
  name: "wc_openpay_gateway",
  label: <Label />,
  content: <Form />,
  edit: <Form />,
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
    features: settings.supports,
  },
});

// 4. REGISTRA COMPONENTE IVA COLOMBIA
registerPlugin("wc-openpay-tax-summary", {
  render: () => <TaxSummaryOpenpay />,
  scope: "woocommerce-checkout",
});
