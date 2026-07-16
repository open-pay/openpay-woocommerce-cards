import React, { useEffect, useRef, useState } from "react";

const NAMESPACE = "openpay_cards_propina";

const DEFAULT_FORMAT_CONFIG = {
  priceDecimals: 2,
  decimalSeparator: ".",
  thousandSeparator: ",",
  debug: false,
};

const escapeRegExp = (value = "") => {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
};

const getFormatConfig = (data = {}) => {
  const parsedDecimals = parseInt(data.price_decimals ?? "2", 10);

  return {
    priceDecimals: Number.isNaN(parsedDecimals)
      ? 2
      : Math.max(0, parsedDecimals),
    decimalSeparator:
      data.decimal_separator || DEFAULT_FORMAT_CONFIG.decimalSeparator,
    thousandSeparator:
      data.thousand_separator || DEFAULT_FORMAT_CONFIG.thousandSeparator,
    debug: data.debug === true || window?.openpayPropinaDebug === true,
  };
};

const debugLog = (config, label, payload = {}) => {
  if (!config.debug) {
    return;
  }

  console.log(`[Openpay Propina] ${label}`, payload);
};

const cleanAmount = (value = "", config = DEFAULT_FORMAT_CONFIG) => {
  const raw = String(value || "");
  const thousandSeparator = config.thousandSeparator || ",";
  const decimalSeparator = config.decimalSeparator || ".";

  let normalized = raw;

  if (thousandSeparator !== "") {
    normalized = normalized.replace(
      new RegExp(escapeRegExp(thousandSeparator), "g"),
      "",
    );
  }

  if (decimalSeparator !== ".") {
    normalized = normalized.replace(
      new RegExp(escapeRegExp(decimalSeparator), "g"),
      ".",
    );
  }

  normalized = normalized.replace(/[^0-9.]/g, "");

  const parts = normalized.split(".");

  if (parts.length > 2) {
    normalized = `${parts.shift()}.${parts.join("")}`;
  }

  debugLog(config, "cleanAmount", {
    raw,
    normalized,
    thousandSeparator,
    decimalSeparator,
  });

  return normalized;
};

const parseAmount = (value = "", config = DEFAULT_FORMAT_CONFIG) => {
  const clean = cleanAmount(value, config);
  const amount = parseFloat(clean);
  const parsedAmount = Number.isNaN(amount) ? 0 : amount;

  debugLog(config, "parseAmount", {
    value,
    clean,
    parsedAmount,
  });

  return parsedAmount;
};

const formatAmount = (
  value = "",
  forceDecimals = false,
  config = DEFAULT_FORMAT_CONFIG,
) => {
  const clean = cleanAmount(value, config);

  if (clean === "") {
    return "";
  }

  const hasDecimal = clean.includes(".");
  const [rawIntegerPart, rawDecimalPart = ""] = clean.split(".");
  const priceDecimals = config.priceDecimals;
  const decimalSeparator = config.decimalSeparator;
  const thousandSeparator = config.thousandSeparator;

  const integerPart = (rawIntegerPart || "0")
    .replace(/^0+(?=\d)/, "")
    .replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator);

  let formattedValue = integerPart;

  if (forceDecimals && priceDecimals > 0) {
    formattedValue = `${integerPart}${decimalSeparator}${`${rawDecimalPart}${"0".repeat(
      priceDecimals,
    )}`.substring(0, priceDecimals)}`;
  } else if (hasDecimal && priceDecimals > 0) {
    formattedValue = `${integerPart}${decimalSeparator}${rawDecimalPart.substring(
      0,
      priceDecimals,
    )}`;
  }

  debugLog(config, "formatAmount", {
    value,
    clean,
    forceDecimals,
    priceDecimals,
    decimalSeparator,
    thousandSeparator,
    formattedValue,
  });

  return formattedValue;
};

const formatStoredAmount = (value = "", config = DEFAULT_FORMAT_CONFIG) => {
  return parseAmount(value, config) > 0
    ? formatAmount(value, true, config)
    : "";
};

