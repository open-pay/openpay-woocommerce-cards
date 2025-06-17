export const HolderNameValidation = (openpayHolderName) => {

    // Validación de campo no vacío
    if(!openpayHolderName.length){
        return 'El campo de tarjetahabiente se encuentra vacío';
    }

    // Validación de texto sin números
    if ( /\d/.test(openpayHolderName.trim()) ) {
        return  'El campo de tarjetahabiente no debe contener números';
    }
}
