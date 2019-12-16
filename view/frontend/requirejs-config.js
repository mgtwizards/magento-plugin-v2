/*
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
var config = {
    map: {
        '*': {
            porterbuddyConfig: 'Porterbuddy_Porterbuddy/js/config',
            porterbuddyCheckoutWidget: 'Porterbuddy_Porterbuddy/js/view/checkout',
            porterbuddyRateFilter: 'Porterbuddy_Porterbuddy/js/model/rate-filter',
            porterbuddyAvailabilityWidget: 'Porterbuddy_Porterbuddy/js/availability',
            porterbuddyShippingModule: 'Porterbuddy_Porterbuddy/js/shipping',
            porterbuddyShippingHelper: 'Porterbuddy_Porterbuddy/js/shipping_helper',
            porterbuddyShippingKlarnaMobileWrapper: 'Porterbuddy_Porterbuddy/js/view/shipping-klarna-mobile-wrapper.js'
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/model/shipping-service': {
                'Porterbuddy_Porterbuddy/js/model/shipping-service-mixin': true
            },
            'Magento_Checkout/js/model/shipping-rate-registry': {
                'Porterbuddy_Porterbuddy/js/model/shipping-rate-registry-mixin': true
            },
            'Magento_Checkout/js/checkout-data': {
                'Porterbuddy_Porterbuddy/js/checkout-data-mixin': true
            },
            'Klarna_Kco/js/view/shipping-method':{
                'Porterbuddy_Porterbuddy/js/klarna-shipping-mixin': true
            }
        }
    }
};
