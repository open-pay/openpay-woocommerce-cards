
const cardCvcComponent = ( props ) => {

    const cardCvcInputValidation = (e) => {
        const value = e.target.value;
        if(/^\d{0,4}$/.test(value)){
            props.setOpenpayCardCvc(value);
        }
    }

    return (
        <div class="wc-block-components-text-input is-active" style={{flex: '1 0 calc(50% - 12px)'}}>
            <label for="openpay-card-cvc">CVV <span class="required">*</span></label>
            <input
                id="openpay-card-cvc"
                name="openpayCardCvc"
                class="input-text wc-credit-card-form-card-cvc openpay-card-input-cvc"
                value={props.openpayCardCvc}
                onChange={cardCvcInputValidation}
                type="password"
                autocomplete="off"
                placeholder="CVC"
                maxlength="4"
                data-openpay-card="cvv2"/>
        </div>
    );
}
export default cardCvcComponent;