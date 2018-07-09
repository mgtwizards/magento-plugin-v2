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
            leaveDoorstepText: checkoutConfig.leaveDoorstepText,
            showTimeslots: checkoutConfig.showTimeslots,
            returnEnabled: checkoutConfig.returnEnabled,
            returnText: checkoutConfig.returnText,
            commentText: checkoutConfig.commentText,
            refreshOptionsTimeout: checkoutConfig.refreshOptionsTimeout,
            leaveDoorstep: checkoutConfig.leaveDoorstep,
            comment: checkoutConfig.comment,
            listens: {
                'leaveDoorstep comment': 'onOptionsChange'
            }
        },
        formKey: $.mage.cookies.get('form_key'),
        timer: null,
        internal: false,
        selectedDate: null,
        refreshIntervalId: null,

        // observable
        visible: ko.observable(false),
        isReturnSelected: ko.observable(false),
        hasReturnOther: ko.observable(true),
        selectedDateLabel: ko.observable(),
        timeslots: ko.observableArray(),
        selectedTimeslot: ko.observable(),
        prevDateAvailable: ko.observable(false),
        nextDateAvailable: ko.observable(false),

        initialize: function () {
            this._super();

            rateFilter.getPorterbuddyRates().subscribe(function (rates) {
                this.processNewRates(rates);
            }.bind(this));

            quote.shippingMethod.subscribe(function (shippingMethod) {
                if (!this.internal) {
                    this.selectRate(shippingMethod);
                }
            }.bind(this));

            this.selectedTimeslot.subscribe(function (newValue) {
                // FIXME
                $('#s_method_porterbuddy').prop('checked', Boolean(newValue));
            }.bind(this));

            this.initRefresh();

            return this;
        },

        initObservable: function () {
            this._super();
            this.observe('leaveDoorstep comment');

            return this;
        },

        initRefresh: function () {
            if (!this.refreshOptionsTimeout) {
                return;
            }

            shippingService.getShippingRates().subscribe(function () {
                if (this.refreshIntervalId) {
                    // restart timer when new rates arrive
                    clearInterval(this.refreshIntervalId);
                }
                this.refreshIntervalId = setInterval(function () {
                    this.refresh();
                }.bind(this), this.refreshOptionsTimeout * 60 * 1000);
            }.bind(this));
        },

        refresh: function () {
            rateFilter.getRateCacheDisabled()(true);
            // see shipping-rate-service
            quote.shippingAddress.valueHasMutated();
            rateFilter.getRateCacheDisabled()(false);
        },

        processNewRates: function (rates) {
            if (!rates.length) {
                this.visible(false);
                return;
            }

            this.timeslotsByValue = {};

            _.each(rates, function (rate) {
                var code = rate.carrier_code + '_' + rate.method_code;
                var timeslot = _.extend({}, rate.extension_attributes.porterbuddy_info, {
                    value: code,
                    price: priceUtils.formatPrice(rate.price_incl_tax, quote.getPriceFormat()), // TODO: incl/excl tax
                    method: rate
                });
                this.timeslotsByValue[code] = ko.observable(timeslot);
            }.bind(this));

            // pair no return - with return timeslots
            _.each(this.timeslotsByValue, function (timeslot) {
                if (timeslot().other_value) {
                    // already linked
                    return;
                }

                var pairTimeslot = _.find(this.timeslotsByValue, function (otherTimeslot) {
                    return timeslot().type == otherTimeslot().type
                        && timeslot().start == otherTimeslot().start
                        && timeslot().end == otherTimeslot().end
                        && timeslot().return != otherTimeslot().return;
                });
                if (pairTimeslot) {
                    // link both ways
                    timeslot().other_value = pairTimeslot().value;
                    pairTimeslot().other_value = timeslot().value;
                }
            }.bind(this));

            // group timeslots by date
            this.dates = {};
            _.each(this.timeslotsByValue, function (timeslot, value) {
                if (!(timeslot().date_key in this.dates)) {
                    this.dates[timeslot().date_key] = {
                        key: timeslot().date_key,
                        label: timeslot().date_label,
                        datetime: timeslot().start,
                        timeslots: {}
                    };
                }

                this.dates[timeslot().date_key].timeslots[value] = timeslot;
            }.bind(this));

            // initial selection
            if (!this.selectedDate) {
                this.selectDate(null);
            }

            if (quote.shippingMethod()) {
                this.selectRate(quote.shippingMethod());
            }

            this.visible(true);
        },

        selectPorterbuddy: function () {
            if (!this.selectedTimeslot()) {
                this.selectTimeslot(null);
            }
            return true;
        },

        toggleReturn: function () {
            if (this.selectedTimeslot() && this.selectedTimeslot().other_value) {
                this.selectTimeslot(this.selectedTimeslot().other_value);
            } else {
                // first
                this.selectTimeslot(null);
            }

            return true;
        },

        /**
         * @api
         */
        setPrevDate: function () {
            var dateCode = this.getPrevDateCode();
            if (false !== dateCode) {
                this.selectDate(dateCode);
                this.selectTimeslot(null);
            }

            return this;
        },

        /**
         * @api
         */
        setNextDate: function () {
            var dateCode = this.getNextDateCode();
            if (false !== dateCode) {
                this.selectDate(dateCode);
                this.selectTimeslot(null);
            }

            return this;
        },

        getPrevDateCode: function () {
            if (!this.selectedDate) {
                return false;
            }

            var keys = _.keys(this.dates);
            var pos = keys.indexOf(this.selectedDate.key);

            if (pos > 0) {
                return keys[pos-1];
            } else {
                return false;
            }
        },

        getNextDateCode: function () {
            if (!this.selectedDate) {
                return false;
            }

            var keys = _.keys(this.dates);
            var pos = keys.indexOf(this.selectedDate.key);
            if (-1 !== pos && pos < keys.length-1) {
                return keys[pos+1];
            } else {
                return false;
            }
        },

        isPorterbuddyRate: function (shippingMethod) {
            if (!shippingMethod) {
                return false;
            }

            var value = shippingMethod.carrier_code + '_' + shippingMethod.method_code;
            var exp = new RegExp('^' + Porterbuddy.CARRIER_CODE + '_');
            return exp.test(value);
        },

        render: function () {
            this.timeslots(this.getVisibleTimeslots());
            this.selectedDateLabel(this.selectedDate.label);
            this.prevDateAvailable(false !== this.getPrevDateCode());
            this.nextDateAvailable(false !== this.getNextDateCode());

            return this;
        },

        /**
         * @api
         * @param key
         * @returns {Window.PorterbuddyWidget}
         */
        selectDate: function (key) {
            if (null === key) {
                key = _.first(_.keys(this.dates));
            }

            if (!(key in this.dates)) {
                throw new Error('Invalid date index ' + key);
            }
            if (!this.selectedDate || this.selectedDate.key !== key) {
                this.selectedDate = this.dates[key];
                this.selectedTimeslot(null);
                this.render();
            }

            return this;
        },

        /**
         * @api
         * @param arg
         * @returns {Window.PorterbuddyWidget}
         */
        selectTimeslot: function (arg) {
            var timeslots = this.getVisibleTimeslots();
            var timeslot;
            if (null === arg) {
                timeslot = this.selectedTimeslot() ? this.selectedTimeslot : _.first(timeslots);
            } else if (_.isObject(arg)) {
                // timeslot click
                timeslot = _.find(timeslots, function (timeslot) {
                    return timeslot().value === arg.value;
                });
            } else {
                // rate code
                timeslot = _.find(timeslots, function (timeslot) {
                    return timeslot().value === arg;
                });
            }

            if (!timeslot) {
                throw new Error('Invalid timeslot', arg);
            }

            // remove active flag from old timeslot
            if (this.selectedTimeslot()) {
                this.selectedTimeslot().active = false;
            }

            this.selectedTimeslot(timeslot());
            this.selectedTimeslot().active = true;
            this.isReturnSelected(this.selectedTimeslot().return);
            this.hasReturnOther(Boolean(this.selectedTimeslot().other_value));

            // internal, don't call selectRate
            this.internal = true;
            this.selectShippingMethod(timeslot().method);
            this.internal = false;

            this.render();

            return this;
        },

        getVisibleTimeslots: function () {
            if (!this.selectedDate) {
                return [];
            }

            var timeslots = _.values(this.selectedDate.timeslots);

            if (this.returnEnabled) {
                // only timeslots with return if selected, or only timeslots without return otherwise
                var returnSelected = this.isReturnSelected();
                timeslots = _.filter(timeslots, function (timeslot) {
                    return returnSelected == timeslot().return;
                });
            }

            return timeslots;
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
                this.selectedTimeslot(null);
                return;
            }

            var value = shippingMethod.carrier_code + '_' + shippingMethod.method_code;
            if (value in this.timeslotsByValue) {
                var timeslot = this.timeslotsByValue[value];

                if (!this.selectedDate || timeslot().date_key !== this.selectedDate.key) {
                    this.selectDate(timeslot().date_key);
                }
                this.isReturnSelected(timeslot().return);
                if (!this.selectedTimeslot() || timeslot().value !== this.selectedTimeslot().value) {
                    this.selectTimeslot(value);
                }
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

        onOptionsChange: function () {
            return $.ajax({
                type: 'POST',
                url: mageUrl.build('porterbuddy/delivery/options'),
                data: this.prepareOptions()
            });
        },

        prepareOptions: function () {
            return {
                form_key: this.formKey,
                comment: this.comment(),
                leave_at_doorstep: this.leaveDoorstep()
            };
        }
    });
});
