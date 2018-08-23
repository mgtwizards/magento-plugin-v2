/*
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
define([
    'jquery',
    'mage/utils/wrapper',
    'uiRegistry'
], function ($, wrapper, registry) {
    'use strict';

    return function (checkout) {
        // detect Klarna checkout and call kcoShippingMethod instead of regular selectShippingMethod
        return checkout.extend({
            selectShippingMethod: function (shippingMethod) {
                var _super = this._super;
                if (registry.has('checkout.steps.klarna_kco')) {
                    require(['Klarna_Kco/js/action/select-shipping-method'], function (kcoShippingMethod) {
                        kcoShippingMethod(shippingMethod);
                    }, function () {
                        // not found, fall back
                        _super(shippingMethod);
                    });
                } else {
                    _super(shippingMethod);
                }
            }
        });
    };
});
