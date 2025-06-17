
const holderNameComponent = ( props ) => {

    const cardNumberInputValidation = (e) => {
        const value = e.target.value;
        if(/^\d{0,16}$/.test(value)){
            props.setOpenpayCardNumber(value);
            console.log(props.openpayCardNumber.length)
        }
    }

    return (
        <div className="wc-block-components-text-input is-active" style={{flex: '0 0 100%'}}>
            <label htmlFor="openpay-card-number">Número de tarjeta <span className="required">*</span></label>
            <input
                id="openpay-card-number"
                name="openpayCardNumber"
                className="wc-credit-card-form-card-number"
                value={props.openpayCardNumber}
                onChange={cardNumberInputValidation}
                type="text"
                maxLength="16"
                autoComplete="off"
                placeholder="•••• •••• •••• ••••"
                data-openpay-card="card_number"/>
        </div>
    );

}


export default holderNameComponent;