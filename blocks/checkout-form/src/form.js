import React, { useState, useEffect } from 'react'
import { decodeEntities } from '@wordpress/html-entities';

const { getSetting } = window.wc.wcSettings
const settings = getSetting( 'wc_openpay_gateway_data', {} )
const label = decodeEntities( settings.title )
console.log('{ REACT } - ' + JSON.stringify(settings));

const Form = ( props ) => {
	const { eventRegistration, emitResponse, billing } = props;
	const { onPaymentSetup } = eventRegistration;

    const [openpayHolderName, setOpenpayHolderName] = useState('');
    const [openpayCardNumber, setOpenpayCardNumber] = useState('');
    const [openpayCardExpiry, setOpenpayCardExpiry] = useState('');
    const [openpayCardCvc,    setOpenpayCardCvc] = useState('');
    const [openpaySaveCardAuth, setOpenpaySaveCardAuth] = useState(false);
    const [openpaySelectedCard, setSelectedCard] = useState('new');

    var openpayToken = '';
    var openpayTokenizedCard = '';
    

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
                            openpay_selected_card: openpaySelectedCard
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
        openpayCardCvc
	] );

	//return decodeEntities( Form || '' );
    //return Form;
    return (
        
        <div id="payment_form_openpay_cards" style={{ marginBottom: '20px', display: 'flex', flexWrap: 'wrap', gap: '0 16px', justifyContent: 'space-between'}}>
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
                 onChange={e => setOpenpayCardNumber(e.target.value)}
                 type="text"
                 maxlength="20"
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
            
        </div>
    );
};

export default Form;