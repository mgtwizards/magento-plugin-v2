define([
    'uiComponent',
    'jquery',
], function (Component, $) {


    return Component.extend({
        defaults: {
            template: 'Porterbuddy_Porterbuddy/shipping-klarna-mobile-wrapper'
        },

        initialize(){
            this._super();
        }
    });
});