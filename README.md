Porterbuddy Magento 2
=====================

## Features

- geo based availability widget on product page
- checkout widget with date and timeslot selection
- auto submit Porterbuddy shipment when payment is received
- email alerts when shipment submit fails

Supported Magento versions:
- 2.2
- 2.1
- 2.0 (*-patch branch with system.xml compatibility fixes - don't use `canRestore` attribute)

Klarna Checkout compatibility:
- API v2
- `klarna/m2-checkout` versions 3.0, 4.0, 5.0


## Installation with Composer

1. Install via Composer:

    `composer require porterbuddy/module-magento2`

1. Enable Magento module:

    ```
    bin/magento module:enable Porterbuddy_Porterbuddy
    bin/magento setup:upgrade
    ```

1. Enter settings in Stores > Configuration > Shipping Methods > Porterbuddy


## Submit shipment configuration

Magento requires these fields pupulated in order to submit shipment:

- General > General > Store Information: - name, phone, country, region, city, postal code, street
- Sales > Shipping Settings > Origin: - country, region, postal code, city, street


## Enabling IP based postcode lookup

1. Enable Tobai Geoip2 module in Magento

    ```
    bin/magento module:enable Tobai_GeoIp2
    bin/magento setup:upgrade
    ```

1. Enable and download Geoip2 city database

    - Go to Stores > Configuration > Tobai > GeoIP2 > GeoIP2Lite databases update
    - Choose Database For Update - City database
    - Save Config
    - Click "Update" button

1. Enable IP discovery in Porterbuddy settings

    Stores > Configuration > Sales > Shipping Methods > Porterbuddy > Location Discovery -
    IP location lookup
