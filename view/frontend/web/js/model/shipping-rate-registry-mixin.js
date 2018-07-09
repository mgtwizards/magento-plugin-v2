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

    return function (rateRegistry) {
        return $.extend(rateRegistry, {
            get: wrapper.wrap(rateRegistry.get, function (original, addressKey) {
                if (rateFilter.getRateCacheDisabled()()) {
                    return false;
                }

                return original(addressKey);
            })
        });
    };
});
