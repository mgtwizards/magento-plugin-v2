/*
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
define([
    'jquery',
    'mage/cookies'
], function ($) {
    return {
        CARRIER_CODE: 'porterbuddy',
        COOKIE: 'porterbuddy_location',
        SOURCE_BROWSER: 'browser',
        SOURCE_IP: 'ip',
        SOURCE_USER: 'user',
        cookieLifetime: 30,

        getCachedLocation: function () {
            var location = $.mage.cookies.get(this.COOKIE);
            if (!location) {
                return null;
            }
            location = JSON.parse(location);
            if (!location || !location.postcode) {
                return null;
            }
            return location;
        },

        rememberLocation: function (location) {
            var cookieExpires = new Date(new Date().getTime() + this.cookieLifetime * 1000);
            $.mage.cookies.set(this.COOKIE, JSON.stringify(location), {
                expires: cookieExpires
            });
        }
    };
});
