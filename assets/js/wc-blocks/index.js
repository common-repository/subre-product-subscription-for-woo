/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!**********************!*\
  !*** ./src/index.js ***!
  \**********************/
document.addEventListener('DOMContentLoaded', () => {
  const wc = window.wc;
  const {
    registerCheckoutFilters
  } = wc.blocksCheckout;
  const modifySubtotalPriceFormat = (defaultValue, extensions, args, validation) => {
    const isCartContext = args?.context === 'cart';
    let {
      subreSubscription = []
    } = extensions;
    if (!isCartContext || !Object.keys(subreSubscription).length) return defaultValue;
    let display = replacePlaceholder(subreSubscription);
    return `<price/> ${display}`;
  };
  const modifyCartItemPrice = (defaultValue, extensions, args, validation) => {
    const isCartContext = args?.context === 'cart' || args?.context === 'summary';
    let {
      subreSubscription = []
    } = extensions;
    if (!isCartContext || !Object.keys(subreSubscription).length) return defaultValue;
    let display = replacePlaceholder(subreSubscription);
    if (subreSubscription?.first_renew) display += ". " + subreSubscription.first_renew;
    return `<price/> ${display}`;
  };
  registerCheckoutFilters('subre', {
    subtotalPriceFormat: modifySubtotalPriceFormat,
    cartItemPrice: modifyCartItemPrice
  });
  const replacePlaceholder = args => {
    let {
      display = '',
      subscription_price = '',
      subscription_period = '',
      trial_period = '',
      sign_up_fee = '',
      expiry = ''
    } = args;
    display = display.replace('{subscription_price}', '');
    display = display.replace('{subscription_period}', subscription_period);
    display = display.replace('{sign_up_fee}', wc.priceFormat.formatPrice(sign_up_fee));
    display = display.replace('{trial_period}', trial_period);
    if (expiry) display += expiry;
    return display;
  };
});
/******/ })()
;
//# sourceMappingURL=index.js.map