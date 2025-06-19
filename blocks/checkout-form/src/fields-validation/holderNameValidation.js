export const HolderNameValidation = (openpayHolderName) => {

    // Validación de campo no vacío
    if(!openpayHolderName.length){
        return 'El campo de tarjetahabiente se encuentra vacío';
    }

    // Validación de texto sin números
    if (! /^[a-z ]+$/i.test(openpayHolderName.trim()) ) {
        return  'El campo de tarjetahabiente solo debe contener letras o espacios';
    }
}
