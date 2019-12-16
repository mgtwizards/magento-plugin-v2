/*
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
define([
    'ko',
    'underscore',
    'porterbuddyConfig',
    'porterbuddyShippingHelper',
], function (ko, _, Porterbuddy, pbShippingHelper) {
    var rateCacheDisabled = ko.observable(false);
    var groupedRates = ko.observableArray();

    return {
        extractRates: function (ratesData) {
            var result = _.groupBy(ratesData, function(rate){
                if(Porterbuddy.CARRIER_CODE === rate.carrier_code && rate.extension_attributes){
                    return 'porterbuddy';
                }
                return 'other';
            });
            groupedRates(result);
        },

        getGroupedRates: function(){
            return groupedRates;
        },

        getRateCacheDisabled: function () {
            return rateCacheDisabled;
        }
    };
});
