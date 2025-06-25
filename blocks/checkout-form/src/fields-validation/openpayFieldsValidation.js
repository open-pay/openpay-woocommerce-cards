import {HolderNameValidation} from "./holderNameValidation";
import {CardNumberValidation} from "./cardNumberValidation";
import {CardExpiryValidation} from "./cardExpiryValidation";
import {CardCvcValidation} from "./cardCvcValidation";

export const OpenpayFieldsValidation = (openpayHolderName,openpayCardNumber,openpayCardExpiry, openpayCardCvc) => {

    const holderNameError = HolderNameValidation(openpayHolderName);
    if(holderNameError){return holderNameError}

    const cardNumberError = CardNumberValidation(openpayCardNumber);
    if(cardNumberError){return cardNumberError}

    const cardExpiryError = CardExpiryValidation(openpayCardExpiry);
    if(cardExpiryError){return cardExpiryError}

    const cardCvcError = CardCvcValidation(openpayCardCvc);
    if(cardCvcError){return cardCvcError}

}
