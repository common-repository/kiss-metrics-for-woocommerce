=== KISS Metrics for WooCommerce ===
Contributors: maxrice
Tags: woocommerce, kissmetrics
Requires at least: 3.3
Tested up to: 3.3
Stable tag: 0.3.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Adds KISS Metrics tracking to WooCommerce.

== Description ==

Adds KISS Metrics tracking code to WooCommerce and records these events:

* Signed in, Signed out, Signed up
* Viewed Product (Properties: Product Name)
* Viewed Cart
* Added to Cart (Properties: Product Name, Quantity)
* Started Checkout
* Purchased (Properties: Order ID, Revenue, Total Quantity, Payment Method)
* Commented (Properties: Content Type = Product Review OR Blog Post, Product Name if review)

Customers are automagically aliased with their wordpress username or email address once they login or register.

Admin users are not tracked by default.

*NOTE: This requires WooCommerce 1.5.5*

== Installation ==

1. Upload the entire 'woocommerce-kissmetrics' folder to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Browse to WooCommerce->Settings->Integrations->KISS Metrics and enter your API Key.

== Frequently Asked Questions ==

= My site isn't tracking! =

Check the live view under your KISS Metrics account and verify that you are receiving data.
You may have the incorrect API key or may be logged in as an admin. Try using a different browser to test your site.

= Can I track <facebook shares, email newsletter signups, my boyfriend's car, etc> with this plugin?

Probably! If you want to track an event not listed above, visit https://github.com/maxrice/kiss-metrics-for-woocommerce and add an issue.

== Changelog ==

= 0.3.0 =  5/6/12
* Added 'Purchased' event with 'Order ID, Revenue, Total Quantity, Payment Method' properties
* Added 'Commented' event with 'Content Type, Product Name' properties
* Added 'Signed in / Signed out / Signed up' events
* Added 'Added to Cart' event with 'Product Name, Quantity' properties
* Added 'Viewed Product' event with 'Product Name' property
* Added 'Viewed Cart' event
* Added identify: null to improve tracking accuracy when users share a computer
* Added KM PHP API integration (real-time, no cron)
* Added tracking script to wp-login header

= 0.2.0 = 5/3/12
* Added 'Started Checkout' event
* Added identity preference (identify visitor via email or wordpress username)
* Added API key setting
* Added admin section (WooCommerce->Settings->Integrations->KISS Metrics)
* Extends WC Integration class

= 0.1.0 = 5/2/2012
* Initial Release