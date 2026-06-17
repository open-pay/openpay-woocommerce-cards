import React from "react";

const OpenpayImpoconsumoSummary = ({ extensions, context }) => {
  const data = extensions?.openpay_cards_impoconsumo || {};
  const total = parseFloat(data.total || "0");

  if (
    context !== "woocommerce/checkout" ||
    data.enabled !== true ||
    total <= 0
  ) {
    return null;
  }

  return (
    <div className="wc-block-components-totals-wrapper openpay-impoconsumo-summary">
      <div className="wc-block-components-totals-item">
        <span className="wc-block-components-totals-item__label">
          Impoconsumo
        </span>
        <span className="wc-block-components-totals-item__value">
          {data.total_formatted}
        </span>
      </div>
    </div>
  );
};

const registerOpenpayImpoconsumoSummary = () => {
  const registerPlugin = window?.wp?.plugins?.registerPlugin;
  const ExperimentalOrderMeta =
    window?.wc?.blocksCheckout?.ExperimentalOrderMeta;

  if (!registerPlugin || !ExperimentalOrderMeta) {
    return;
  }

  const render = () => (
    <ExperimentalOrderMeta>
      <OpenpayImpoconsumoSummary />
    </ExperimentalOrderMeta>
  );

  registerPlugin("openpay-impoconsumo-summary", {
    render,
    scope: "woocommerce-checkout",
  });
};

registerOpenpayImpoconsumoSummary();
