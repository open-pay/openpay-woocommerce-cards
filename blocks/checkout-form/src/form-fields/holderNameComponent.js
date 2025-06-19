
const holderNameComponent = ( props ) => {

const holderNameInputValidation = (e) => {
    const value = e.target.value;
    if( /^[a-z ]+$/i.test(value) || value === "" ){
        props.setOpenpayHolderName(value);
    }
}

    return (
        <div className="wc-block-components-text-input is-active" style={{flex: '0 0 100%'}}>
            <input
                id="openpay-holder-name"
                name="openpayHolderName"
                value={props.openpayHolderName}
                onChange={holderNameInputValidation}
                type="text"
                autoComplete="off"
                placeholder="Nombre del tarjetahabiente"
                data-openpay-card="holder_name"/>
            <label htmlFor="openpay-holder-name">Nombre del títular
                <span className="required">*</span>
            </label>
        </div>
    );

}



export default holderNameComponent;