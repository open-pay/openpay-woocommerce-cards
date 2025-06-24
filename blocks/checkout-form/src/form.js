import React, { useState, useEffect} from 'react'
import { decodeEntities } from '@wordpress/html-entities';
import axios from "axios";
import {OpenpayFieldsValidation} from "./fields-validation/openpayFieldsValidation";
import HolderNameComponent from "./form-fields/holderNameComponent";
import CardNumberComponent from "./form-fields/cardNumberComponent";
import CardExpiryComponent from "./form-fields/cardExpiryComponent";
import CardCvcComponent from "./form-fields/cardCvcComponent";
import SaveCardAuthComponent from "./form-fields/saveCardAuthComponent";
import {OpenpayServiceValidation} from "./fields-validation/openpayServiceValidation";


const { getSetting } = window.wc.wcSettings
const settings = getSetting( 'wc_openpay_gateway_data', {} )
const label = decodeEntities( settings.title )
console.log('{ REACT INIT SETTINGS } - ' + JSON.stringify(settings));


const Form = ( props ) => {

    const { eventRegistration, emitResponse, billing } = props;
    const { onPaymentSetup } = eventRegistration;

    const [openpayHolderName, setOpenpayHolderName] = useState('');
    const [openpayCardNumber, setOpenpayCardNumber] = useState('');
    const [openpayCardExpiry, setOpenpayCardExpiry] = useState('');
    const [openpayCardCvc,    setOpenpayCardCvc] = useState('');
    const [cardType,          setCardType] = useState(null);
    const [payments,        setPayments] = useState([]);
    const [installments,      setInstallments] = useState('');
    const [activateForm,      setActivateForm] = useState(true);
    const [openpaySaveCardAuth, setOpenpaySaveCardAuth] = useState(false);
    const [openpaySelectedCard, setSelectedCard] = useState('new');

    var openpayToken = '';
    var openpayTokenizedCard = '';
    var confirmUseCardPoints = false;

    //Estilo para bloquear formulario
    const spinnerOverlayStyle = {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        background: 'rgba(255, 255, 255, 0.7)',
        display: 'flex',
        justifyContent: 'center',
        alignItems: 'center',
        zIndex: 10,
    };


    const tokenRequest = async () => {

        var data = {
            holder_name: openpayHolderName,
            card_number: openpayCardNumber,
            cvv2: openpayCardCvc,
            expiration_month: openpayCardExpiry.substring(0,2),
            expiration_year: openpayCardExpiry.substring(2),
            address:{
                line1:billing.billingAddress.address_1,
                line2:billing.billingAddress.address_2,
                state:billing.billingAddress.state,
                city:billing.billingAddress.city,
                postal_code:billing.billingAddress.postcode,
                country_code:billing.billingAddress.country
            }
        };

        console.log('{ REACT } - ' + JSON.stringify(data));

        const result = await tokenRequestWrapper(data);
        console.log("Token Request Result: ");
        console.log(result);
        console.log(data);
        if(result.data.error_code){
            return {
                errorCode: result.data.error_code,
            };
        }
        openpayToken = result.data.id;
        openpayTokenizedCard = result.data.card.card_number;

        if(result.data.card.points_card === true && settings.cardPoints && installments == 0 && settings.country == 'MX') {
            const confirmResult = confirm("¿Desea usar los puntos?")
            confirmUseCardPoints = confirmResult;
        }
    }

    const tokenRequestWrapper = (data) => {

        return new Promise((resolve, reject) => {
            OpenPay.token.create(data, (successResponse) => {
                resolve(successResponse);
            } , (errorResponse) => {
                resolve(errorResponse);
            });
        });

    }

    useEffect( () => {
        const unsubscribe = onPaymentSetup( async () => {

            //console.log('onPaymentSetup_openpayHolderName - ' + openpayHolderName);
            //console.log('onPaymentSetup_deviceSessionId - ' + deviceSessionId);
            //console.log('onPaymentSetup_CARD - ' + card );
            //console.log('Billing - ' + JSON.stringify(billing))
            //console.log('Billing - ' + billing.billingAddress.first_name);

            const openpayFieldsErrorMessage = OpenpayFieldsValidation(openpayHolderName,openpayCardNumber,openpayCardExpiry,openpayCardCvc);
            if(openpayFieldsErrorMessage){
                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: openpayFieldsErrorMessage,
                };
            }

            if(openpayHolderName.length){

               const result = await tokenRequest();
               if(result !== undefined){
                   if(result.errorCode !== undefined) {
                       const openpayServiceErrorMessage = OpenpayServiceValidation(result.errorCode);
                       if (openpayServiceErrorMessage) {
                           return {
                               type: emitResponse.responseTypes.ERROR,
                               message: openpayServiceErrorMessage,
                           };
                       }
                   }
               }

                console.log('{ REACT } - after token request');

                if ( openpayToken.length) {
                    return {
                        type: emitResponse.responseTypes.SUCCESS,
                        meta: {
                            paymentMethodData: {
                                openpay_token: openpayToken,
                                openpay_tokenized_card: openpayTokenizedCard,
                                device_session_id: deviceSessionId,
                                openpay_save_card_auth: openpaySaveCardAuth,
                                openpay_selected_card: openpaySelectedCard,
                                ...(confirmUseCardPoints && {openpay_card_points_confirm: 'ONLY_POINTS'}),
                                ...(installments > 0 && { openpay_selected_installment: installments})
                            },
                        },
                    };
                }
            }else{
                return {
                    type: emitResponse.responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: {
                            device_session_id: deviceSessionId,
                            openpay_selected_card: openpaySelectedCard,
                            openpay_card_cvc: openpayCardCvc
                        },
                    },
                }
            }

            return {
                type: emitResponse.responseTypes.ERROR,
                message: 'There was an error',
            };
        } );

        //Llamada al api para obtener los bines
        if(settings.country == 'MX') {
            if(openpayCardNumber.length === 6){
                const endpoint = `${settings.openpayAPI}/${settings.merchantId}/bines/${openpayCardNumber}/promotions`
                setActivateForm(false);
                axios.get(endpoint).then((response) => {
                    setCardType(response.data.cardType);
                    setPayments(settings.installments.payments);
                    console.log(response.data.installments);
                    setActivateForm(true);
                }).catch((error) => {
                    console.log(error)
                    setActivateForm(true);
                    setCardType(null);
                });
            }
        } else if (settings.country == 'PE') {
            if(openpayCardNumber.length === 6){
                console.log(settings)
                const endpoint = `${settings.openpayAPI}/${settings.merchantId}/bines/${openpayCardNumber}/promotions`
                setActivateForm(false);
                axios.get(endpoint).then((response) => {
                    setCardType(response.data.cardType);
                    if(response.data.installments.paymentPlan ) setPayments(response.data.installments);
                    setActivateForm(true);
                }).catch((error) => {
                    console.log(error)
                    setActivateForm(true);
                    setCardType(null);
                });
            }
        } else {
            if(openpayCardNumber.length === 6){
                console.log(settings)
                const endpoint = `${settings.openpayAPI}/cards/validate-bin?bin=${openpayCardNumber}`
                setActivateForm(false);
                axios.get(endpoint).then((response) => {
                    setCardType(response.data.card_type);
                    if(response.data.card_type == 'CREDIT') setPayments(Array.from({ length: 36 }, (_, i) => i + 1));
                    console.log(payments);
                    setActivateForm(true);
                }).catch((error) => {
                    console.log(error)
                    setActivateForm(true);
                    setCardType(null);
                });
            }
        }
        // Unsubscribes when this component is unmounted.
        return () => {
            unsubscribe();
        };
    }, [
        emitResponse.responseTypes.ERROR,
        emitResponse.responseTypes.SUCCESS,
        //billing,
        onPaymentSetup,
        openpayHolderName,
        openpayCardNumber,
        openpayCardExpiry,
        openpayCardCvc,
        openpaySaveCardAuth,
        openpaySelectedCard,
        openpayCardCvc,
        installments
    ] );

    //return decodeEntities( Form || '' );
    //return Form;
    return (

        <div id="payment_form_openpay_cards" style={{ marginBottom: '20px', display: 'flex', flexWrap: 'wrap', gap: '0 16px', justifyContent: 'space-between'}}>

            { !activateForm && (<div style={spinnerOverlayStyle}> <div className="" /></div>)}
            {settings.userLoggedIn == true ?
                <div class="wc-blocks-components-select is-active" style={{flex: '0 0 100%'}}>
                    <div class="wc-blocks-components-select__container">
                        <label class="wc-blocks-components-select__label" for="openpay-selected-card">Selecciona la tarjeta</label>
                        <select class="wc-blocks-components-select__select"
                                id="openpay-selected-card"
                                name="openpaySelectedCard"
                                onChange={e => setSelectedCard(e.target.value)}
                                placeholder="Selecciona la tarjeta" >
                            {
                                settings.savedCardsList.map( (card, index ) => {
                                    return (<option key={index} value={card.value}> {card.name} </option>)
                                })
                            }
                        </select>
                    </div>
                </div> : null}


            { openpaySelectedCard == 'new' ?
                /* OPENPAY HOLDER NAME */
                <HolderNameComponent
                    openpayHolderName={openpayHolderName}
                    setOpenpayHolderName = {setOpenpayHolderName}/>
            : null }

            { openpaySelectedCard == 'new' ?
                /* OPENPAY CARD NUMBER */
                <CardNumberComponent
                    openpayCardNumber={openpayCardNumber}
                    setOpenpayCardNumber = {setOpenpayCardNumber}/>
            : null }

            { openpaySelectedCard == 'new' ?
                /* OPENPAY EXPIRY DATE */
                <CardExpiryComponent
                    openpayCardExpiry={openpayCardExpiry}
                    setOpenpayCardExpiry = {setOpenpayCardExpiry}/>
            : null }

            { openpaySelectedCard != 'new' && settings.saveCardMode == 2  && settings.country == 'PE' ? null :
                /* OPENPAY CARD CVC */
                <CardCvcComponent
                    openpayCardCvc={openpayCardCvc}
                    setOpenpayCardCvc = {setOpenpayCardCvc}/>
            }

            { settings.userLoggedIn == true && settings.saveCardMode != 0 && openpaySelectedCard == 'new' ?
                /* SAVE CARD AUTHORIZATION */
                <SaveCardAuthComponent
                    openpaySaveCardAuth={openpaySaveCardAuth}
                    setOpenpaySaveCardAuth = {setOpenpaySaveCardAuth}/>
                 : null }

            { payments != null && (cardType == 'credit' || cardType == 'CREDIT')  ?
                <div class="wc-blocks-components-select is-active" style={{flex: '0 0 100%'}}>
                    <div class="wc-blocks-components-select__container">
                        <label class="wc-blocks-components-select__label" for="installments">Meses sin intereses</label>
                        <select class="wc-blocks-components-select__select"
                                name="installments"
                                id="installments"
                                placeholder="Pago de contado"
                                value={installments}
                                onChange={(e) => setInstallments(e.target.value)}
                        >
                            <option value="0" selected="selected"> Pago de contado </option>
                            {
                                payments.map( (installment, index ) => {
                                    return (<option key={index} value={installment}> {installment} Meses </option>)
                                })
                            }
                        </select>
                    </div>
                </div> : null}

        </div>
    );
};

export default Form;