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
            autoUpdate: true,
            mapsApiKey: '',
            getLocationURL: '',
            locationDiscovery: [],
            showAvailability: '',
            defaultCountry: '',
            availabilityText: '',
            textClickToSee: '',
            textFetchingTemplate: '',
            textDeliveryUnavailableTemplate: ''
        },

        _create: function () {
            this.initOptions();
            this.initEvents();
            this.initExtensions();

            if (this.autoUpdate) {
                this.update();
            }
        },

        initOptions: function () {
            this.autoUpdate = this.options.autoUpdate;
            this.mapsApiKey = this.options.mapsApiKey;
            this.getLocationURL = this.options.getLocationURL;

            var availableMethods = this.getLocationDiscoveryMethods();
            this.enabledDiscoveryMethods = [];
            jQuery.each(this.options.locationDiscovery, function (index, discoveryMethod) {
                if (discoveryMethod in availableMethods) {
                    this.enabledDiscoveryMethods.push(availableMethods[discoveryMethod]);
                }
            }.bind(this));

            this.getAvailabilityURL = this.options.getAvailabilityURL;
            this.isAlwaysShow = this.options.isAlwaysShow;
            this.defaultCountry = this.options.defaultCountry;

            this.$availabilityText = this.element.find('.porterbuddy-availability-text');
            this.textTemplate = this.$availabilityText.html();
            this.$availabilityText.html(''); // clear template

            this.textClickToSee = this.options.textClickToSee;
            this.textFetchingTemplate = this.options.textFetching;
            this.textDeliveryUnavailableTemplate = this.options.textDeliveryUnavailable;

            this.$locationLink = this.element.find('.porterbuddy-availability-location-link');
            this.locationTemplate = this.$locationLink.html();
            this.$locationLink.html(''); // clear template

            this.popupId = this.$locationLink.data('open');
            this.$popup = jQuery('#' + this.popupId);
            this.$popupPostcode = this.$popup.find('.porterbuddy-popup-postcode');
            this.$popupSave = this.$popup.find('.porterbuddy-popup-save');
            this.$message = this.$popup.find('.porterbuddy-message');

            this.geocoder = null;
            this.location = null;
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
            this.ipLocationDfd = null;
            this.browserLocationDfd = null;
            this.geocodeDfd = {}; // by request
            this.availabilityTimer = null;
        },

        getLocationDiscoveryMethods: function () {
            return {
                'browser': this.getBrowserLocation.bind(this),
                'ip': this.getIpLocation.bind(this),
            };
        },

        initEvents: function () {
            this.$locationLink.on('click', 'a, button', this.changeLocation.bind(this));
            this.$popupSave.click(this.saveChangedLocation.bind(this));

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
                var selected_options = {};
                $('div.swatch-attribute').each(function(k,v){
                    var attribute_id    = $(v).attr('attribute-id');
                    var option_selected = $(v).attr('option-selected');
                    if (!attribute_id || !option_selected) {
                        return null;
                    }
                    selected_options[attribute_id] = option_selected;
                });

                var product_id_index = $('[data-role=swatch-options]').data('mageSwatchRenderer').options.jsonConfig.index;
                var found_ids = [];
                $.each(product_id_index, function (product_id,attributes) {
                    var productIsSelected = function (attributes, selected_options) {
                        return _.isEqual(attributes, selected_options);
                    }
                    if (productIsSelected(attributes, selected_options)) {
                        found_ids.push(product_id);
                    }
                });
                if (found_ids) {
                    productId = found_ids[0];
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
            var location = Porterbuddy.getCachedLocation();
            if (location) {
                this.locationSuccess(location);
            } else {
                this.element.addClass('location-loading');
                this.chainCallbacks(this.enabledDiscoveryMethods)
                    .done(function (location) {
                        Porterbuddy.rememberLocation(location);
                        this.locationSuccess(location);
                    }.bind(this))
                    .fail(this.locationError.bind(this))
                    .always(function () {
                        this.element.removeClass('location-loading');
                    }.bind(this));
            }
        },

        prepareAvailabilityData: function (postcode) {
            // can extend to send whole serialized product form if necessary
            return jQuery.extend({}, this.params, {
                postcode: postcode
            })
        },

        prepareAvailabilityKey: function (data) {
            return data.postcode + '_' + data.productId + '_' + data.qty;
        },

        getAvailability: function (postcode) {
            var data = this.prepareAvailabilityData(postcode);
            var key = this.prepareAvailabilityKey(data);
            if (this.getAvailabilityDfd && this.getAvailabilityDfd[key]) {
                return this.getAvailabilityDfd[key].promise();
            }

            var dfd = this.getAvailabilityDfd[key] = jQuery.Deferred();
            dfd.always(function () {
                // once complete, stop caching this call
                delete this.getAvailabilityDfd[key];
            }.bind(this));

            jQuery.ajax(this.getAvailabilityURL, {
                method: 'post',
                dataType: 'json',
                data: data
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

        locationSuccess: function (location) {
            this.setCurrentLocation(location);

            this.$availabilityText.html(template(this.textFetchingTemplate, this.location));
            this.element.addClass('availability-loading');

            this.getAvailability(location.postcode)
                .done(function (result) {
                    this.availabilitySuccess(result);
                }.bind(this))
                .fail(function (message) {
                    this.availabilityError(message);
                }.bind(this))
                .always(function () {
                    this.element.removeClass('availability-loading');
                }.bind(this));
        },

        setCurrentLocation: function (location) {
            this.location = location;

            // rendered location summary for rendering in templates
            this.location.location = (this.location.postcode + ' ' + this.location.city).replace(/ +$/, '');

            this.$locationLink.html(template(this.locationTemplate, this.location));
            this.element.removeClass('postcode-error').addClass('postcode-success');
        },

        locationError: function (reason) {
            //this.hide();
            this.element.addClass('postcode-error').removeClass('postcode-success');
            this.$locationLink.html(this.textClickToSee);
            this.show();
        },

        availabilitySuccess: function (result) {
            this.availability = result;
            this.element.removeClass('availability-error').addClass('availability-success');
            this.renderText();
            this.show();
        },

        availabilityError: function (message) {
            if (!this.isAlwaysShow) {
                this.hide();
                return;
            }

            this.element.addClass('availability-error').removeClass('availability-success');

            // default error message
            var error = template(this.textDeliveryUnavailableTemplate, this.location);
            if (message) {
                try {
                    error = template(message, this.location);
                } catch (err) {
                }
            }

            this.$availabilityText.html(error);
            this.show();
        },

        renderText: function () {
            var params = {
                date: this.availability.humanDate,
                countdown: this.getCounterText(this.availability.date, this.availability.timeRemaining)
            };
            this.$availabilityText.html(template(this.textTemplate, params));

            if (this.availability.timeRemaining-- > 0) {
                // prevent multiple timers
                clearTimeout(this.availabilityTimer);
                // revisit in a minute
                this.availabilityTimer = setTimeout(this.renderText.bind(this), 60*1000);
            } else {
                this.hide();
            }
        },

        show: function () {
            this.element.show();
        },

        hide: function () {
            this.element.hide();
        },

        getCounterText: function (date, remainingMinutes) {
            if (typeof window.moment !== 'undefined') {
                return moment().to(date, true);
            }

            var days = Math.floor(remainingMinutes / (60*24));
            var hours = Math.floor(remainingMinutes / 60) % 24;
            var minutes = remainingMinutes % 60;

            var parts = [];
            if (days) {
                if (1 === days) {
                    parts.push($t('%1 day').replace('%1', days));
                } else {
                    parts.push($t('%1 days').replace('%1', days));
                }
            }
            if (hours) {
                if (1 === hours) {
                    parts.push($t('%1 hour').replace('%1', hours));
                } else {
                    parts.push($t('%1 hours').replace('%1', hours));
                }
            }
            if (minutes) {
                if (1 === minutes) {
                    parts.push($t('%1 minute').replace('%1', minutes));
                } else {
                    parts.push($t('%1 minutes').replace('%1', minutes));
                }
            }

            return parts.join(' ');
        },

        /**
         * Chain postcode detection methods until any returns results
         */
        chainCallbacks: function (enabledDiscoveryMethods) {
            var dfd = jQuery.Deferred();

            var index = 0;
            function runNext(previousError)
            {
                if (enabledDiscoveryMethods[index]) {
                    enabledDiscoveryMethods[index++]().then(success, runNext);
                } else {
                    dfd.reject(previousError);
                }
            }

            function success(postcode)
            {
                dfd.resolve(postcode);
            }

            runNext();

            return dfd.promise();
        },

        getIpLocation: function () {
            if (this.ipLocationDfd) {
                return this.ipLocationDfd.promise();
            }

            var dfd = this.ipLocationDfd = jQuery.Deferred();

            // use POST to eliminate full page cache
            jQuery.ajax(this.getLocationURL, {method: 'post', dataType: 'json'})
                .done(function (result) {
                    if (result.postcode) {
                        // postcode, city, country
                        delete result.error;
                        delete result.message;
                        result.source = Porterbuddy.SOURCE_IP;
                        dfd.resolve(result);
                    } else {
                        dfd.reject(result.message);
                    }
                }).fail(function () {
                    dfd.reject('AJAX request error');
                });

            return dfd.promise();
        },

        getBrowserLocation: function () {
            if (this.browserLocationDfd) {
                return this.browserLocationDfd.promise();
            }

            var dfd = this.browserLocationDfd = jQuery.Deferred();

            this.getBrowserCoordinates()
                .done(function (latlng) {
                    this.geocodeLocation({'location': latlng})
                        .done(function (location) {
                            location.source = Porterbuddy.SOURCE_BROWSER;
                            dfd.resolve(location);
                        })
                        .fail(function (reason) {
                            dfd.reject(reason);
                        });
                }.bind(this))
                .fail(function () {
                    dfd.reject('Browser location API failed');
                });

            return dfd.promise();
        },

        geocodeLocation: function (request) {
            var key = JSON.stringify(request);
            if (this.geocodeDfd[key]) {
                return this.geocodeDfd[key].promise();
            }

            var dfd = this.geocodeDfd[key] = jQuery.Deferred();
            dfd.always(function () {
                // once complete, stop caching this call
                delete this.geocodeDfd[key];
            }.bind(this));

            // TODO: move this check to loadMaps to run only once
            // detect maps auth failure (bad API key) and reject
            var origAuthFailure = window.gm_authFailure;
            window.gm_authFailure = function () {
                dfd.reject('Google maps auth failure');

                if (origAuthFailure) {
                    origAuthFailure();
                }
            };

            this.loadMaps(this.mapsApiKey)
                .done(function () {
                    this.geocoder = this.geocoder || new google.maps.Geocoder;
                    this.geocoder.geocode(request, function (results, status) {
                        if (status !== 'OK' || !results) {
                            dfd.reject('No results');
                            return;
                        }

                        var postcode = null,
                            city = '',
                            country = '';
                        jQuery.each(results[0].address_components, function (index, component) {
                            if (-1 !== component.types.indexOf('postal_code')) {
                                postcode = component.long_name;
                            }
                            if (-1 !== component.types.indexOf('postal_town')) {
                                city = component.long_name;
                            }
                            if (-1 !== component.types.indexOf('country')) {
                                country = component.long_name;
                            }
                        });

                        if (null === postcode) {
                            dfd.reject('Unknown postcode after geocoding');
                            return;
                        }

                        dfd.resolve({
                            postcode: postcode,
                            city: city,
                            country: country
                        });
                    });
                }.bind(this))
                .fail(function () {
                    dfd.reject('Cannot load Google maps');
                });

            return dfd.promise();
        },

        /**
         * Gets user coordinates
         * @returns {*}
         */
        getBrowserCoordinates: function () {
            if (this.browserCoordinatesDfd) {
                return this.browserCoordinatesDfd.promise();
            }

            var dfd = this.browserCoordinatesDfd = jQuery.Deferred();

            if ("geolocation" in navigator) {
                navigator.geolocation.getCurrentPosition(function (position) {
                    dfd.resolve({
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    });
                }, function (error) {
                    // error.code, error.message
                    dfd.reject(error);
                });
            } else {
                dfd.reject('Geolocation is not supported');
            }

            return dfd.promise();
        },

        /**
         * Loads maps if not loaded and returns deferred object
         * @returns {*}
         */
        loadMaps: function (mapsApiKey) {
            if ('undefined' === typeof window.google || !'maps' in window.google) {
                return jQuery.getScript(
                    'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(mapsApiKey)
                );
            } else {
                // resolve immediately
                return jQuery.when();
            }
        },

        changeLocation: function () {
            this.$popupPostcode.val(this.location && this.location.postcode);

            this.$popup.validation().validation('clearError');
            this.hideMessage();
            this.openPopup();
        },

        saveChangedLocation: function () {
            var postcode = this.$popupPostcode.val();

            if (!this.$popup.validation().validation('isValid')) {
                setTimeout(function () {
                    this.updatePopupHeight();
                }.bind(this), 1000);

                return;
            }

            this.$popupSave.prop('disabled', true);

            this.getAvailability(postcode)
                .done(function (result) {
                    // set imprecise location, geocode more details later
                    var location = {
                        postcode: postcode,
                        city: '',
                        country: this.defaultCountry,
                        source: Porterbuddy.SOURCE_USER
                    };
                    Porterbuddy.rememberLocation(location);
                    this.setCurrentLocation(location);
                    this.availabilitySuccess(result);
                    this.closePopup();

                    // geocode city and country name
                    this.geocodeLocation({
                        address: 'country ' + this.defaultCountry + ', postal code ' + postcode,
                        region: this.defaultCountry
                    }).done(function (geocodedLocation) {
                        var currentLocation = this.location;
                        currentLocation.city = geocodedLocation.city;
                        currentLocation.country = geocodedLocation.country;

                        Porterbuddy.rememberLocation(currentLocation);
                        this.setCurrentLocation(currentLocation);
                    }.bind(this));
                }.bind(this))
                .fail(function (message) {
                    var newLocation = {
                        postcode: postcode,
                        location: postcode,
                        city: '',
                        country: ''
                    };

                    // default error message
                    var error = template(this.textDeliveryUnavailableTemplate, newLocation);
                    if (message) {
                        try {
                            error = template(message, newLocation);
                        } catch (err) {
                        }
                    }

                    this.showMessage(error, 'error');
                }.bind(this))
                .always(function () {
                    this.$popupSave.prop('disabled', false);
                }.bind(this));
        },

        // Popup related methods
        /**
         * @override
         */
        openPopup: function () {
            if (!this.popup) {
                this.$popup.modal({
                    modalClass: 'porterbuddy-availability',
                    type: 'popup',
                    buttons: [],
                });
                this.popup = true;
            }
            this.$popup.modal('openModal');
        },

        /**
         * @override
         */
        updatePopupHeight: function () {
            // implement if needed
        },

        /**
         * @override
         */
        closePopup: function () {
            if (this.popup) {
                this.$popup.modal('closeModal');
            }
        },

        hideMessage: function () {
            this.$message.removeClass(function (index, className) {
                return (className.match(/(^|\s)alert-\S+/g) || []).join(' ');
            });
            this.$message.hide();
        },

        showMessage: function (message, level) {
            level = level || 'info';
            this.$message.removeClass(function (index, className) {
                return (className.match(/(^|\s)alert-\S+/g) || []).join(' ');
            }).addClass('alert-' + level);
            this.$message.html(message).show();
            this.updatePopupHeight();
        }
    });

    return $.porterbuddy.availability;
});
