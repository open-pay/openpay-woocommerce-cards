import React from "react";
import { ExperimentalOrderMeta } from "@woocommerce/blocks-checkout";

const { getSetting } = window.wc.wcSettings;
const settings = getSetting("wc_openpay_gateway_data", {});

const formatCurrency = (value) => {
  const amount = Number(String(value || 0).replace(/[^0-9.-]/g, ""));
  const decimals = parseInt(settings.price_decimals ?? "2", 10);
  const priceDecimals = Number.isNaN(decimals) ? 2 : Math.max(0, decimals);

  if (Number.isNaN(amount)) {
    return "$0";
  }

  return `$${new Intl.NumberFormat("en-US", {
    style: "decimal",
    minimumFractionDigits: priceDecimals,
    maximumFractionDigits: priceDecimals,
  }).format(amount)}`;
};

const TaxSummaryContent = ({ extensions = {}, context = "" }) => {
  const impoconsumoData = extensions?.openpay_cards_impoconsumo || {};

  const ivaEnabled =
    settings.iva_enabled === true ||
    settings.iva_enabled === "yes" ||
    settings.iva_enabled === "1";

  const ivaTotal = ivaEnabled ? Number(settings.iva || 0) : 0;
  const ivaFormatted = settings.iva_formatted || formatCurrency(ivaTotal);

  const impoconsumoTotal = Number(impoconsumoData.total || 0);

  const shouldShowIva = ivaEnabled && ivaTotal > 0;
  const shouldShowImpoconsumo =
    impoconsumoData.enabled === true && impoconsumoTotal > 0;

  if (
    context !== "woocommerce/checkout" ||
    (!shouldShowIva && !shouldShowImpoconsumo)
  ) {
    return null;
  }

  return (
    <div className="openpay-tax-summary">
      <div
        className="wc-block-components-checkout-order-summary__title"
        style={{ marginTop: "12px" }}
      >
        <p className="wc-block-components-checkout-order-summary__title-text">
          Resumen de Impuestos{" "}
          <span
            className="wc-block-components-checkout-order-summary__title-text--sublabel"
            style={{ fontSize: 11 }}
          >
            (Informativo)
          </span>
        </p>
      </div>

      {shouldShowIva ? (
        <div
          className="wc-block-components-totals-item"
          style={{ paddingTop: "0" }}
        >
          <span className="wc-block-components-totals-item__label">IVA</span>

          <span className="wc-block-components-totals-item__value">
            {ivaFormatted}
          </span>
        </div>
      ) : null}

      {shouldShowImpoconsumo ? (
        <div
          className="wc-block-components-totals-item"
          style={{ paddingTop: "0" }}
        >
          <span className="wc-block-components-totals-item__label">
            Impoconsumo
          </span>

          <span className="wc-block-components-totals-item__value">
            {impoconsumoData.total_formatted}
          </span>
        </div>
      ) : null}
    </div>
  );
};

const TaxSummaryOpenpay = () => (
  <ExperimentalOrderMeta>
    <TaxSummaryContent />
  </ExperimentalOrderMeta>
);

export default TaxSummaryOpenpay;
