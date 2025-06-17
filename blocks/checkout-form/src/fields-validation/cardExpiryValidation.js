export const CardExpiryValidation = (openpayCardExpiry) => {

    // Validación de campo no vacío
    if(!openpayCardExpiry.length){
        return 'El campo de fecha de expiración de tarjeta se encuentra vacío';
    }

    if(openpayCardExpiry.length < 4){
        return 'Número de digitos de fecha de expiración de tarjeta incorrectos';
    }

    // Validación de texto solo números
    if ( /^[0-9]+$/.test(openpayCardExpiry.trim()) ) {
        message: 'La fecha de expiración de tarjeta solo debe contener números';
    }
}