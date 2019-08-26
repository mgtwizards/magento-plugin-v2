define([
    'uiComponent',
    'jquery',
    'ko',
    'Magento_Checkout/js/model/quote',
    'mage/translate'
], function (Component, $, ko, quote) {
    return Component.extend({
        defaults: {
            enabled: true,
            template: 'Porterbuddy_Porterbuddy/address-warning',
            message: $.mage.__('Enter address to get rates'),
            closed: false
        },
        visible: false,

        initObservable: function () {
            this._super();
            this.observe('visible closed');
            return this;
        },

        initialize: function () {
            this._super();
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
                var address = quote.shippingAddress();
                var entered = address && address.postcode && address.postcode.length > 0;
                this.visible(!entered && !this.closed());
            }.bind(this));

        },

        close: function () {
            this.closed(true);
        },
    });
});
