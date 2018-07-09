/*
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
define([
    'ko',
    'underscore',
    'porterbuddyConfig'
], function (ko, _, Porterbuddy) {
    var rateCacheDisabled = ko.observable(false);
    var porterbuddyRates = ko.observableArray();
    var otherRates = ko.observableArray();

    return {
        extractPorterbuddyRates: function (ratesData) {
            var result = _.partition(ratesData, function (rate) {
                return Porterbuddy.CARRIER_CODE === rate.carrier_code && rate.extension_attributes;
            });
            porterbuddyRates(result[0]);
            otherRates(result[1]);
        },

        getOtherRates: function () {
            return otherRates;
        },

        getPorterbuddyRates: function () {
            return porterbuddyRates;
        },

        getRateCacheDisabled: function () {
            return rateCacheDisabled;
        }
    };
});
