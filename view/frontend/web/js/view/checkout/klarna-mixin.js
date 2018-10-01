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
                if (registry.has('checkout.steps.klarna_kco')) {
                    var _super = this._super;
                    require(['Klarna_Kco/js/action/select-shipping-method'], function (kcoShippingMethod) {
                        kcoShippingMethod(shippingMethod);
                    }, function () {
                        // not found, fall back
                        _super(shippingMethod);
                    });
                } else {
                    this._super(shippingMethod);
                }
            }
        });
    };
});
