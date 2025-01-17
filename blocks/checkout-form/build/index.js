/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./blocks/checkout-form/src/form.js":
/*!******************************************!*\
  !*** ./blocks/checkout-form/src/form.js ***!
  \******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/html-entities */ "@wordpress/html-entities");
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);



const {
  getSetting
} = window.wc.wcSettings;
const settings = getSetting('wc_openpay_gateway_data', {});
const label = (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__.decodeEntities)(settings.title);
const Form = props => {
  const {
    eventRegistration,
    emitResponse,
    billing
  } = props;
  const {
    onPaymentSetup
  } = eventRegistration;
  const [openpayHolderName, setOpenpayHolderName] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [openpayCardNumber, setOpenpayCardNumber] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [openpayCardExpiry, setOpenpayCardExpiry] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [openpayCardCvc, setOpenpayCardCvc] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  var openpayToken = '';
  var openpayTokenizedCard = '';
  const tokenRequest = async () => {
    var card = openpayCardNumber;
    var cvc = openpayCardCvc;
    var expires = openpayCardExpiry;
    console.log(settings);
    console.log(settings.title);
    console.log(settings.merchantId);
    console.log(settings.publicKey);
    console.log(card + ' - ' + cvc + ' - ' + expires);
    var data = {
      holder_name: openpayHolderName,
      card_number: openpayCardNumber,
      cvv2: openpayCardCvc,
      expiration_month: openpayCardExpiry.substring(0, 2),
      expiration_year: openpayCardExpiry.substring(2),
      address: {
        line1: billing.billingAddress.address_1,
        line2: billing.billingAddress.address_2,
        state: billing.billingAddress.state,
        city: billing.billingAddress.city,
        postal_code: billing.billingAddress.postcode,
        country_code: billing.billingAddress.country
      }
    };
    console.log(data);
    const result = await tokenRequestWrapper(data);
    openpayToken = result.data.id;
    openpayTokenizedCard = result.data.card.card_number;
  };
  const tokenRequestWrapper = data => {
    return new Promise((resolve, reject) => {
      OpenPay.token.create(data, successResponse => {
        resolve(successResponse);
      }, errorResponse => {
        reject(errorResponse);
      });
    });
  };
  (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    const unsubscribe = onPaymentSetup(async () => {
      //console.log('onPaymentSetup_openpayHolderName - ' + openpayHolderName);
      //console.log('onPaymentSetup_deviceSessionId - ' + deviceSessionId);
      //console.log('onPaymentSetup_CARD - ' + card );
      console.log('Billing - ' + JSON.stringify(billing));
      //console.log('Billing - ' + billing.billingAddress.first_name);

      if (openpayHolderName.length) {
        await tokenRequest();
        console.log('after token request');
        if (openpayToken.length) {
          return {
            type: emitResponse.responseTypes.SUCCESS,
            meta: {
              paymentMethodData: {
                openpay_token: openpayToken,
                openpay_tokenized_card: openpayTokenizedCard,
                device_session_id: deviceSessionId
              }
            }
          };
        }
      }
      return {
        type: emitResponse.responseTypes.ERROR,
        message: 'There was an error'
      };
    });
    // Unsubscribes when this component is unmounted.
    return () => {
      unsubscribe();
    };
  }, [emitResponse.responseTypes.ERROR, emitResponse.responseTypes.SUCCESS,
  //billing,
  onPaymentSetup, openpayHolderName, openpayCardNumber, openpayCardExpiry, openpayCardCvc]);

  //return decodeEntities( Form || '' );
  //return Form;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
    id: "payment_form_openpay_cards",
    style: {
      marginBottom: '20px',
      display: 'flex',
      flexWrap: 'wrap',
      gap: '0 16px',
      justifyContent: 'space-between'
    },
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
      class: "wc-block-components-text-input is-active",
      style: {
        flex: '0 0 100%'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("input", {
        id: "openpay-holder-name",
        name: "openpayHolderName",
        value: openpayHolderName,
        onChange: e => setOpenpayHolderName(e.target.value),
        type: "text",
        autocomplete: "off",
        placeholder: "Nombre del tarjetahabiente",
        "data-openpay-card": "holder_name"
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("label", {
        for: "openpay-holder-name",
        children: ["Nombre del t\xEDtular", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("span", {
          class: "required",
          children: "*"
        })]
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
      class: "wc-block-components-text-input is-active",
      style: {
        flex: '0 0 100%'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("label", {
        for: "openpay-card-number",
        children: ["N\xFAmero de tarjeta ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("span", {
          class: "required",
          children: "*"
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("input", {
        id: "openpay-card-number",
        name: "openpayCardNumber",
        class: "wc-credit-card-form-card-number",
        value: openpayCardNumber,
        onChange: e => setOpenpayCardNumber(e.target.value),
        type: "text",
        maxlength: "20",
        autocomplete: "off",
        placeholder: "\u2022\u2022\u2022\u2022 \u2022\u2022\u2022\u2022 \u2022\u2022\u2022\u2022 \u2022\u2022\u2022\u2022",
        "data-openpay-card": "card_number"
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
      class: "wc-block-components-text-input is-active",
      style: {
        flex: '1 0 calc(50% - 12px)'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("label", {
        for: "openpay-card-expiry",
        children: ["Expira (MM/AA) ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("span", {
          class: "required",
          children: "*"
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("input", {
        id: "openpay-card-expiry",
        name: "openpayCardExpiry",
        class: "input-text wc-credit-card-form-card-expiry",
        value: openpayCardExpiry,
        onChange: e => setOpenpayCardExpiry(e.target.value),
        type: "text",
        autocomplete: "off",
        placeholder: "MM / AA",
        maxlength: "4",
        "data-openpay-card": "expiration_year"
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
      class: "wc-block-components-text-input is-active",
      style: {
        flex: '1 0 calc(50% - 12px)'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("label", {
        for: "openpay-card-cvc",
        children: ["CVV ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("span", {
          class: "required",
          children: "*"
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("input", {
        id: "openpay-card-cvc",
        name: "openpayCardCvc",
        class: "input-text wc-credit-card-form-card-cvc openpay-card-input-cvc",
        value: openpayCardCvc,
        onChange: e => setOpenpayCardCvc(e.target.value),
        type: "password",
        autocomplete: "off",
        placeholder: "CVC",
        maxlength: "4",
        "data-openpay-card": "cvv2"
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
      class: "wc-block-components-text-input is-active",
      style: {
        marginBottom: '20px'
      },
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("label", {
        for: "save_cc",
        class: "label",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
          class: "tooltip",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("input", {
            type: "checkbox",
            name: "save_cc",
            id: "save_cc"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("span", {
            children: "Guardar tarjeta"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("img", {
            alt: "",
            src: "<?php echo $this->images_dir ?>tooltip_symbol.svg"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("span", {
            class: "tooltiptext",
            children: "Al guardar los datos de tu tarjeta agilizar\xE1s tus pagos futuros y podr\xE1s usarla como m\xE9todo de pago guardado."
          })]
        })
      })
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Form);

/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = window["React"];

/***/ }),

/***/ "react/jsx-runtime":
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["ReactJSXRuntime"];

/***/ }),

/***/ "@wordpress/html-entities":
/*!**************************************!*\
  !*** external ["wp","htmlEntities"] ***!
  \**************************************/
/***/ ((module) => {

module.exports = window["wp"]["htmlEntities"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!*******************************************!*\
  !*** ./blocks/checkout-form/src/index.js ***!
  \*******************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/html-entities */ "@wordpress/html-entities");
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _form__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./form */ "./blocks/checkout-form/src/form.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);
//import { registerPaymentMethod } from '@woocommerce/blocks-registry';



const {
  registerPaymentMethod
} = window.wc.wcBlocksRegistry;
const {
  getSetting
} = window.wc.wcSettings;
const settings = getSetting('wc_openpay_gateway_data', {});

//const label = decodeEntities( settings.title )
const label = (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_0__.decodeEntities)('Openpay Cards');

/*
const Content = () => {
	return decodeEntities( settings.description || '' )
}
*/

const Label = props => {
  const {
    PaymentMethodLabel
  } = props.components;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(PaymentMethodLabel, {
    text: label
  });
};
registerPaymentMethod({
  name: "wc_openpay_gateway",
  label: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(Label, {}),
  content: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(_form__WEBPACK_IMPORTED_MODULE_1__["default"], {}),
  edit: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(_form__WEBPACK_IMPORTED_MODULE_1__["default"], {}),
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
    features: settings.supports
  }
});
})();

/******/ })()
;
//# sourceMappingURL=index.js.map