define([
    'underscore'
], function (_) {
    'use strict';

    var checkoutConfig = window.checkoutConfig.porterbuddy;
    var shippingRates = checkoutConfig.shippingRates;

    return {
        HOME_DELIVERY: 'home_delivery',
        PICKUP_POINT: 'pickup_point',
        COLLECT_IN_STORE: 'collect_in_store',
        processRate: function(rate){
            var configEntry = _.findWhere(shippingRates, {carrier_code: rate.carrier_code, rate_code: rate.method_code});
            if(!configEntry){
                configEntry = _.findWhere(shippingRates, {carrier_code: rate.carrier_code, rate_code: ""});
            }
            if(!configEntry){
                //no config for this option, so we don't show it
                return { showRate: false };
            }
            var type = configEntry.rate_type;
            var rateData =  {
                id: rate.carrier_code + '_' + rate.method_code,
                name: rate.method_title,
                price: {
                    fractionalDenomination: 100 * rate.amount,
                    currency: 'NOK'
                },
                minDeliveryDays: configEntry.min_delivery_days,
                maxDeliveryDays: configEntry.max_delivery_days,
                description: rate.carrier_title,
                additionalData: {
                    //the additional data fields below are required!
                    type: type,
                    methodCode: rate.method_code,
                    carrierCode: rate.carrier_code
                },
                showRate: true
            };
            if(configEntry.logo_url && configEntry.logo_url.length > 0)
                rateData.logoUrl = configEntry.logo_url;
            if(configEntry.description && configEntry.description.length > 0){
                rateData.description = configEntry.description
            }
            // var locations = this.getPickupLocations(rate, type);
            // if(locations.length > 0){
            //     rateData.locations = locations;
            // }
            return rateData;
        },
        selectRate: function(rate, location, type){
            //do any additional selection associated with pickup point etc.
            //location is in same format as sent via getPickupLocations
            return;
        },
        getPickupLocations: function(rate, type){
            var location = {
                id:    "id",  //A unique id for the shipping location
                name: "pickupPointName",  //Name of the location, to be displayed in the option card
                address:   "pickup Point address", //Address of the location, to be displayed in the option card
                openingHours: "9-5, M-F", //Opening hours of the location (arbitrary string)
                logoUrl: "" //A url to a logo image to be displayed for the location. Maximum displayable logo size is 70x37 px. The logo url can be specified as relative to the embedding host, or absolute. If a location with a logo isselected, the logo for the whole option is set to the one for the location
            };
            return [];
        },
        getRateType: function(rate){
            return this.PICKUP_POINT;
        }
    }
});