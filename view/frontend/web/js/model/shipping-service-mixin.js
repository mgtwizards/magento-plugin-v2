/*
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */

/*jshint browser:true jquery:true*/
/*global alert*/
define([
    'jquery',
    'mage/utils/wrapper',
    'porterbuddyRateFilter'
], function ($, wrapper, rateFilter) {
    'use strict';

    return function (shippingService) {
        var rates = shippingService.getShippingRates();

        return $.extend(shippingService, {
            setShippingRates: wrapper.wrap(shippingService.setShippingRates, function (original, ratesData) {
                // split Porterbuddy and other rates
                rateFilter.extractPorterbuddyRates(ratesData);
                // run original checkout-data-resolver.resolveShippingRates to check selected shipping method
                original(ratesData);

                // hide Porterbuddy from visible rates list
                rates(rateFilter.getOtherRates()());
            })
        });
    };
});
