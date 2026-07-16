import React, { useEffect, useRef, useState } from "react";

const NAMESPACE = "openpay_cards_propina";

const DEFAULT_FORMAT_CONFIG = {
  priceDecimals: 2,
  decimalSeparator: ".",
  thousandSeparator: ",",
  debug: false,
};

const getFormatConfig = (data = {}) => {
  const parsedDecimals = parseInt(data.price_decimals ?? "2", 10);

  return {
    priceDecimals: Number.isNaN(parsedDecimals)
      ? DEFAULT_FORMAT_CONFIG.priceDecimals
      : Math.max(0, parsedDecimals),
    decimalSeparator:
      data.decimal_separator || DEFAULT_FORMAT_CONFIG.decimalSeparator,
    thousandSeparator:
      data.thousand_separator || DEFAULT_FORMAT_CONFIG.thousandSeparator,
    debug: data.debug === true || window?.openpayPropinaDebug === true,
  };
};

const debugLog = (config, label, payload = {}) => {
  if (!config.debug) return;
  console.log(`[Openpay Propina] ${label}`, payload);
};

// 1. LIMPIEZA: Cortamos por el separador decimal y tomamos SOLO los enteros
const cleanAmount = (value = "", config = DEFAULT_FORMAT_CONFIG) => {
  const raw = String(value || "");
  const decimalSeparator = config.decimalSeparator || ".";

  // Al cortar por el separador decimal, ignoramos los ".00" visuales
  const integerPart = raw.split(decimalSeparator)[0];

  // Eliminamos comas de miles o cualquier carácter no numérico del entero
  const normalized = integerPart.replace(/\D/g, "");

  debugLog(config, "cleanAmount", { raw, integerPart, normalized });
  return normalized;
};

const parseAmount = (value = "", config = DEFAULT_FORMAT_CONFIG) => {
  const clean = cleanAmount(value, config);
  const amount = parseInt(clean, 10);
  return Number.isNaN(amount) ? 0 : amount;
};

// 2. FORMATEO: Agregamos separador de miles y adjuntamos los decimales de WooCommerce
const formatAmount = (value = "", config = DEFAULT_FORMAT_CONFIG) => {
  const amount = parseAmount(value, config);

  if (amount <= 0) {
    return "";
  }

  // Formateamos la parte entera con el separador de miles de WooCommerce
  const integerFormatted = String(amount).replace(
    /\B(?=(\d{3})+(?!\d))/g,
    config.thousandSeparator,
  );

  // Si WooCommerce requiere mostrar decimales, concatenamos los ceros
  if (config.priceDecimals > 0) {
    const zeros = "0".repeat(config.priceDecimals);
    return `${integerFormatted}${config.decimalSeparator}${zeros}`;
  }

  return integerFormatted;
};

const formatStoredAmount = (value = "", config = DEFAULT_FORMAT_CONFIG) => {
  return parseAmount(value, config) > 0 ? formatAmount(value, config) : "";
};

const OpenpayPropinaCheckout = ({ extensions = {}, context = "" }) => {
  const data = extensions?.[NAMESPACE] || {};
  const formatConfig = getFormatConfig(data);
  const extensionCartUpdate = window?.wc?.blocksCheckout?.extensionCartUpdate;

  const [amount, setAmount] = useState(
    formatStoredAmount(data.amount, formatConfig),
  );
  const inputRef = useRef(null);
  const isEditingRef = useRef(false);
  const [errorMessage, setErrorMessage] = useState("");
  const timerRef = useRef(null);

  useEffect(() => {
    if (!isEditingRef.current) {
      setAmount(formatStoredAmount(data.amount, formatConfig));
    }
  }, [
    data.amount,
    data.max_amount,
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

  const updateTip = (cleanValue) => {
    clearTimeout(timerRef.current);

    timerRef.current = setTimeout(() => {
      extensionCartUpdate({
        namespace: NAMESPACE,
        data: {
          amount: cleanValue, // Se envía únicamente la parte entera limpia
        },
      });
    }, 500);
  };

  // 3. CURSOR: Mantiene el cursor siempre antes del punto decimal
  const constrainCursor = (event) => {
    const input = event.target;
    const value = input.value || "";
    const decIndex = value.indexOf(formatConfig.decimalSeparator);

    // Si el usuario hace clic o navega dentro de los decimales ".00", regresamos el cursor a los enteros
    if (decIndex !== -1 && input.selectionStart > decIndex) {
      input.setSelectionRange(decIndex, decIndex);
    }
  };

  const handleChange = (event) => {
    const input = event.target;
    const rawValue = input.value;
    const selectionStart = input.selectionStart || 0;

    // Contamos dígitos limpios solo en la parte entera antes de la posición del cursor
    const integerPartBeforeCursor = rawValue
      .slice(0, selectionStart)
      .split(formatConfig.decimalSeparator)[0];
    const digitsBeforeCursor = integerPartBeforeCursor.replace(
      /\D/g,
      "",
    ).length;

    const cleanValue = cleanAmount(rawValue, formatConfig);
    const numericValue = parseAmount(cleanValue, formatConfig);
    const maxAmount = parseAmount(data.max_amount, formatConfig);

    if (rawValue === "" || cleanValue === "") {
      setErrorMessage("");
      setAmount("");
      updateTip("0");
      return;
    }

    const wasInvalid = maxAmount > 0 && numericValue > maxAmount;

    if (wasInvalid) {
      setErrorMessage(
        `La propina no puede ser mayor que el importe total: ${
          data.max_amount_formatted || formatAmount(maxAmount, formatConfig)
        }.`,
      );
      return;
    }

    setErrorMessage("");

    // Formatear valor actual
    const formattedValue = formatAmount(cleanValue, formatConfig);
    setAmount(formattedValue);
    updateTip(cleanValue);

    // Restauramos el cursor en la posición exacta dentro del grupo de enteros
    requestAnimationFrame(() => {
      if (!inputRef.current) return;

      let newCursorPos = 0;
      let digitsSeen = 0;

      while (
        newCursorPos < formattedValue.length &&
        digitsSeen < digitsBeforeCursor
      ) {
        if (/\d/.test(formattedValue[newCursorPos])) {
          digitsSeen++;
        }
        newCursorPos++;
      }

      inputRef.current.setSelectionRange(newCursorPos, newCursorPos);
    });
  };

  const handleBlur = () => {
    isEditingRef.current = false;
    clearTimeout(timerRef.current);

    const cleanValue = cleanAmount(amount, formatConfig);
    const numericValue = parseAmount(cleanValue, formatConfig);

    if (cleanValue === "" || numericValue <= 0) {
      setAmount("");
      setErrorMessage("");
      extensionCartUpdate({
        namespace: NAMESPACE,
        data: { amount: "0" },
      });
      return;
    }

    setAmount(formatAmount(cleanValue, formatConfig));
    extensionCartUpdate({
      namespace: NAMESPACE,
      data: { amount: cleanValue },
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
          ref={inputRef}
          id="openpay-propina"
          type="text"
          className="wc-block-components-totals-item__value"
          inputMode="numeric"
          pattern="[0-9]*"
          autoComplete="off"
          value={amount}
          onChange={handleChange}
          onBlur={handleBlur}
          onFocus={(e) => {
            isEditingRef.current = true;
            constrainCursor(e);
          }}
          onClick={constrainCursor}
          onKeyUp={constrainCursor}
          placeholder="0"
          style={{
            width: "120px",
            maxWidth: "120px",
            textAlign: "right",
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

  if (!registerPlugin || !ExperimentalOrderMeta) return;

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
