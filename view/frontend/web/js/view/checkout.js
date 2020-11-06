/*
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
define([
    'jquery',
    'ko',
    'underscore',
    'uiComponent',
    'porterbuddyConfig',
    'porterbuddyRateFilter',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/action/select-shipping-method',
    'Magento_Checkout/js/checkout-data',
    'mage/url',
    'Magento_Catalog/js/price-utils',
    'mage/cookies'
], function (
    $,
    ko,
    _,
    Component,
    Porterbuddy,
    rateFilter,
    quote,
    shippingService,
    selectShippingMethodAction,
    checkoutData,
    mageUrl,
    priceUtils
) {
    'use strict';

    var checkoutConfig = window.checkoutConfig.porterbuddy;


    return Component.extend({
        defaults: {
            template: 'Porterbuddy_Porterbuddy/checkout/widget',
            title: checkoutConfig.title,
            description: checkoutConfig.description,
            leaveDoorstepEnabled: checkoutConfig.leaveDoorstepEnabled,
            leaveDoorstepText: checkoutConfig.leaveDoorstepText,
            returnEnabled: checkoutConfig.returnEnabled,
            commentText: checkoutConfig.commentText,
            refreshOptionsTimeout: checkoutConfig.refreshOptionsTimeout,
            leaveDoorstep: checkoutConfig.leaveDoorstep,
            comment: checkoutConfig.comment,
            publicKey: checkoutConfig.publicKey,
            apiMode: checkoutConfig.apiMode,
            discount: checkoutConfig.discount
        },
        formKey: $.mage.cookies.get('form_key'),
        timer: null,
        internal: false,
        selectedDate: null,
        refreshIntervalId: null,
        availabilityResponse: null,
        shippingTable: null,

        // observable
        visible: ko.observable(false),
        isReturnSelected: ko.observable(false),
        hasReturnOther: ko.observable(true),
        selectedDateLabel: ko.observable(),
        timeslots: ko.observableArray(),
        prevDateAvailable: ko.observable(false),
        nextDateAvailable: ko.observable(false),

        initialize: function () {
            this._super();
            window.pbHasWindows = ko.observable(false);

            rateFilter.getGroupedRates().subscribe(function (rates) {
                this.processNewRates(rates);

                if(window.updateDeliveryWindows) {
                    window.updateDeliveryWindows(this.availabilityResponse);
                }
            }.bind(this));

            quote.shippingMethod.subscribe(function (shippingMethod) {
                if (!this.internal) {
                    this.selectRate(shippingMethod);
                }
            }.bind(this));





            return this;
        },

        initObservable: function () {
            this._super();
            this.observe('leaveDoorstep comment');

            return this;
        },


        initWidget: function(){


            window.porterbuddy = {
                token: checkoutConfig.publicKey,
                apiMode: checkoutConfig.apiMode,
                view: 'checkout',
                availabilityResponse: this.availabilityResponse,
                updateDeliveryWindowsInterval: checkoutConfig.refreshOptionsTimeout*60,
                discount: this.discount * 100,
                showLeaveAtDoorstep: this.leaveDoorstepEnabled,
                onSelectDeliveryWindow: function(selectedWindow){
                    if(selectedWindow){
                        var timeslot = _.find(this.timeslotsByValue, function(timeslot){
                            return selectedWindow.product == timeslot().type && selectedWindow.start == timeslot().start && selectedWindow.end == timeslot().end;
                        });
                        if(timeslot) {
                            this.internal = true;
                            this.selectShippingMethod(timeslot().method);
                            this.internal = false;
                            window.$previousSelectedTimeslot = timeslot;
                            $('#s_method_porterbuddy').prop('checked', true);
                        }
                    }
                }.bind(this),
                onUpdateDeliveryWindows: function(callback, additionalInfo) {
                    jQuery.ajax(mageUrl.build('porterbuddy/delivery/timeslots'), {
                        type: 'post',
                        dataType: 'json',
                        data: {
                            form_key: $.mage.cookies.get('form_key'),
                            additional_info: additionalInfo,
                            refresh: true
                        }
                    })
                    .done(function(data) {
                        if(data.error){
                            console.error(data.message);
                        }else {
                            callback(data.timeslots);
                        }
                    });
                },
                onSetCallbacks: function(callbacks) {
                    window.pbUnselectDeliveryWindow = callbacks.unselectDeliveryWindow;
                    window.pbSetSelectedDeliveryWindow = callbacks.setSelectedDeliveryWindow;
                    window.pbForceRefresh = callbacks.forceRefresh;
                    window.updateDeliveryWindows = callbacks.updateDeliveryWindows;
                },
                onComment: function( comment ){

                    //send, check response.error
                    jQuery.ajax(mageUrl.build('porterbuddy/delivery/options'), {
                        type: 'post',
                        dataType: 'json',
                        data: {
                            form_key: $.mage.cookies.get('form_key'),
                            comment: comment,
                            type: 'comment'
                        }
                    }).done(function (data) {
                        if (data.error) {
                            console.error(data.message);
                        } else {
                            return;
                        }
                    }).fail(function () {
                        console.error("error saving note to courier");
                    });

                    return;

                },
                onLeaveAtDoorstep: function( leaveAtDoorstep ){
                    jQuery.ajax(mageUrl.build('porterbuddy/delivery/options'), {
                        type: 'post',
                        dataType: 'json',
                        data: {
                            form_key: $.mage.cookies.get('form_key'),
                            leave_doorstep: leaveAtDoorstep,
                            type: 'doorstep'
                        }
                    }).done(function (data) {
                        if (data.error) {
                            console.error(data.message);
                        } else {
                            return;
                        }
                    }).fail(function () {
                        console.error("error saving leave at doorstep");
                    });

                    return;
                },
                text: {
                    comment: checkoutConfig.commentText != null?checkoutConfig.commentText:undefined,
                    leaveAtDoorstep: checkoutConfig.leaveDoorstepText != null?checkoutConfig.leaveDoorstepText:undefined

                }

            };
            if (window.$previousSelectedTimeslot != null){
                window.porterbuddy.initialSelectedWindow = {'product': window.$previousSelectedTimeslot().product, 'start': window.$previousSelectedTimeslot().start, 'end': window.$previousSelectedTimeslot().end}
            }else{
                if(this.availabilityResponse && this.availabilityResponse.deliveryWindows && this.availabilityResponse.deliveryWindows > 0){
                    window.porterbuddy.initialSelectedWindow = this.availabilityResponse.deliveryWindows[0]
                }
            }

        },


        refresh: function () {
            rateFilter.getRateCacheDisabled()(true);
            // see shipping-rate-service
            quote.shippingAddress.valueHasMutated();
            rateFilter.getRateCacheDisabled()(false);
        },

        processNewRates: function (rates) {
            if (!rates.porterbuddy || !rates.porterbuddy.length) {
                this.visible(false);
                return;
            }
            this.availabilityResponse = JSON.parse(rates.porterbuddy[0].extension_attributes.porterbuddy_info.windows);
            if(!window.porterbuddy){
                this.initWidget();
            }
            window.pbHasWindows(true);

            this.timeslotsByValue = {};

            _.each(rates.porterbuddy, function (rate) {
                var code = rate.carrier_code + '_' + rate.method_code;
                var timeslot = _.extend({}, rate.extension_attributes.porterbuddy_info, {
                    value: code,
                    price: priceUtils.formatPrice(rate.price_incl_tax, quote.getPriceFormat()), // TODO: incl/excl tax
                    method: rate
                });
                this.timeslotsByValue[code] = ko.observable(timeslot);
            }.bind(this));



            if (quote.shippingMethod()) {
                this.selectRate(quote.shippingMethod());
            }

            this.visible(true);
        },

        selectPorterbuddy: function () {
            var timeslot;
            if(window.$previousSelectedTimeslot != null ) {
                timeslot = window.$previousSelectedTimeslot;
            }else {

                timeslot = _.values(this.timeslotsByValue)[0];
            }
            window.pbSetSelectedDeliveryWindow({'product': timeslot().type, 'start': timeslot().start, 'end': timeslot().end});
            this.selectShippingMethod(timeslot().method);

            return true;
        },


        isPorterbuddyRate: function (shippingMethod) {
            if (!shippingMethod) {
                return false;
            }

            var value = shippingMethod.carrier_code + '_' + shippingMethod.method_code;
            var exp = new RegExp('^' + Porterbuddy.CARRIER_CODE + '_');
            return exp.test(value);
        },





        /**
         * Native shipping rate updated -> select according widget date and timeslot
         * @api
         */
        selectRate: function (shippingMethod) {
            if (!this.timeslotsByValue) {
                // no Porterbuddy timeslots
                return;
            }

            if (!this.isPorterbuddyRate(shippingMethod)) {
                $('#s_method_porterbuddy').prop('checked', false);
                if(window.pbUnselectDeliveryWindow) {
                    window.pbUnselectDeliveryWindow();
                }

                return;
            }

            var value = shippingMethod.carrier_code + '_' + shippingMethod.method_code;
            if (value in this.timeslotsByValue) {
                var timeslot = this.timeslotsByValue[value];
                if(window.pbSetSelectedDeliveryWindow) {
                    window.pbSetSelectedDeliveryWindow({
                        'product': timeslot().type,
                        'start': timeslot().start,
                        'end': timeslot().end
                    });
                }
                window.$previousSelectedTimeslot = timeslot;
                $('#s_method_porterbuddy').prop('checked', true);

            }
        },

        /**
         * @param {Object} shippingMethod
         * @return {Boolean}
         */
        selectShippingMethod: function (shippingMethod) {
            selectShippingMethodAction(shippingMethod);
            checkoutData.setSelectedShippingRate(shippingMethod['carrier_code'] + '_' + shippingMethod['method_code']);

            return true;
        },

    });
});
