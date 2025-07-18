import {useRef} from 'react'
const holderNameComponent = ( props ) => {
    
    const inputRef = useRef(null);

    const cardNumberInputValidation = (e) => {
        const value = e.target.value;
        if(/^\d{0,16}$/.test(value)){
            props.setOpenpayCardNumber(value);
        }
    }

    const handleKeyDown = (e) => {
            const input = inputRef.current;
            const cursorAtEnd = input.selectionStart === input.value.length;

            // Bloquear flechas hacia la izquierda y mover el cursor si no está al final
            if (!cursorAtEnd && ['ArrowLeft', 'ArrowUp', 'ArrowRight', 'Backspace', 'Delete'].includes(e.key)) {
                e.preventDefault();
            }

            // Bloquear cualquier escritura si el cursor no está al final
            if (!cursorAtEnd && e.key.length === 1) {
                e.preventDefault();
            }
        }

    return (
        <div className="wc-block-components-text-input is-active" style={{flex: '0 0 100%'}}>
            <label for="test-openpay-card-number">Número de tarjeta <span className="required">*</span></label>
            <input
                id="openpay-card-number"
                name="openpayCardNumber"
                className="wc-credit-card-block-form-card-number"
                value={props.openpayCardNumber}
                ref={inputRef}
                onChange={cardNumberInputValidation}
                onKeyDown={handleKeyDown}
                type="text"
                maxLength="16"
                autoComplete="off"
                placeholder="•••• •••• •••• ••••"
                data-openpay-card="card_number"/>
        </div>
    );

}


export default holderNameComponent;