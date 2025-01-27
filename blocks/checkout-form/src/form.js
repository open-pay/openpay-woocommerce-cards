import React, { useState, useEffect } from 'react'
import { decodeEntities } from '@wordpress/html-entities';

const { getSetting } = window.wc.wcSettings
const settings = getSetting( 'wc_openpay_gateway_data', {} )

/*-----------------------------------------------------
| Adding raw settings
|Adding raw settings 
| save_cc: No guardar == 0, Guardar y no solicitar cvv == 1, Guardar y solicitar cvv == 2
--------------------------------------------------------*/
settings.save_cc = 1;
settings.country_code = 'MX'; // MX, CO, PE
settings.isGuest = false; //Configuracion para verificar si es ivitado o no;
settings.cc_options = [
    {name: '4111XXXX1111', value: 'kstraxbhst7yh'},
    {name: '4242XXXX4242', value: 'kftrafffst7yh'},
    {name: '4241XXXX4110', value: 'k7trauiyst8tr'},
    {name: '5300XXXX4100', value: 'kotr567ujhdcv'}
]
console.log(settings);


const label = decodeEntities( settings.title )


const Form = ( props ) => {
	const { eventRegistration, emitResponse, billing } = props;
	const { onPaymentSetup } = eventRegistration;

    const [openpayHolderName, setOpenpayHolderName] = useState('');
    const [openpayCardNumber, setOpenpayCardNumber] = useState('');
    const [openpayCardExpiry, setOpenpayCardExpiry] = useState('');
    const [openpayCardCvc,    setOpenpayCardCvc] = useState('');
    const [selectedCard, setSelectedCard] = useState(0);
    var openpayToken = '';
    var openpayTokenizedCard = '';
    

    const tokenRequest = async () => {
        var card = openpayCardNumber;
        var cvc = openpayCardCvc;
        var expires = openpayCardExpiry;

        console.log(settings);
        console.log(settings.title);
        console.log(settings.merchantId);
        console.log(settings.publicKey);
        console.log(card + ' - ' + cvc + ' - ' + expires);

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

        //Data cuando selecciona una tarjeta con cvv
        /*
            var data = {
            openpay_token: selectedCard,
            cvv2: openpayCardCvc,
            address:{
                line1:billing.billingAddress.address_1,
                line2:billing.billingAddress.address_2,
                state:billing.billingAddress.state,
                city:billing.billingAddress.city,
                postal_code:billing.billingAddress.postcode,
                country_code:billing.billingAddress.country
            }    
        };
        */ 

        console.log(data);

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

    const verifySelectedCard = (event) => {
        console.log(event.target.value);
        if(event.target.value != 0) {
            setSelectedCard(event.target.value);
        } else {
            setSelectedCard(event.target.value);
        }
    }
    useEffect( () => {
		const unsubscribe = onPaymentSetup( async () => {


			//console.log('onPaymentSetup_openpayHolderName - ' + openpayHolderName);
            //console.log('onPaymentSetup_deviceSessionId - ' + deviceSessionId);
            //console.log('onPaymentSetup_CARD - ' + card );
            console.log('Billing - ' + JSON.stringify(billing))
            //console.log('Billing - ' + billing.billingAddress.first_name);

        if(openpayHolderName.length){
            await tokenRequest();
            console.log('after token request');

            if ( openpayToken.length) {
                return {
                    type: emitResponse.responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: {
                            openpay_token: openpayToken,
                            openpay_tokenized_card: openpayTokenizedCard,
                            device_session_id: deviceSessionId
                        },
                    },
                };
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
        openpayCardCvc
	] );

	//return decodeEntities( Form || '' );
    //return Form;
    return (
        <div id="payment_form_openpay_cards" style={{ marginBottom: '20px', display: 'flex', flexWrap: 'wrap', gap: '0 16px', justifyContent: 'space-between'}}>
            {settings.isGuest == false ? 
            <div class="wc-blocks-components-select is-active" style={{flex: '0 0 100%'}}>
                <div class="wc-blocks-components-select__container">
                    <label class="wc-blocks-components-select__label" for="openpaySavedCard">Selecciona la tarjeta</label>
                    <select class="wc-blocks-components-select__select"
                        onChange={verifySelectedCard}
                        name="openpaySavedCard"
                        id="openpaySavedCard"
                        placeholder="Selecciona la tarjeta"
                    >
                        <option value="0" selected="selected"> Selecciona una tarjeta </option>
                        {
                            settings.cc_options.map( (card, index ) => {
                                return (<option key={index} value={card.value}> {card.name} </option>)
                            })
                        }
                    </select>
                </div>
            </div> : null}
            { selectedCard == 0 ? 
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
            { selectedCard == 0 ?
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
            { selectedCard == 0 ?
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
            { selectedCard != 0 && settings.save_cc == 1  && settings.country_code == 'PE' ? null :
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
            { settings.save_cc == 0 ? <div class="wc-block-components-checkbox is-active" style={{ marginBottom: '20px' }} >
                <label for="save_cc" class="label">
                    <input class="wc-block-components-checkbok__input" type="checkbox" name="save_cc" id="save_cc" />
                    <span >Guardar tarjeta</span>
                    { /* 
                    <img alt="" src="<?php echo $this->images_dir ?>tooltip_symbol.svg"></img>
                    <span class="tooltiptext" >Al guardar los datos de tu tarjeta agilizarás tus pagos futuros y podrás usarla como método de pago guardado.</span>
                    */}
                </label>
            </div> : null } 
        </div>
    );
};

export default Form;