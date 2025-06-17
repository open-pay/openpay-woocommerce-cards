export const CardNumberValidation = (openpayCardNumber) => {

    // Validación de campo no vacío
    if(!openpayCardNumber.length){
        return 'El campo de número de tarjeta se encuentra vacío';
    }

    if(openpayCardNumber.length < 16){
        return 'Numero de digitos de tarjeta incorrectos';
    }

    // Validación de texto solo números
    if ( /^[0-9]+$/.test(openpayCardNumber.trim()) ) {
            message: 'El campo de número de tarjeta solo debe contener números';
    }
}
