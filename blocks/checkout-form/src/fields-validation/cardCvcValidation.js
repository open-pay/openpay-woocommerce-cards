export const CardCvcValidation = (openpayCardCvc) => {

    // Validación de campo no vacío
    if(!openpayCardCvc.length){
        return 'El campo de CVV se encuentra vacío';
    }

    if(openpayCardCvc.length < 3){
        return 'Número de digitos de CVV incorrectos';
    }

    // Validación de texto solo números
    if ( /^[0-9]+$/.test(openpayCardCvc.trim()) ) {
        message: 'El CVV solo debe contener números';
    }
}