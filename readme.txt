=== Intl DateTime Calendar ===
Contributors: ttwrpz, sigmarubyz
Tags: calendar, datetime, internationalization, i18n, formatting
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.0.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Display dates and times in various calendar systems (Buddhist, Islamic, etc.) while respecting WordPress settings and maintaining SEO friendliness.

== Description ==

Intl DateTime Calendar transforms how dates and times are displayed on your WordPress site by leveraging the ECMAScript Internationalization API (Intl). This plugin allows you to switch between different calendar systems like Buddhist, Islamic, Hebrew, and more, without affecting your database or SEO.

= Key Features =

* **Multiple Calendar Systems** - Support for Gregorian, Buddhist, Chinese, Hebrew, Islamic, Japanese, Persian and more
* **WordPress Integration** - Automatically uses your WordPress date/time format and locale settings
* **Block Editor Compatible** - Works with all WordPress core blocks, including custom date formats
* **SEO Friendly** - Maintains proper HTML datetime attributes for search engines
* **Multilingual Support** - Works with any locale WordPress supports
* **Site Performance** - Client-side formatting with minimal impact on page load time
* **Thai Language Support** - Special handling for Thai date formats including Buddhist calendar years

= Supported Calendar Systems =

* Gregorian (Western)
* Buddhist
* Chinese
* Coptic
* Ethiopic
* Hebrew
* Indian
* Islamic
* ISO 8601
* Japanese
* Persian
* Republic of China

= Use Cases =

* Buddhist websites in Thailand that need Buddhist Era years (BE)
* Islamic websites that need Hijri calendar dates
* Multilingual websites that want dates in native calendar systems
* Any website that needs specialized date formatting without affecting SEO

== Installation ==

1. Upload the `intl-datetime-calendar` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Settings' > 'Intl DateTime Calendar' to select your preferred calendar system
4. That's it! Your dates will now display in the selected calendar system

== Frequently Asked Questions ==

= Does this plugin change how dates are stored in the database? =

No. The plugin only changes how dates are displayed on the frontend. All dates are still stored in the WordPress database using the standard format.

= Will this affect my site's SEO? =

No. The plugin maintains proper HTML5 datetime attributes for SEO while changing only the displayed text. Search engines will still understand your content's dates correctly.

= How does this work with the Block Editor? =

The plugin automatically works with all WordPress core blocks that display dates and times, including Post Date, Post Time, Latest Posts, etc. It also respects any custom date formats you set in the block editor.

= Does it support custom date formats? =

Yes! The plugin respects both:
1. Your WordPress general date/time format settings
2. Custom formats specified in individual blocks

= Can I use this with multilingual sites? =

Yes! The plugin automatically uses your WordPress locale setting. If you're using a multilingual plugin that changes locales, the dates will follow the current language.

= What about Thai Buddhist calendar support? =

The plugin has special handling for the Thai locale with Buddhist calendar, including proper conversion of years to Buddhist Era (BE = CE + 543) and Thai month/day names.

= Can I use the plugin in my theme? =

Yes! Use this helper function in your theme:

`<?php
if (function_exists('intl_datetime_calendar_format_date')) {
    echo intl_datetime_calendar_format_date('2023-05-04', true, false);
}
?>`

Or use the shortcode in your content:

`[intl_datetime date="2023-05-04" type="date"]`

== Screenshots ==

1. Settings page showing calendar system selection
2. Example of Buddhist calendar dates on a Thai website
3. Example of Islamic calendar dates
4. Block editor date format support

== Changelog ==

= 1.0.1 =
* Renamed main plugin file to match WordPress.org naming convention (`intl-datetime-calendar.php`)

= 1.0.0 =
* Initial release
* Support for 12 different calendar systems
* WordPress block editor integration
* Automatic locale detection
* WordPress date/time format integration
* SEO-friendly implementation
* Thai Buddhist calendar special handling

== Upgrade Notice ==

= 1.0.1 =
Renamed main plugin file to fix compatibility with WordPress.org submission requirements

= 1.0.0 =
Initial release

== Additional Information ==

= Technical Details =

This plugin uses the ECMAScript Internationalization API (Intl) for client-side date formatting. The Intl API provides robust support for various calendar systems and locales. For older browsers that may not fully support all features, the plugin includes fallback formatting options.

= Browser Compatibility =

Modern browsers (last 2-3 years) fully support the plugin's functionality. For older browsers, basic date formatting will still work, but some advanced calendar systems might fall back to Gregorian.

= Privacy =

This plugin does not collect or share any personal data, and all date formatting is done client-side in the user's browser.