=== Local Gamified Directory ===
Contributors: local-gamified-directory
Tags: directory, classifieds, gamification, bbpress, business listings
Requires at least: 6.3
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The Local Gamified Directory plugin delivers a community-powered business directory with classifieds, bbPress integration, a gamified points system, paid subscriptions, and token-based advertising in one package.

== Description ==

Local Gamified Directory equips WordPress sites with everything needed to run a hyperlocal hub:

* **Business listings** with owner assignments, claim workflows, taxonomies for categories and regions, Google Place IDs, and moderated submissions.
* **Classified ads** that can auto-publish or queue for approval and automatically expire after configurable durations.
* **bbPress forums** integration with points for new topics and replies plus badges and ranks for engaged members.
* **Gamification engine** that tracks points, awards badges, maintains leaderboards, and lets administrators adjust balances.
* **Token-based advertising** so businesses can spend their points on sponsored placements targeted by category or region.
* **PayPal subscriptions** to deliver premium, ad-free listings, monthly token allowances, and other perks.
* **Minimalist, responsive front-end forms** for submitting businesses, classifieds, ads, and for managing user dashboards.
* **Automated notifications** that alert admins and owners about new submissions, claim requests, approvals, and expiring classifieds.

The plugin is organized into dedicated modules for front-end forms, gamification, ads, and subscriptions so you can easily extend or override specific functionality. All output uses semantic markup and ships with a lightweight stylesheet to get you started.

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/` or install through the WordPress plugins screen.
2. Activate the plugin through the "Plugins" menu.
3. Visit **Settings → Directory Subscriptions** to configure your PayPal button ID, subscription mode, and monthly token allowance.
4. Create pages that contain the shortcodes you plan to use (see below) and assign appropriate user roles to your community members.
5. (Optional) Configure bbPress forums to unlock forum-based gamification rewards.

== Shortcodes ==

* `[lgd_business_submission_form]` – Front-end form for business owners to submit or update their listing.
* `[lgd_classified_submission_form]` – Front-end form for creating classifieds.
* `[lgd_user_dashboard]` – Displays the logged-in user dashboard with business listings, classifieds, subscriptions, and ad tools.
* `[lgd_leaderboard type="all" timeframe="all" role="any" limit="10"]` – Renders a leaderboard (type = points, timeframe = daily/weekly/monthly/all, role filters business/community users).
* `[lgd_ad_form]` – Lets business owners spend points to create sponsored ads.
* `[lgd_subscription_button]` – Outputs a PayPal subscription button for premium upgrades.

== Roles & Capabilities ==

* **Business Owner** – Manage owned business listings, classifieds, forum posts, and create ads.
* **Community Member** – Submit classifieds and participate in forums.
* Administrators maintain approval workflows, configure pricing, award points, and manage ads or subscriptions.

== Frequently Asked Questions ==

= Does this plugin require bbPress? =

bbPress is optional. When active, the plugin awards points for forum activity automatically. Without bbPress the rest of the directory, classifieds, ads, and subscription features continue to function.

= Can I customize the front-end templates? =

Yes. The plugin outputs minimal markup so you can override styling with your theme or child theme. Developers can hook into the form rendering actions and filters located throughout the front-end class.

= How do premium subscriptions work? =

Configure your PayPal hosted button or REST credentials on the subscriptions settings screen. When PayPal sends an IPN notification, the plugin activates premium status, grants monthly tokens, and removes ads from the member's listings.

== Screenshots ==

1. Business submission form and dashboard.
2. Classified submission form.
3. Leaderboard example.

== Changelog ==

= 0.1.0 =
* Initial release with business listings, classifieds, gamification, ads, and PayPal subscriptions.

== Upgrade Notice ==

= 0.1.0 =
This is the first public release of Local Gamified Directory.
