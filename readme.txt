=== WJ Website Backup ===
Contributors: kingsamar36
Tags: backup, wordpress backup, website backup, cloud backup
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 4.11.19
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
WJ Website Backup provides WordPress database and website file backups to the Webjooll Backup service. It includes backup history, retention information, manual backups, scheduled daily backups for an active connected subscription, and subscription management.

== Features ==
* Database backups
* Website file backups
* Remote cloud backup storage
* Backup history and retention
* Manual and scheduled daily backups
* Subscription and plan management
* Razorpay recurring subscription checkout

== Installation ==
1. Upload the plugin ZIP through WordPress Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Open WJ Website Backup in the WordPress admin area.
4. Review the available backup plans and choose a plan if remote backup service access is required.
5. Complete the connection and subscription process shown by the plugin.
6. Automatic backups are scheduled only after the website has an active subscription and a valid backup-service connection.

== External Services ==

WJ Website Backup requires the Webjooll Backup service for remote backup storage, backup history, subscription management, subscription plan information, country-specific pricing, and related backup-service operations. The service is provided by Webjooll.

When you connect the plugin or use subscription and backup features, the plugin may send the following information to the Webjooll Backup service: website domain and WordPress URL, administrator email address, website code, API authentication token, selected plan and subscription information, backup metadata, and the database/site backup data that you explicitly or automatically choose to back up. Backup data is sent when a backup operation is performed for a connected active subscription. Connection, subscription, plan, status, reconnect, and disconnect requests are sent when the corresponding plugin feature is used.

For country-specific subscription pricing, the plugin requests the administrator's consent before sending the administrator's public IP address to the Webjooll Backup service. The IP address is used to determine the country and display the applicable subscription plans and prices. If consent is not provided, the plugin does not make the plan-loading request that requires this IP address.

Webjooll Backup service:
* Terms and Conditions: https://www.webjooll.com/terms-and-conditions
* Privacy Policy: https://www.webjooll.com/privacy-policy

Razorpay is used for payment and recurring subscription checkout. Razorpay is contacted when a user starts the payment or subscription checkout. Payment and subscription information required to process and verify the transaction may be transmitted to Razorpay through its checkout and payment services.

Razorpay:
* Terms and Conditions: https://razorpay.com/terms/
* Privacy Policy: https://razorpay.com/privacy-policy/

This plugin does not use Google Analytics or another analytics service to track WordPress admin usage. Country detection for subscription pricing is handled by the Webjooll Backup service as described above.

== Privacy ==
The plugin does not activate or upload backups merely because it is installed or activated. A daily backup is scheduled only for an active, connected subscription, and the scheduled backup handler verifies the connection and subscription state before uploading data.

The administrator can choose whether to share their public IP address for country-specific subscription pricing. The IP address is used for country detection by the Webjooll Backup service as described in the External Services section.

== Changelog ==
= 4.11.19 =
* Improved WordPress.org compatibility and external-service documentation.
* Added disclosure of consent-based IP processing for country-specific subscription pricing.
* Moved admin JavaScript to enqueued files and restricted it to the plugin admin screen.
* Prevented activation from scheduling backups before the service is configured.
* Added the plugin owner to Contributors.

== Source Code ==

The human-readable source code for this plugin is available at:
https://github.com/workplace36/wj-website-backup

The repository contains the plugin source code and documentation
for reviewing and maintaining its JavaScript assets.