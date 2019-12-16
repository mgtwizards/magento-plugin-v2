/*
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
define([
    'jquery',
    'ko',
    'underscore',
    'mage/template',
    'mage/translate',
    'porterbuddyConfig',
    'mage/cookies'
], function ($, ko, _, template, $t, Porterbuddy) {
    'use strict';
    $.widget('porterbuddy.availability', {
        options: {
            availabilityText: '',
            textClickToSee: '',
            textDeliveryUnavailableTemplate: '',
            publicKey: '',
            apiMode: ''
        },

        _create: function () {
            this.initOptions();
            this.initEvents();
            this.initExtensions();
            this.initWidget();
            this.update();

        },

        initOptions: function () {

            this.availability = null;

            this.$form = jQuery('#product_addtocart_form');
            this.$qty = this.$form.find('#qty');

            var productId = this.options.productId;
            if (!productId && this.$form.attr('action')) {
                var match = this.$form.attr('action').match(/product\/(\d+)/);
                if (match) {
                    productId = match[1];
                }
            }

            // productId, qty, anything else
            this.params = {
                productId: productId,
                qty: this.$qty.val()
            };

            // deferred objects
            this.getAvailabilityDfd = {}; // by postcode and product id
        },

        initWidget: function () {
            window.porterbuddy = {
                token: this.options.publicKey,
                view: 'availability',
                apiMode: this.options.apiMode,
                text: {
                    availabilityPostcodeSuccess: this.options.availabilityText != null?this.options.availabilityText:undefined,
                    availabilityPostcodeError: this. options.textDeliveryUnavailableTemplate!=null?this.options.textDeliveryUnavailableTemplate:undefined,
                    availabilityStart: this.options.textClickToSee != null?this.options.textClickToSee:undefined
                }

            }
        },

        initEvents: function () {
            this.listenQtyChange();
            this.listenConfigurableChange();
        },

        listenQtyChange: function () {
            this.$qty.change(function () {
                this.params.qty = this.$qty.val();
                this.update();
            }.bind(this));
        },

        /**
         * Listens for configurable product selection and updates availability
         *
         * @see configurable._configureElement - sets simple product id and reloads price
         */
        listenConfigurableChange: function () {
            this.$selectedConfigurableOption = this.$form.find('[name="selected_configurable_option"]');
            if (!this.$selectedConfigurableOption.length) {
                // not configurable
                return;
            }

            $('.price-box').on('updatePrice', function () {
                var productId = this.getSelectedSimpleId();
                if (productId && productId != this.params.productId) {
                    this.params.productId = productId;
                    this.update();
                }
            }.bind(this));
        },

        getSelectedSimpleId: function () {
            // regular configurable
            var productId = this.$selectedConfigurableOption.val();

            // swatches?
            if ('' === productId) {
                // https://alanstorm.com/magento-2-extract-currently-selected-product-id/
                var selectedOptions = {};
                $('div.swatch-attribute').each(function (k, v) {
                    var attributeId = $(v).attr('attribute-id');
                    var optionSelected = $(v).attr('option-selected');
                    if (!attributeId || !optionSelected) {
                        return null;
                    }
                    selectedOptions[attributeId] = optionSelected;
                });

                var widgetRender = _.find($('[data-role=swatch-options]').data(), function (value, index) {
                    // by default mageSwatchRenderer, but can be inherited and changed namespace, e.g. convertSwatchRenderer
                    return index.match(/SwatchRenderer/);
                });
                if (!widgetRender) {
                    // cannot find swatch widget instance
                    return null;
                }

                var foundIds = [];
                $.each(widgetRender.options.jsonConfig.index, function (productId, attributes) {
                    if (_.isEqual(attributes, selectedOptions)) {
                        foundIds.push(productId);
                    }
                });
                // FIXME: intersect
                if (foundIds) {
                    productId = foundIds[0];
                }
            }

            return productId;
        },

        /**
         * @override
         */
        initExtensions: function () {
            // extension point
        },

        update: function () {
            this.getAvailability()
                .done(function (result) {
                    this.element.show();
                }.bind(this))
                .fail(function (message) {
                    this.element.hide();
                }.bind(this));

        },


        prepareAvailabilityKey: function (data) {
            return data.productId + '_' + data.qty;
        },

        getAvailability: function (postcode) {
            var key = this.prepareAvailabilityKey(this.params);
            if (this.getAvailabilityDfd && this.getAvailabilityDfd[key]) {
                return this.getAvailabilityDfd[key].promise();
            }

            var dfd = this.getAvailabilityDfd[key] = jQuery.Deferred();
            dfd.always(function () {
                // once complete, stop caching this call
                delete this.getAvailabilityDfd[key];
            }.bind(this));

            jQuery.ajax(this.options.getAvailabilityURL, {
                method: 'post',
                dataType: 'json',
                data: this.params
            }).done(function (result) {
                if (!result.available) {
                    dfd.reject(result.message);
                    return;
                }
                dfd.resolve(result);
            }.bind(this)).fail(function () {
                dfd.reject();
            }.bind(this));

            return dfd.promise();
        },
    });

    return $.porterbuddy.availability;
});
