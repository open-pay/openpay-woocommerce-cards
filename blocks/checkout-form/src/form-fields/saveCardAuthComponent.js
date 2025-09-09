
const saveCardAuthComponent = ( props ) => {

    const saveCardAuthValidation = (e) => {
            props.setOpenpaySaveCardAuth(!props.openpaySaveCardAuth);
    }

    return (
        <div className="wc-block-components-checkbox"><label htmlFor="openpay-save-card-auth">
            <input
                id="openpay-save-card-auth"
                name="openpaySaveCardAuth"
                class="wc-block-components-checkbox__input"
                value={props.openpaySaveCardAuth}
                onChange={saveCardAuthValidation}
                type="checkbox"
                aria-invalid="false"/>
            <svg class="wc-block-components-checkbox__mark" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                 viewBox="0 0 24 20">
                <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"></path>
            </svg>
            <span class="wc-block-components-checkbox__label" style={{fontSize: '1.25em'}}>Guardar Tarjeta</span></label>
        </div>
    );
}
export default saveCardAuthComponent;