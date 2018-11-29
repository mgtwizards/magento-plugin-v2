/*
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
/*jshint browser:true jquery:true*/
/*global alert*/
define([
    'jquery',
    'mage/utils/wrapper',
    'porterbuddyConfig',
    'mage/cookies'
], function ($, wrapper, Porterbuddy) {
    'use strict';

    var checkoutConfig = window.checkoutConfig.porterbuddy;

    return function (checkoutData) {
        if (!checkoutConfig.preselectLocation) {
            return checkoutData;
        }

        return $.extend(checkoutData, {
            getShippingAddressFromData: wrapper.wrap(checkoutData.getShippingAddressFromData, function (original) {
                var formData = original();
                var location = Porterbuddy.getCachedLocation();
                if (!location) {
                    return formData;
                }

                if (!formData) {
                    formData = {};
                }

                formData.postcode = formData.postcode || location.postcode;
                formData.city = formData.city || location.city;

                return formData;
            })
        });
    };
});
