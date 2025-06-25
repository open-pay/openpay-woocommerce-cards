import React, { useState, useEffect } from 'react'
import { decodeEntities } from '@wordpress/html-entities';
import axios from "axios";

const { getSetting } = window.wc.wcSettings
const settings = getSetting( 'wc_openpay_gateway_data', {} )
const label = decodeEntities( settings.title )
console.log('{ REACT INIT SETTINGS } - ' + JSON.stringify(settings));

const Form = ( props ) => {
    //Agregamos a settings datos hardcodeados
    //settings.useCardPoints = true;
    //settings.openpayApi = "";

	const { eventRegistration, emitResponse, billing } = props;
	const { onPaymentSetup } = eventRegistration;

    const [openpayHolderName, setOpenpayHolderName] = useState('');
    const [openpayCardNumber, setOpenpayCardNumber] = useState('');
    const [openpayCardExpiry, setOpenpayCardExpiry] = useState('');
    const [openpayCardCvc,    setOpenpayCardCvc] = useState('');
    const [cardType,          setCardType] = useState(null);
    const [payments,        setPayments] = useState([]);
    const [hastInterestPe, setHasInterestPe] = useState(null);
    const [installments,      setInstallments] = useState('');
    const [withInterestPeru, setWithInterestPeru ] = useState(false);
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
        var card = openpayCardNumber;
        var cvc = openpayCardCvc;
        var expires = openpayCardExpiry;
        console.log('{ REACT } - ' + card + ' - ' + cvc + ' - ' + expires);

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
                reject(errorResponse);
            });
        });
    }


    const validateCardNumber = (e) => {
        const value = e.target.value;
        if(/^\d{0,16}$/.test(value)){
            setOpenpayCardNumber(value);
        }
    }
    
    useEffect( () => {
		const unsubscribe = onPaymentSetup( async () => {


			//console.log('onPaymentSetup_openpayHolderName - ' + openpayHolderName);
            //console.log('onPaymentSetup_deviceSessionId - ' + deviceSessionId);
            //console.log('onPaymentSetup_CARD - ' + card );
            //console.log('Billing - ' + JSON.stringify(billing))
            //console.log('Billing - ' + billing.billingAddress.first_name);

        if(openpayHolderName.length){
            await tokenRequest();
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
                            ...(installments > 0 && { openpay_selected_installment: installments}),
                            ...(hastInterestPe && {openpay_has_interest_pe: hastInterestPe})
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

        if(openpayCardNumber.length === 6) {
            setActivateForm(false);
            axios.post(
                settings.ajaxurl,
                new URLSearchParams({
                    action: 'get_type_card_openpay',
                    card_bin: openpayCardNumber
                }),
                {
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    }
                }
            ).then((response) => {
                setCardType(response.data.card_type);
                if(settings.country == 'MX') {
                    setPayments(settings.installments.payments);
                    setActivateForm(true);
                } else if(settings.country == 'PE') {
                    if(response.data.withInterest) {
                        setWithInterestPeru(response.data.withInterest);
                        setHasInterestPe(response.data.withInterest);
                    }
                    if(settings.installments.paymentPlan) setPayments(response.data.installments);
                } else {
                     if(response.data.card_type == 'CREDIT') setPayments(Array.from({ length: 36 }, (_, i) => i + 1));
                }
                setActivateForm(true);
                console.log(response);
            }).catch((error) => {
                console.log(error);
                setActivateForm(true);
            });
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

            { openpaySelectedCard == 'new' ? /* OPENPAY HOLDER NAME */
            <div class="wc-block-components-text-input is-active" style={{flex: '0 0 100%'}}>
                <input 
                    id="openpay-holder-name"
                    name="openpayHolderName"  
                    value={openpayHolderName} 
                    onChange={e => setOpenpayHolderName(e.target.value)}
                    type="text" 
                    autocomplete="off" 
                    placeholder="Nombre del tarjetahabiente" 
                    data-openpay-card="holder_name" />
                    <label for="openpay-holder-name">Nombre del títular
                        <span class="required">*</span>
                    </label>
            </div> : null }

            { openpaySelectedCard == 'new' ? /* OPENPAY CARD NUMBER */
            <div class="wc-block-components-text-input is-active" style={{flex: '0 0 100%'}} >
                <label for="openpay-card-number">Número de tarjeta <span class="required">*</span></label>
                <input 
                 id="openpay-card-number"
                 name="openpayCardNumber"
                 class="wc-credit-card-form-card-number"
                 value={openpayCardNumber} 
                 onChange={ validateCardNumber }
                 type="text"
                 maxlength="16"
                 autocomplete="off" 
                 placeholder="•••• •••• •••• ••••"
                 data-openpay-card="card_number" />
            </div> : null }

            { openpaySelectedCard == 'new' ? /* OPENPAY EXPIRY DATE */
            <div class="wc-block-components-text-input is-active" style={{flex: '1 0 calc(50% - 12px)'}}>
                <label for="openpay-card-expiry">Expira (MM/AA) <span class="required">*</span></label>
                <input 
                    id="openpay-card-expiry"
                    name="openpayCardExpiry"
                    class="input-text wc-credit-card-form-card-expiry"
                    value={openpayCardExpiry} 
                    onChange={e => setOpenpayCardExpiry(e.target.value)}
                    type="text"
                    autocomplete="off" 
                    placeholder="MM / AA" 
                    maxlength="4" 
                    data-openpay-card="expiration_year" />
            </div> : null }

            { openpaySelectedCard != 'new' && settings.saveCardMode == 2  && settings.country == 'PE' ? null :
            <div class="wc-block-components-text-input is-active" style={{flex: '1 0 calc(50% - 12px)'}}>
                <label for="openpay-card-cvc">CVV <span class="required">*</span></label>
                <input 
                    id="openpay-card-cvc"
                    name="openpayCardCvc" 
                    class="input-text wc-credit-card-form-card-cvc openpay-card-input-cvc" 
                    value={openpayCardCvc} 
                    onChange={e => setOpenpayCardCvc(e.target.value)}
                    type="password" 
                    autocomplete="off" 
                    placeholder="CVC"
                    maxlength="4" 
                    data-openpay-card="cvv2" />
            </div> }

            { settings.userLoggedIn == true && settings.saveCardMode != 0 && openpaySelectedCard == 'new' ? <div class="wc-block-components-checkbox"><label for="openpay-save-card-auth">
                <input
                    id="openpay-save-card-auth"
                    name="openpaySaveCardAuth"
                    class="wc-block-components-checkbox__input"
                    value={openpaySaveCardAuth}
                    onChange={e => {setOpenpaySaveCardAuth(!openpaySaveCardAuth); console.log(openpaySaveCardAuth) } }
                    type="checkbox"
                    aria-invalid="false"/>
                <svg class="wc-block-components-checkbox__mark" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 20"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"></path></svg>
                <span class="wc-block-components-checkbox__label" style={{fontSize: '1.25em'}}>Guardar Tarjeta</span></label>
            </div> : null }

            <div class="wc-block-components-text-input is-active" style={{ marginBottom: '20px' }} >
                <label for="save_cc" class="label">
                    <div class="tooltip">
                    <input type="checkbox" name="save_cc" id="save_cc" />
                    <span >Guardar tarjeta</span>
                    <img alt="" src="<?php echo $this->images_dir ?>tooltip_symbol.svg"></img>
                    <span class="tooltiptext" >Al guardar los datos de tu tarjeta agilizarás tus pagos futuros y podrás usarla como método de pago guardado.</span>
                    </div>
                </label>
            </div>
            { payments.length > 0 && (cardType == 'credit' || cardType == 'CREDIT') && withInterestPeru == false ?
            <div class="wc-blocks-components-select is-active" style={{flex: '0 0 100%'}}>
                <div class="wc-blocks-components-select__container">
                    <label class="wc-blocks-components-select__label" for="installments">{settings.country == 'MX' ? 'Meses sin intereses' : 'Cuotas'}</label>
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
                                return (<option key={index} value={installment}> {installment} {settings.country == 'MX' ? 'Meses' : 'Cuotas'} </option>)
                            })
                        }
                    </select>
                </div>
            </div> : null}
        </div>
    );
};

export default Form;