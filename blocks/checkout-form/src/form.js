import React, { useState, useEffect, useRef } from "react";
import { decodeEntities } from "@wordpress/html-entities";
import axios from "axios";
import { OpenpayFieldsValidation } from "./fields-validation/openpayFieldsValidation";
import HolderNameComponent from "./form-fields/holderNameComponent";
import CardNumberComponent from "./form-fields/cardNumberComponent";
import CardExpiryComponent from "./form-fields/cardExpiryComponent";
import CardCvcComponent from "./form-fields/cardCvcComponent";
import SaveCardAuthComponent from "./form-fields/saveCardAuthComponent";
import { OpenpayServiceValidation } from "./fields-validation/openpayServiceValidation";
import { CardCvcValidation } from "./fields-validation/cardCvcValidation";

import { createPortal } from "@wordpress/element";

const { getSetting } = window.wc.wcSettings;
const settings = getSetting("wc_openpay_gateway_data", {});
const label = decodeEntities(settings.title);

const Form = (props) => {
  const { eventRegistration, emitResponse, billing } = props;
  const { onPaymentSetup } = eventRegistration;

  const [openpayHolderName, setOpenpayHolderName] = useState("");
  const [openpayCardNumber, setOpenpayCardNumber] = useState("");
  const [openpayCardExpiry, setOpenpayCardExpiry] = useState("");
  const [openpayCardCvc, setOpenpayCardCvc] = useState("");
  const [cardType, setCardType] = useState(null);
  const [payments, setPayments] = useState([]);
  const [hastInterestPe, setHasInterestPe] = useState(null);
  const [installments, setInstallments] = useState("");
  const [withInterestPeru, setWithInterestPeru] = useState(false);
  const [activateForm, setActivateForm] = useState(true);
  const [openpaySaveCardAuth, setOpenpaySaveCardAuth] = useState(false);
  const [openpaySelectedCard, setSelectedCard] = useState("new");
  const [showInvalidCardAlert, setShowInvalidCardAlert] = useState(false);
  const previousFirst8Digits = useRef("");
  const isCompleteCardNumberField = useRef(false);

  // Estados para el Modal de Puntos
  const [showPointsModal, setShowPointsModal] = useState(false);
  const [pointsResolver, setPointsResolver] = useState(null);

  // Reemplaza las declaraciones var por useRef al inicio del componente
  const openpayToken = useRef("");
  const openpayTokenizedCard = useRef("");
  const confirmUseCardPoints = useRef(false);

  //Estilo para bloquear formulario
  const spinnerOverlayStyle = {
    position: "absolute",
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    background: "rgba(255, 255, 255, 0.7)",
    display: "flex",
    justifyContent: "center",
    alignItems: "center",
    zIndex: 10,
  };
  const isAddressComplete = (address) => {
    return Object.values(address).every(
      (value) =>
        value !== undefined && value !== null && value.toString().trim() !== "",
    );
  };

  const tokenRequest = async () => {
    var data = {
      holder_name: openpayHolderName,
      card_number: openpayCardNumber,
      cvv2: openpayCardCvc,
      expiration_month: openpayCardExpiry.substring(0, 2),
      expiration_year: openpayCardExpiry.substring(
        openpayCardExpiry.length - 2,
      ),
    };
    const address = {
      line1: billing.billingAddress.address_1,
      line2: billing.billingAddress.address_2,
      state: billing.billingAddress.state,
      city: billing.billingAddress.city,
      postal_code: billing.billingAddress.postcode,
      country_code: billing.billingAddress.country,
    };
    if (isAddressComplete(address)) {
      data.address = address;
    }
    const result = await tokenRequestWrapper(data);

    if (result.data.error_code) {
      return {
        errorCode: result.data.error_code,
      };
    }
    openpayToken.current = result.data.id;
    openpayTokenizedCard.current = result.data.card.card_number;

    if (
      result.data.card.points_card === true &&
      settings.cardPoints &&
      installments == 0 &&
      settings.country == "MX"
    ) {
      // En lugar de confirm(), abrimos nuestro modal y esperamos la respuesta
      const userDecision = await new Promise((resolve) => {
        setPointsResolver({ resolve }); // Guardamos la función para resolverla después
        setShowPointsModal(true); // Mostramos el modal
      });

      confirmUseCardPoints.current = userDecision;
    }
  };

  const tokenRequestWrapper = (data) => {
    return new Promise((resolve, reject) => {
      OpenPay.token.create(
        data,
        (successResponse) => {
          resolve(successResponse);
        },
        (errorResponse) => {
          resolve(errorResponse);
        },
      );
    });
  };

  useEffect(() => {
    const unsubscribe = onPaymentSetup(async () => {
      if (openpaySelectedCard === "new") {
        const openpayFieldsErrorMessage = OpenpayFieldsValidation(
          openpayHolderName,
          openpayCardNumber,
          openpayCardExpiry,
          openpayCardCvc,
        );
        if (openpayFieldsErrorMessage) {
          return {
            type: emitResponse.responseTypes.ERROR,
            message: openpayFieldsErrorMessage,
          };
        }

        const result = await tokenRequest();
        if (result !== undefined) {
          if (result.errorCode !== undefined) {
            const openpayServiceErrorMessage = OpenpayServiceValidation(
              result.errorCode,
            );
            if (openpayServiceErrorMessage) {
              return {
                type: emitResponse.responseTypes.ERROR,
                message: openpayServiceErrorMessage,
              };
            }
          }
        }

        if (openpayToken.current.length) {
          return {
            type: emitResponse.responseTypes.SUCCESS,
            meta: {
              paymentMethodData: {
                openpay_token: openpayToken.current,
                openpay_tokenized_card: openpayTokenizedCard.current,
                device_session_id: deviceSessionId,
                openpay_save_card_auth: openpaySaveCardAuth,
                openpay_selected_card: openpaySelectedCard,
                ...(confirmUseCardPoints.current && {
                  openpay_card_points_confirm: "ONLY_POINTS",
                }),
                ...(installments > 0 && {
                  openpay_selected_installment: installments,
                }),
                ...(hastInterestPe && {
                  openpay_has_interest_pe: hastInterestPe,
                }),
              },
            },
          };
        }
      } else {
        if (!(settings.saveCardMode === 2 && settings.country === "PE")) {
          const CardCvcValidationErrorMessage =
            CardCvcValidation(openpayCardCvc);
          if (CardCvcValidationErrorMessage) {
            return {
              type: emitResponse.responseTypes.ERROR,
              message: CardCvcValidationErrorMessage,
            };
          }
        }

        return {
          type: emitResponse.responseTypes.SUCCESS,
          meta: {
            paymentMethodData: {
              device_session_id: deviceSessionId,
              openpay_selected_card: openpaySelectedCard,
              openpay_card_cvc: openpayCardCvc,
            },
          },
        };
      }

      return {
        type: emitResponse.responseTypes.ERROR,
        message: "There was an error",
      };
    });

    if (openpaySelectedCard !== "new" && settings.country === "CO") {
      setPayments(Array.from({ length: 36 }, (_, i) => i + 1));
    }

    if (isCompleteCardNumberField.current && openpayCardNumber.length < 15) {
      setShowInvalidCardAlert(true);
      isCompleteCardNumberField.current = false;
    }

    if (openpayCardNumber.length >= 15) {
      isCompleteCardNumberField.current = true;
      setShowInvalidCardAlert(false);
      const currentFirst8Digits = openpayCardNumber.slice(0, 8);

      if (previousFirst8Digits.current !== currentFirst8Digits) {
        setPayments(null);
        previousFirst8Digits.current = currentFirst8Digits;

        setActivateForm(false);
        axios
          .post(
            settings.ajaxurl,
            new URLSearchParams({
              action: "get_type_card_openpay",
              card_bin: openpayCardNumber.slice(0, 8),
              security: settings.binNonce,
            }),
            {
              headers: {
                "Content-Type": "application/x-www-form-urlencoded",
              },
            },
          )
          .then((response) => {
            setCardType(response.data.card_type);
            if (settings.country == "MX") {
              setPayments(settings.installments.payments);
              setActivateForm(true);
            } else if (settings.country == "PE") {
              if (response.data.withInterest) {
                setWithInterestPeru(response.data.withInterest);
                setHasInterestPe(response.data.withInterest);
              }
              if (settings.installments.paymentPlan)
                setPayments(response.data.installments);
            } else {
              setPayments(Array.from({ length: 36 }, (_, i) => i + 1));
            }
            setActivateForm(true);
          })
          .catch((error) => {
            console.log(error);
            setActivateForm(true);
          });
      }
    }

    // Unsubscribes when this component is unmounted.
    return () => {
      unsubscribe();
    };
  }, [
    onPaymentSetup,
    openpayCardNumber,
    openpayCardExpiry,
    openpayCardCvc,
    openpaySaveCardAuth,
    openpaySelectedCard,
    installments,
  ]);

  //return decodeEntities( Form || '' );
  //return Form;
  return (
    <div
      id="payment_form_openpay_cards"
      style={{
        marginBottom: "20px",
        display: "flex",
        flexWrap: "wrap",
        gap: "0 16px",
        justifyContent: "space-between",
      }}
    >
      {!activateForm && (
        <div style={spinnerOverlayStyle}>
          {" "}
          <div className="" />
        </div>
      )}
      {settings.userLoggedIn == true ? (
        <div
          class="wc-blocks-components-select is-active"
          style={{ flex: "0 0 100%" }}
        >
          <div class="wc-blocks-components-select__container">
            <label
              class="wc-blocks-components-select__label"
              for="openpay-selected-card"
            >
              Selecciona la tarjeta
            </label>
            <select
              class="wc-blocks-components-select__select"
              id="openpay-selected-card"
              name="openpaySelectedCard"
              onChange={(e) => setSelectedCard(e.target.value)}
              placeholder="Selecciona la tarjeta"
            >
              {settings.savedCardsList.map((card, index) => {
                return (
                  <option key={index} value={card.value}>
                    {" "}
                    {card.name}{" "}
                  </option>
                );
              })}
            </select>
          </div>
        </div>
      ) : null}

      {openpaySelectedCard == "new" ? (
        /* OPENPAY HOLDER NAME */
        <HolderNameComponent
          openpayHolderName={openpayHolderName}
          setOpenpayHolderName={setOpenpayHolderName}
        />
      ) : null}

      {openpaySelectedCard == "new" ? (
        /* OPENPAY CARD NUMBER */
        <CardNumberComponent
          openpayCardNumber={openpayCardNumber}
          setOpenpayCardNumber={setOpenpayCardNumber}
        />
      ) : null}
      {showInvalidCardAlert ? (
        <div style={{ color: "red", fontSize: 13, width: "100%" }}>
          <p style={{ marginTop: 0 }}>Número de tarjeta invalido</p>
        </div>
      ) : null}

      {openpaySelectedCard == "new" ? (
        /* OPENPAY EXPIRY DATE */
        <CardExpiryComponent
          openpayCardExpiry={openpayCardExpiry}
          setOpenpayCardExpiry={setOpenpayCardExpiry}
        />
      ) : null}

      {openpaySelectedCard != "new" &&
      settings.saveCardMode == 2 &&
      settings.country == "PE" ? null : (
        /* OPENPAY CARD CVC */
        <CardCvcComponent
          openpayCardCvc={openpayCardCvc}
          setOpenpayCardCvc={setOpenpayCardCvc}
        />
      )}

      {settings.userLoggedIn == true &&
      settings.saveCardMode != 0 &&
      openpaySelectedCard == "new" ? (
        /* SAVE CARD AUTHORIZATION */
        <SaveCardAuthComponent
          openpaySaveCardAuth={openpaySaveCardAuth}
          setOpenpaySaveCardAuth={setOpenpaySaveCardAuth}
        />
      ) : null}

      {(payments &&
        payments.length > 0 &&
        (cardType === "credit" || cardType === "CREDIT") &&
        withInterestPeru === false) ||
      (payments && payments.length > 0 && settings.country === "CO") ? (
        <div
          class="wc-blocks-components-select is-active"
          style={{ flex: "0 0 100%" }}
        >
          <div class="wc-blocks-components-select__container">
            <label
              class="wc-blocks-components-select__label"
              for="installments"
            >
              {settings.country == "MX" ? "Meses sin intereses" : "Cuotas"}
            </label>
            <select
              class="wc-blocks-components-select__select"
              name="installments"
              id="installments"
              placeholder="Pago de contado"
              value={installments}
              onChange={(e) => setInstallments(e.target.value)}
            >
              <option value="0" selected="selected">
                {" "}
                Pago de contado{" "}
              </option>
              {payments.map((installment, index) => {
                return (
                  <option key={index} value={installment}>
                    {" "}
                    {installment}{" "}
                    {settings.country == "MX" ? "Meses" : "Cuotas"}{" "}
                  </option>
                );
              })}
            </select>
          </div>
        </div>
      ) : null}

      {showPointsModal &&
        createPortal(
          <div
            style={{
              position: "fixed",
              top: 0,
              left: 0,
              width: "100%",
              height: "100%",
              backgroundColor: "rgba(0, 0, 0, 0.7)",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              zIndex: 10000000,
              pointerEvents: "all",
            }}
          >
            <div
              style={{
                background: "#fff",
                padding: "30px",
                borderRadius: "10px",
                boxShadow: "0 10px 40px rgba(0,0,0,0.5)",
                maxWidth: "380px",
                width: "90%",
                textAlign: "center",
                position: "relative",
              }}
            >
              <h4
                style={{ margin: "0 0 15px", color: "#333", fontSize: "20px" }}
              >
                Pagar con Puntos
              </h4>
              <p
                style={{
                  fontSize: "15px",
                  color: "#666",
                  marginBottom: "25px",
                }}
              >
                ¿Desea usar los puntos de su tarjeta para realizar este pago?
              </p>
              <div
                style={{
                  display: "flex",
                  gap: "15px",
                  justifyContent: "center",
                }}
              >
                <button
                  type="button"
                  style={{
                    backgroundColor: "#5cb85c",
                    color: "#fff",
                    border: "none",
                    padding: "12px 30px",
                    borderRadius: "5px",
                    cursor: "pointer",
                    fontWeight: "bold",
                  }}
                  onClick={(e) => {
                    e.preventDefault();
                    console.log("Portal: Click SI"); // Verás esto ahora sí
                    pointsResolver.resolve(true);
                    setShowPointsModal(false);
                  }}
                >
                  Sí
                </button>
                <button
                  type="button"
                  style={{
                    backgroundColor: "#e0e0e0",
                    color: "#333",
                    border: "none",
                    padding: "12px 30px",
                    borderRadius: "5px",
                    cursor: "pointer",
                    fontWeight: "bold",
                  }}
                  onClick={(e) => {
                    e.preventDefault();
                    console.log("Portal: Click NO");
                    pointsResolver.resolve(false);
                    setShowPointsModal(false);
                  }}
                >
                  No
                </button>
              </div>
            </div>
          </div>,
          document.body, // Esto saca el modal del contenedor de WooCommerce y lo pone en el body
        )}
    </div>
  );
};

export default Form;