const OpenpayPropinaCheckout = ({ extensions = {}, context = "" }) => {
  const data = extensions?.[NAMESPACE] || {};
  const formatConfig = getFormatConfig(data);
  const maxAmount = parseAmount(data.max_amount || "0", formatConfig);
  const maxAmountFormatted =
    data.max_amount_formatted || data.max_amount || "0";
  const extensionCartUpdate = window?.wc?.blocksCheckout?.extensionCartUpdate;

  const [amount, setAmount] = useState(
    formatStoredAmount(data.amount, formatConfig),
  );
  const isEditingRef = useRef(false);
  const [errorMessage, setErrorMessage] = useState("");
  const timerRef = useRef(null);

  useEffect(() => {
    debugLog(formatConfig, "Store API data changed", {
      amount: data.amount,
      maxAmount: data.max_amount,
      maxAmountFormatted: data.max_amount_formatted,
      formatConfig,
      isEditing: isEditingRef.current,
    });

    if (!isEditingRef.current) {
      setAmount(formatStoredAmount(data.amount, formatConfig));
    }
  }, [
    data.amount,
    data.max_amount,
    data.max_amount_formatted,
    data.price_decimals,
    data.decimal_separator,
    data.thousand_separator,
  ]);

  if (
    context !== "woocommerce/checkout" ||
    data.enabled !== true ||
    !extensionCartUpdate
  ) {
    return null;
  }

  const updateTip = (value) => {
    clearTimeout(timerRef.current);

    const cleanValue = cleanAmount(value, formatConfig) || "0";

    debugLog(formatConfig, "updateTip scheduled", {
      value,
      cleanValue,
    });

    timerRef.current = setTimeout(() => {
      debugLog(formatConfig, "extensionCartUpdate sent", {
        namespace: NAMESPACE,
        amount: cleanValue,
      });

      extensionCartUpdate({
        namespace: NAMESPACE,
        data: {
          amount: cleanValue,
        },
      });
    }, 500);
  };

  const handleChange = (event) => {
    let value = event.target.value;
    const maxAmount = parseAmount(data.max_amount, formatConfig);

    if (value === "") {
      debugLog(formatConfig, "handleChange empty", {
        value,
      });

      setErrorMessage("");
      setAmount("");
      updateTip("0");
      return;
    }

    let numericValue = parseAmount(value, formatConfig);

    if (numericValue < 0) {
      return;
    }

    const wasClamped = maxAmount > 0 && numericValue > maxAmount;

    if (wasClamped) {
      value = String(maxAmount);
      numericValue = maxAmount;

      setErrorMessage(
        `La propina no puede ser mayor que el importe total de la venta: ${
          data.max_amount_formatted || formatAmount(value, true, formatConfig)
        }.`,
      );
    } else {
      setErrorMessage("");
    }

    const formattedValue = formatAmount(value, false, formatConfig);

    debugLog(formatConfig, "handleChange", {
      rawValue: event.target.value,
      numericValue,
      maxAmount,
      wasClamped,
      formattedValue,
    });

    setAmount(formattedValue);
    updateTip(formattedValue);
  };

  const handleBlur = () => {
    clearTimeout(timerRef.current);
    isEditingRef.current = false;

    let value = cleanAmount(amount, formatConfig);
    const maxAmount = parseAmount(data.max_amount, formatConfig);
    const numericValue = parseAmount(value, formatConfig);

    debugLog(formatConfig, "handleBlur before validation", {
      amount,
      value,
      numericValue,
      maxAmount,
    });

    if (value === "" || numericValue <= 0) {
      setAmount("");
      setErrorMessage("");

      extensionCartUpdate({
        namespace: NAMESPACE,
        data: {
          amount: "0",
        },
      });

      return;
    }

    if (maxAmount > 0 && numericValue > maxAmount) {
      value = data.max_amount || String(maxAmount);
    }

    const formattedValue = formatAmount(value, true, formatConfig);
    const cleanValue = cleanAmount(formattedValue, formatConfig);

    debugLog(formatConfig, "handleBlur formatted", {
      value,
      formattedValue,
      cleanValue,
    });

    setAmount(formattedValue);

    extensionCartUpdate({
      namespace: NAMESPACE,
      data: {
        amount: cleanValue,
      },
    });
  };

  return (
    <div className="wc-block-components-totals-wrapper openpay-propina-checkout">
      <div className="wc-block-components-totals-item">
        <label
          className="wc-block-components-totals-item__label"
          htmlFor="openpay-propina"
        >
          Agregar Propina
        </label>

        <input
          id="openpay-propina"
          type="text"
          inputMode="decimal"
          autoComplete="off"
          value={amount}
          onChange={handleChange}
          onBlur={handleBlur}
          onFocus={() => {
            isEditingRef.current = true;

            debugLog(formatConfig, "input focus", {
              amount,
              dataAmount: data.amount,
              maxAmount: data.max_amount,
              formatConfig,
            });
          }}
          placeholder=""
          style={{
            width: "120px",
            maxWidth: "120px",
            textAlign: "center",
            border: 0,
            borderBottom: "1px solid currentColor",
            borderRadius: 0,
            background: "transparent",
            outline: "none",
            boxShadow: "none",
            appearance: "none",
            padding: "2px 0",
            caretColor: "currentColor",
          }}
        />

        {errorMessage ? (
          <small
            style={{
              width: "120px",
              maxWidth: "120px",
              textAlign: "center",
              border: 0,
              background: "transparent",
              outline: "none",
              boxShadow: "none",
              caretColor: "currentColor",
            }}
          >
            {errorMessage}
          </small>
        ) : null}
      </div>
    </div>
  );
};

const registerOpenpayPropinaCheckout = () => {
  const registerPlugin = window?.wp?.plugins?.registerPlugin;
  const ExperimentalOrderMeta =
    window?.wc?.blocksCheckout?.ExperimentalOrderMeta;

  if (!registerPlugin || !ExperimentalOrderMeta) {
    return;
  }

  const render = () => (
    <ExperimentalOrderMeta>
      <OpenpayPropinaCheckout />
    </ExperimentalOrderMeta>
  );

  registerPlugin("openpay-propina-checkout", {
    render,
    scope: "woocommerce-checkout",
  });
};

registerOpenpayPropinaCheckout();
