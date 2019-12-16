define([
    'uiComponent',
    'jquery',
    'ko',
    'uiRegistry',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/create-shipping-address',
    'Magento_Checkout/js/action/select-shipping-address',
    'Magento_Checkout/js/model/postcode-validator'
], function (Component, $, ko, reg, quote, createShippingAddress, selectShippingAddress, postcodeValidator) {

    var checkoutConfig = window.checkoutConfig.porterbuddy;

    return Component.extend({
        defaults: {
            enabled: true,
            template: 'Porterbuddy_Porterbuddy/address-warning',
            message: checkoutConfig.addressWarningText,
            closeLabel: checkoutConfig.addressWarningCloseLabel,
            header: checkoutConfig.addressWarningTitle,
            closed: false,
            postCode: ko.observable("")
        },
        visible: false,

        initObservable: function () {
            this._super();
            this.observe('visible closed');
            this.postCode.subscribe(_.debounce(function(postCode){
                if(postcodeValidator.validate(postCode, 'NO')) {
                    reg.get('checkout.steps.shipping-step.shippingAddress.shipping-address-fieldset.postcode').value(postCode)
                }
            }, 300));
            return this;
        },

        initialize: function () {
            this._super();
            var address = quote.shippingAddress();
            if(address && address.postcode){
                postCode(address.postcode);
            }
            this.initVisibility();
            return this;
        },

        initVisibility: function () {
            if (this.enabled) {

                quote.shippingAddress.subscribe(this.setVisibility.bind(this));
            }
        },

        setVisibility: function () {
            ko.computed(function () {
                // noinspection ES6ConvertVarToLetConst
                var address = quote.shippingAddress();
                // noinspection ES6ConvertVarToLetConst
                var entered = address && address.postcode && address.postcode.length > 0;
                this.visible(!entered && !this.closed());
                if(address && address.postcode){
                    this.postCode(address.postcode);
                }
            }.bind(this));

        },

        close: function () {
            this.closed(true);
        },
    });
});
