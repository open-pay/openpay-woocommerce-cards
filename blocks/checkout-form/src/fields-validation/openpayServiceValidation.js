

export const OpenpayServiceValidation = (errorCode) => {

    var msg = "";
    switch (errorCode) {
        case 1000:
            msg = "Servicio no disponible.";
            break;

        case 1001:
            msg = "Los campos no tienen el formato correcto, o la petición no tiene campos que son requeridos.";
            break;

        case 1004:
            msg = "Servicio no disponible.";
            break;

        case 1005:
            msg = "Servicio no disponible.";
            break;

        case 2004:
            msg = "El dígito verificador del número de tarjeta es inválido de acuerdo al algoritmo Luhn.";
            break;

        case 2005:
            msg = "La fecha de expiración de la tarjeta es anterior a la fecha actual.";
            break;

        case 2006:
            msg = "El código de seguridad de la tarjeta (CVV2) no fue proporcionado o es incorrecto";
            break;

        case 1:
            msg = "El nombre del titular de la tarjeta no fue proporcionado o tiene un formato inválido.";
            break;

        default: //Demás errores 400
            msg = "La petición no pudo ser procesada.";
            break;
    }
    return msg;

}