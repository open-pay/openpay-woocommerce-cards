import React, { useEffect, useRef, useState } from "react";

const NAMESPACE = "openpay_cards_propina";

const cleanAmount = (value = "") => {
  const withoutCommas = String(value).replace(/,/g, "");
  const onlyValidChars = withoutCommas.replace(/[^0-9.]/g, "");
  const parts = onlyValidChars.split(".");

  if (parts.length > 2) {
    return `${parts.shift()}.${parts.join("")}`;
  }

  return onlyValidChars;
};

const parseAmount = (value = "") => {
  const amount = parseFloat(cleanAmount(value));
  return Number.isNaN(amount) ? 0 : amount;
};

const formatAmount = (value = "", forceDecimals = false) => {
  const clean = cleanAmount(value);

  if (clean === "") {
    return "";
  }

  const hasDecimal = clean.includes(".");
  const [rawIntegerPart, rawDecimalPart = ""] = clean.split(".");
  const integerPart = (rawIntegerPart || "0")
    .replace(/^0+(?=\d)/, "")
    .replace(/\B(?=(\d{3})+(?!\d))/g, ",");

  if (forceDecimals) {
    return `${integerPart}.${`${rawDecimalPart}00`.substring(0, 2)}`;
  }

  if (hasDecimal) {
    return `${integerPart}.${rawDecimalPart.substring(0, 2)}`;
  }

  return integerPart;
};

const formatStoredAmount = (value = "") => {
  return parseAmount(value) > 0 ? formatAmount(value, true) : "";
};

const OpenpayPropinaCheckout = ({ extensions = {}, context = "" }) => {
  const data = extensions?.[NAMESPACE] || {};
  const maxAmount = parseFloat(data.max_amount || "0");
  const maxAmountFormatted =
    data.max_amount_formatted || data.max_amount || "0";
  const extensionCartUpdate = window?.wc?.blocksCheckout?.extensionCartUpdate;

  const [amount, setAmount] = useState(formatStoredAmount(data.amount));
  const [errorMessage, setErrorMessage] = useState("");
  const timerRef = useRef(null);

  useEffect(() => {
    setAmount(formatStoredAmount(data.amount));
  }, [data.amount]);

  if (
    context !== "woocommerce/checkout" ||
    data.enabled !== true ||
    !extensionCartUpdate
  ) {
    return null;
  }

  const updateTip = (value) => {
    clearTimeout(timerRef.current);

    timerRef.current = setTimeout(() => {
      extensionCartUpdate({
        namespace: NAMESPACE,
        data: {
          amount: cleanAmount(value) || "0",
        },
      });
    }, 500);
  };

  const handleChange = (event) => {
    let value = event.target.value;
    const maxAmount = parseAmount(data.max_amount);

    if (value === "") {
      setErrorMessage("");
      setAmount("");
      updateTip("0");
      return;
    }

    let numericValue = parseAmount(value);

    if (numericValue < 0) {
      return;
    }

    if (maxAmount > 0 && numericValue > maxAmount) {
      value = String(maxAmount);
      numericValue = maxAmount;

      setErrorMessage(
        `La propina no puede ser mayor que el importe total de la venta: ${
          data.max_amount_formatted || formatAmount(value, true)
        }.`,
      );
    } else {
      setErrorMessage("");
    }

    const formattedValue = formatAmount(value, false);

    setAmount(formattedValue);
    updateTip(formattedValue);
  };

  const handleBlur = () => {
    clearTimeout(timerRef.current);

    let value = cleanAmount(amount);
    const maxAmount = parseAmount(data.max_amount);
    const numericValue = parseAmount(value);

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

    const formattedValue = formatAmount(value, true);

    setAmount(formattedValue);

    extensionCartUpdate({
      namespace: NAMESPACE,
      data: {
        amount: cleanAmount(formattedValue),
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
