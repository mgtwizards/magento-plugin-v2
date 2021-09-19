define([
    'jquery',
    'Magento_Checkout/js/view/shipping',
    'underscore',
    'ko',
    'uiRegistry',
    'porterbuddyConfig',
    'porterbuddyRateFilter',
    'porterbuddyShippingHelper',
    'Magento_Checkout/js/action/select-shipping-method',
    'Magento_Checkout/js/action/select-shipping-address',
    'Magento_Checkout/js/action/create-shipping-address',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/postcode-validator',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/model/customer',
    'Klarna_Kco/js/action/select-shipping-method',
    'Klarna_Kco/js/model/iframe',
    'mage/storage',
    'mage/url',
    'Klarna_Kco/js/action/update-klarna-order',
    'Klarna_Kco/js/model/config',
], function (
    $,
    Component,
    _,
    ko,
    reg,
    pbConfig,
    pbRateFilter,
    pbShippingHelper,
    selectShippingMethodAction,
    selectShippingAddressAction,
    createShippingAddressAction,
    checkoutData,
    postcodeValidator,
    shippingService,
    quote,
    customer,
    kcoShippingMethod,
    klarna,
    storage,
    mageUrl,
    updateKlarnaOrder,
    kcoConfig
) {
    'use strict';
    var checkoutConfig = window.checkoutConfig.porterbuddy;

    var updateInProgress = ko.observable(false);

    return Component.extend({
        defaults: {
            pbDiscount: checkoutConfig.discount * 100,
            leaveDoorstepEnabled: checkoutConfig.leaveDoorstepEnabled,
            checkoutWidgetTitle: checkoutConfig.checkoutWidgetTitle,
            enterPostcodeText: checkoutConfig.enterPostcodeText,
            homeDeliveryTitle: checkoutConfig.homeDeliveryTitle,
            pickupPointTitle: checkoutConfig.pickupPointTitle,
            collectInStoreTitle: checkoutConfig.collectInStoreTitle,
            template: 'Porterbuddy_Porterbuddy/klarna-shipping'

        },
        updateInProgress: updateInProgress,



        initialize: function () {
            this._super();
            window.pbPlugin = this;
            window.reg = reg;
            window.storage = storage;

            pbRateFilter.getGroupedRates().subscribe(function (rates) {
                this.processNewRates(rates);

            }.bind(this));
            quote.shippingAddress.subscribe(function(address){
                if(!window.pbRecipientInfo ||
                    window.pbRecipientInfo.postcode != address.postcode ||
                    window.pbRecipientInfo.street != address.street
                ){
                    window.pbRecipientInfo.postcode = address.postcode;
                    window.pbRecipientInfo.street = address.street;
                    var email = '';
                    if (customer.isLoggedIn()) {
                        email = customer.customerData.email;
                    } else {
                        email = quote.guestEmail;
                    }
                    if( email.length == 0 && window.pbRecipientInfo.email && window.pbRecipientInfo.email.length > 0){
                        email = window.pbRecipientInfo.email;
                    }else{
                        window.pbRecipientInfo.email = email;
                    }
                    if(window.pbSetRecipientInfo){
                        window.pbSetRecipientInfo({
                            postCode: address.postcode,
                            streetAddress: address.street,
                            email: address.email
                        });
                    }
                    if(window.pbSetRecipientInfoLocked){
                        window.pbSetRecipientInfoLocked(true);
                    }
                }

            });


        },

        processNewRates: function (rates) {
            var homeDeliveryRates = [];
            var pickupPointRates = [];
            var collectInStoreRates = [];
            var address = quote.shippingAddress();
            var hasPostcode = false;
            if(address && address.postcode && address.postcode.length > 0){
                hasPostcode = true;
            }
            var hasFullAddress = false;
            if (hasPostcode && address && address.street && address.street.length > 0){
                hasFullAddress = true;
            }
            var email = '';
            if (customer.isLoggedIn()) {
                email = customer.customerData.email;
            } else {
                email = quote.guestEmail;
            }
            var hasDefault = false;
            if (rates.porterbuddy && rates.porterbuddy.length > 0) {
                var availabilityResponse = JSON.parse(rates.porterbuddy[0].extension_attributes.porterbuddy_info.windows);
                var deliveryWindows = availabilityResponse.deliveryWindows;
                var porterbuddyOption = {
                    id: rates.porterbuddy[0].carrier_code,
                    name: rates.porterbuddy[0].carrier_title,
                    deliveryWindows: deliveryWindows,
                    discount: this.pbDiscount,
                    showLeaveAtDoorstep: this.leaveDoorstepEnabled,
                    updateInterval: 300,
                    onUpdateOption: this.onUpdateDeliveryWindows,
                    default: true
                };
                homeDeliveryRates.push(porterbuddyOption);
                hasDefault = true;
            }
            if (rates.other && rates.other.length > 0) {

                _.each(rates.other, function (rate) {
                    var rateObject = pbShippingHelper.processRate(rate);
                    if(rateObject.showRate) {
                        if (!hasDefault) {
                            rateObject.default = true;
                            hasDefault = true;
                        }
                        if (rateObject.additionalData.type === pbShippingHelper.HOME_DELIVERY) {
                            homeDeliveryRates.push(rateObject);
                        } else if (rateObject.additionalData.type === pbShippingHelper.PICKUP_POINT) {
                            pickupPointRates.push(rateObject);
                        } else if (rateObject.additionalData.type === pbShippingHelper.COLLECT_IN_STORE) {
                            collectInStoreRates.push(rateObject);
                        }
                    }
                }.bind(this));

                if(!hasPostcode) {
                    homeDeliveryRates = [];
                    if (pickupPointRates.length > 0) {
                        pickupPointRates = [];
                    } else {
                        pickupPointRates = undefined;
                    }
                }
                if(collectInStoreRates.length > 0){
                    if(!hasPostcode) {
                        collectInStoreRates = [];
                    }
                }else{
                    collectInStoreRates = undefined;
                }

            }
            if (!window.porterbuddy) {
                var recipientInfo = {};

                if(hasFullAddress){
                    recipientInfo.streetAddress = address.street
                    recipientInfo.postCode = address.postcode;
                    recipientInfo.email = email;
                    window.pbRecipientInfo = {
                        'postcode' : address.postcode,
                        'street': address.street,
                        'email': email
                    };
                }
                var text = {};
                if(this.checkoutWidgetTitle && this.checkoutWidgetTitle.length > 0){
                    text.title = this.checkoutWidgetTitle;
                }
                if(this.enterPostcodeText && this.enterPostcodeText.length > 0){
                    text.enterPostCode = this.enterPostcodeText;
                }
                if(this.homeDeliveryTitle && this.homeDeliveryTitle.length > 0){
                    text.categoryTitle_home = this.homeDeliveryTitle;
                }
                if(this.pickupPointTitle && this.pickupPointTitle.length > 0){
                    text.categoryTitle_pickupPoint = this.pickupPointTitle;
                }
                if(this.collectInStoreTitle && this.collectInStoreTitle.length > 0){
                    text.categoryTitle_store = this.collectInStoreTitle;
                }
                window.porterbuddy = {
                    homeDeliveryOptions: homeDeliveryRates,
                    pickupPointOptions: pickupPointRates,
                    storeOptions: collectInStoreRates,
                    recipientInfoLocked: hasFullAddress,
                    recipientInfo: recipientInfo,
                    text: text,
                    onSelectionChanged: function (type, selectedShipping) {
                        this.selectRate(type, selectedShipping);
                        // use the selection data
                    }.bind(this),
                    onSetCallbacks: function(callbacks) {
                        window.pbForceRefresh = callbacks.forceRefresh;
                        window.pbSetRecipientInfo = callbacks.setRecipientInfo;
                        window.pbRefreshShippingOptions = callbacks.refreshShippingOptions;
                        window.pbSetRecipientInfoLocked = callbacks.setRecipientInfoLocked
                        if(window.pbDelayRefresh){
                            window.pbDelayRefresh = false;
                            window.pbRefreshShippingOptions();
                        }
                    },
                    onRecipientInfoEntered: function(recipientInfo) {
                        this.setRecipientInfo(recipientInfo);
                    }.bind(this),
                    selectionPropertyChangeListeners: [
                        {
                            optionId: "porterbuddy",
                            propertyPath: "data.leaveAtDoorstep",
                            onChange: function (value) {
                                $.ajax({
                                    type: 'POST',
                                    url: mageUrl.build('porterbuddy/delivery/options'),
                                    data: {
                                        leave_doorstep: value,
                                        type: 'doorstep',
                                        form_key: $.mage.cookies.get('form_key'),
                                    }
                                }).done(function (data) {
                                    if (data.error) {
                                        console.error(data.message);
                                    }
                                }).fail(function () {
                                    console.error("error saving leave at doorstep state");
                                });
                            }
                        },
                        {
                            optionId: "porterbuddy",
                            propertyPath: "data.comment",
                            onChange: function (value) {
                                $.ajax({
                                    type: 'POST',
                                    url: mageUrl.build('porterbuddy/delivery/options'),
                                    data: {
                                        comment: value,
                                        type: 'comment',
                                        form_key: $.mage.cookies.get('form_key'),
                                    }
                                }).done(function (data) {
                                    if (data.error) {
                                        console.error(data.message);
                                    }
                                }).fail(function () {
                                    console.error("error saving note to courier");
                                });
                            }
                        }
                    ]
                };
            }else{

                window.porterbuddy.homeDeliveryOptions = homeDeliveryRates;
                window.porterbuddy.pickupPointOptions = pickupPointRates;
                window.porterbuddy.storeOptions = collectInStoreRates;
                if(window.pbRefreshShippingOptions) {
                    window.pbRefreshShippingOptions();
                }else{
                    window.pbDelayRefresh = true;
                }
            }
        },

        selectRate: function (type, selectedShipping) {
            var selectedRate;
            var groupedRates = pbRateFilter.getGroupedRates();
            if(pbConfig.CARRIER_CODE === selectedShipping.id){
                if(!selectedShipping.data.deliveryWindow){
                    this.processNewRates(groupedRates());
                }else {
                    var selectedWindow = selectedShipping.data.deliveryWindow;
                    selectedRate = _.find(groupedRates().porterbuddy, function (rate) {
                        return selectedWindow.product === rate.extension_attributes.porterbuddy_info.type && selectedWindow.start === rate.extension_attributes.porterbuddy_info.start && selectedWindow.end === rate.extension_attributes.porterbuddy_info.end;
                    });
                }
            }else {
                selectedRate = _.find(groupedRates().other, function (rate) {
                    return selectedShipping.additionalData && rate.method_code === selectedShipping.additionalData.methodCode && rate.carrier_code === selectedShipping.additionalData.carrierCode;
                });
            }
            if (selectedRate) {
                if(pbConfig.CARRIER_CODE !== selectedShipping.id){
                    pbShippingHelper.selectRate(selectedRate, selectedShipping.data?selectedShipping.data:null, selectedShipping.additionalData.type);
                }
                /**
                 * This method is called more then 1 time. To avoid duplicated calculations, requests and other things
                 * we're using the flag to know if there is currently a active process.
                 */
                if (updateInProgress()) {
                    return true;
                }

                kcoShippingMethod(selectedRate);
                updateInProgress(true);

                storage.post(kcoConfig.methodUrl, JSON.stringify(selectedRate)).done(function() {
                    updateInProgress(false);
                    updateKlarnaOrder();
                }).fail(function(){
                    updateInProgress(false);
                });

            }
        },

        onUpdateDeliveryWindows: function(callback) {
            storage.post(
                mageUrl.build('porterbuddy/delivery/timeslots'),
                JSON.stringify({
                    refresh: true
                }),
                true
            ).done(function(data) {
                if(data.error){
                    console.error(data.message);
                }else {
                    if(!data.timeslots || !data.timeslots.deliveryWindows || data.timeslots.deliveryWindows.length === 0){
                        pbRateFilter.getRateCacheDisabled()(true);
                        // see shipping-rate-service
                        quote.shippingAddress.valueHasMutated();
                        pbRateFilter.getRateCacheDisabled()(false);
                    }
                    callback({deliveryWindows:data.timeslots.deliveryWindows, discount: window.checkoutConfig.porterbuddy.discount * 100});
                }
            });
        },

        setRecipientInfo: function(recipient){
            var address = quote.shippingAddress();
            if(!address) {
                address = { };
            }
            address.countryId = 'NO';
            var addressToSave = { 'country_id': 'NO' };

            if (!window.pbRecipientInfo){
                window.pbRecipientInfo = {
                    'postcode' : '',
                    'street': '',
                    'email': ''
                };
            }
            if(window.pbRecipientInfo.postcode == recipient.postCode &&
                 window.pbRecipientInfo.street == recipient.streetAddress &&
                window.pbRecipientInfo.email == recipient.email){
                //nothing has changed
                return;
            }
            if(recipient.postCode && recipient.postCode.length > 0 ){
                address.postcode = recipient.postCode;
                addressToSave.postcode = recipient.postCode;
                window.pbRecipientInfo.postcode = recipient.postCode;

            }
            if(recipient.streetAddress && recipient.streetAddress.length > 0){
                address.street = recipient.streetAddress;
                addressToSave.street = recipient.streetAddress;
                window.pbRecipientInfo.street = recipient.streetAddress;
            }
            if(recipient.email && recipient.email.length > 0){
                addressToSave.email = recipient.email;
                if (customer.isLoggedIn()) {
                    customer.customerData.email = recipient.email;
                } else {
                    quote.guestEmail = recipient.email;
                }
                window.pbRecipientInfo.email = recipient.email;
            }
            var shippingAddress = createShippingAddressAction(address);
            selectShippingAddressAction(shippingAddress);


            checkoutData.setShippingAddressFromData(addressDataToSave);
            klarna.suspend();
            $.ajax({
                type: 'POST',
                url: mageUrl.build('porterbuddy/delivery/recipientInfoUpdate'),
                data: addressToSave
            }).always(function(){
                klarna.resume();
            }).done(function (data) {
                if (data.error) {
                    console.error(data.message);
                } else {
                    return;
                }
            }).fail(function () {
                console.error("error updating address in klarna");
            });

        }
    });
});
