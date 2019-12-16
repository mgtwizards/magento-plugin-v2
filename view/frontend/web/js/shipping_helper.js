define([
    ], function () {
    'use strict';



    return {

        HOME_DELIVERY: 'home_delivery',
        PICKUP_POINT: 'pickup_point',
        COLLECT_IN_STORE: 'collect_in_store',

        processRate: function(rate){
            var type = this.getRateType(rate);

            var rateData =  {
                id: rate.carrier_code,
                name: rate.carrier_title,
                price: {
                    fractionalDenomination: 100 * rate.amount,
                    currency: 'NOK'
                },
                minDeliveryDays: 2,
                maxDeliveryDays: 5,
                //logoUrl: "",
                //description: "",
                additionalData: {
                    //the additional data fields below are required!
                    type: type,
                    methodCode: rate.method_code,
                    carrierCode: rate.carrier_code
                },
                showRate: true
            };
            var locations = this.getPickupLocations(rate, type);
            if(locations.length > 0){
                rateData.locations = locations;
            }

            return rateData;

        },

        selectRate(rate, location, type){
            //do any additional selection associated with pickup point etc.
            //location is in same format as sent via getPickupLocations
          return;
        },

        getPickupLocations(rate, type){
            var location = {
                id:	"id",  //A unique id for the shipping location
                name: "pickupPointName",  //Name of the location, to be displayed in the option card
                address:	"pickup Point address", //Address of the location, to be displayed in the option card
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
