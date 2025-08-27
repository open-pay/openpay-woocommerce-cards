
const cardExpiryComponent = (props ) => {

    const cardExpiryInputValidation = (e) => {
        jQuery('.wc-credit-card-block-form-card-expiry').payment('formatCardExpiry');
        const value = e.target.value;
        //if(/^\d{0,4}$/.test(value)){
            props.setOpenpayCardExpiry(value);
        //}


    }

    return (
        <div class="wc-block-components-text-input is-active" style={{flex: '1 0 calc(50% - 12px)'}}>
            <label for="openpay-card-expiry">Expira (MM/AA) <span class="required">*</span></label>
            <input
                id="openpay-card-expiry"
                name="openpayCardExpiry"
                class="input-text wc-credit-card-block-form-card-expiry"
                value={props.openpayCardExpiry}
                onChange={cardExpiryInputValidation}
                type="text"
                autocomplete="off"
                placeholder="MM / AA"
                maxlength="7"
                data-openpay-card="expiration_year"/>
        </div>
    );
}
export default cardExpiryComponent;