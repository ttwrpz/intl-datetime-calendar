=== Intl DateTime Calendar ===
Contributors: sigmarubyz
Tags: calendar, datetime, internationalization, i18n, formatting
Requires at least: 6.5
Tested up to: 7.1
Stable tag: 2.0.1
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Show dates in Buddhist, Hijri, Hebrew, Persian and other calendars, with the digits and wording your readers expect. Your database never changes.

== Description ==

Intl DateTime Calendar changes how dates are written on your site without changing what is stored. You pick a calendar. Your posts, archives and comments then show dates the way your readers write them. Every date stays in the database exactly as WordPress saved it. The machine readable date that search engines look for stays untouched.

= What it does =

* **Any calendar system** including Buddhist, Hijri, Hebrew, Persian, Japanese, Indian, Coptic, Ethiopic and Chinese
* **Native digits** so a Thai site can show ๔ พฤษภาคม ๒๕๖๘ and an Arabic site can show ٦ ذو القعدة ١٤٤٦
* **Your existing date format** is respected, including any custom format set on an individual block
* **Nothing to relearn** because the calendar applies everywhere WordPress writes a date
* **Correct for search engines** because the underlying datetime attribute stays Gregorian and unambiguous
* **No database changes** at all. Switching calendars or removing the plugin leaves your content untouched

= Dates are written before the page is sent =

On a host with the PHP intl extension, dates are converted on the server. Readers see the right calendar immediately. There is no flicker as the page loads. Dates remain correct for anyone browsing with JavaScript turned off. Under this setup, the plugin adds no JavaScript to your pages.

If your host lacks the intl extension, everything still works. The conversion moves to the browser and a small script is added to each page. Site Health tells you which method your site is using.

= Supported calendars =

Gregorian, Buddhist, Chinese, Coptic, Dangi, Ethiopic, Ethiopic Amete Alem, Hebrew, Indian, Islamic, Islamic Civil, Islamic Saudi Arabia, Islamic Tabular, Islamic Umm al-Qura, ISO 8601, Japanese, Persian and Republic of China.

= Supported digits =

Every decimal numbering system Unicode defines, which is around 77 of them. The familiar ones are listed first. Western, Arabic Indic, Eastern Arabic Indic, Bengali, Devanagari, Thai, Lao, Myanmar, Tibetan, Gurmukhi, Gujarati, Kannada, Malayalam, Odia, Tamil, Telugu, Sinhala, Khmer, Han decimal and full width. Every other script follows, each shown next to a sample of its digits.

Only the systems your server can actually write are offered, so the list matches the version of ICU your host runs. You can also leave this alone and let the site language decide.

== Installation ==

1. Upload the `intl-datetime-calendar` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to Settings, then Intl DateTime Calendar
4. Choose a calendar, and choose your digits if you want something other than your language default

The preview on the settings page shows today's date in whatever you pick, so you can see the result before saving.

== Frequently Asked Questions ==

= Does this change what is stored in my database? =

No. Only the text a visitor reads changes. Every date is stored exactly as WordPress stored it. You can switch calendars whenever you like. Removing the plugin puts everything back the way it was.

= Will this hurt my SEO? =

No. Each date keeps its machine readable HTML datetime value in the Gregorian calendar. Search engines read this value. Only the wording a person sees is converted.

= Does it work with the block editor? =

Yes. Dates in the Date block, Query Loop, comments and archives are all covered. This includes any custom format set on an individual block.

= Can I use it in my theme? =

Yes.

`<?php
if ( function_exists( 'intl_datetime_calendar_format_date' ) ) {
    echo intl_datetime_calendar_format_date( '2026-08-19' );
}
?>`

You can pass a date string, a Unix timestamp or a DateTime object, and a second argument to choose the format.

Or use the shortcode in your content.

`[intl_datetime date="2026-08-19" type="date"]`

= Can different languages use different calendars? =

Yes. On a multilingual site each language can have its own calendar, so a Thai reader sees Buddhist years while an English reader on the same site sees Gregorian ones. Use the `intl_datetime_calendar_calendar` filter to set this up.

= Another plugin reads dates from my pages and now gets confused. What do I do? =

Use the `intl_datetime_calendar_should_convert` filter to leave particular dates in the Gregorian calendar, or turn off server rendering on the settings page.

= What is the intl extension and do I need it? =

It is a standard PHP extension that most hosts already provide. You do not strictly need it. However, with it, your dates are written before the page is sent instead of in the reader's browser. This is faster and works without JavaScript. Site Health tells you whether you have it.

== Screenshots ==

1. The settings page, with a live preview of the chosen calendar and digits
2. A Thai site showing Buddhist years with Thai digits
3. An Arabic site showing Hijri dates
4. Site Health reporting that dates are converted on the server

== Changelog ==

= 2.0.1 =

Fixes a fatal error that could take the front end down after switching the site language.

* Fix: Switching the site to a language ICU has no data for, such as South Azerbaijani, Saraiki, Hazaragi or Northern Kurdish, brought down the front end with a critical error. Those languages now keep the dates WordPress itself produces, which are already translated
* Fix: A failed date formatter is no longer reused for the rest of the request, so one bad locale cannot repeat the failure on every date
* Fix: A site with no language set no longer builds an invalid locale
* Fix: A language with a region ICU does not carry now falls back to the language rather than giving up
* Fix: Formatting can no longer raise out of the plugin at all. A date that cannot be converted is left as WordPress wrote it

= 2.0.0 =

This release rebuilds how dates are formatted. Sites using the Gregorian calendar see no change at all. We check this automatically against PHP's own date function on every commit.

New:

* Dates are now written on the server where the PHP intl extension is available. There is no longer a flash of Gregorian dates while the page loads. Dates are correct without JavaScript.
* Added a choice of digits covering every decimal numbering system Unicode defines. Thai, Arabic, Persian, Bengali, Devanagari, Khmer and many other sites can use their own numerals.
* Relative dates such as "3 days ago" are now translated instead of being left in English
* Added the `intl_datetime_calendar_format_date()` function, which the FAQ has described since 1.0.2 but which was never actually included
* Added a Site Health check reporting whether dates are converted on the server or in the browser
* Added the `intl_datetime_calendar_calendar`, `intl_datetime_calendar_locale`, `intl_datetime_calendar_numbering_system` and `intl_datetime_calendar_should_convert` filters
* Different languages can now use different calendars on a multilingual site
* Removing the plugin now cleans up its settings

Fixed:

* Dates from the shortcode were up to a day out on any site not set to UTC. They published a timezone offset that did not match the time beside it.
* Custom date formats were ignored. A format of `Y-m-d` and a format of `d/m/Y` both produced the same output, and any text or separator in a format was discarded
* A 12 hour format such as `h:i A` produced output like `08 PM:5 PM`
* Minutes and seconds lost their leading zero, so five past showed as `5` rather than `05`
* Buddhist and Hijri dates leaked an era marker into numeric formats, giving `04/05/พ.ศ. 2568` instead of `04/05/2568`
* Custom block formats were silently dropped for dates inside a Query Loop
* Impossible dates such as 31 February were quietly changed to a real date instead of being reported
* A date of 1 January 1970 was treated as invalid
* Month names now take the correct grammatical form in Russian, Czech, Polish, Catalan and other languages that inflect them
* Rendering could stop the page with a fatal error on a server without PHP's DOM extension
* Block markup containing `<picture>` or `<source>` could be corrupted while dates were being processed
* Three of the four block filters had never run, because the blocks they named do not exist in WordPress

Changed:

* Requires PHP 8.1 and WordPress 6.5
* Block markup is now edited with WordPress's own HTML API instead of a regular expression and an HTML4 parser
* The settings page no longer uses an inline script
* The browser script is only loaded when the server cannot convert dates itself
* The minified script is now generated by a build step and checked automatically, instead of being maintained by hand

= 1.0.3 =
* Compatibility: Tested and confirmed working with WordPress 6.9
* Performance: Added settings caching to reduce database queries
* Performance: Added minified JavaScript for production environments
* Performance: Removed jQuery dependency. It now uses vanilla JavaScript.
* Feature: Added live date preview on settings page
* Feature: Added 6 new calendar systems (Dangi, Ethiopic Amete Alem, Islamic variants)
* Feature: Skip conversion for human-diff format elements
* Fix: Prevent repeated date conversions (elements now processed only once)
* Fix: Always use WordPress site language instead of browser language
* Code Quality: Refactored regex patterns as class constants
* Security: Added direct access prevention checks to all PHP files

= 1.0.2 =
* Security: Fixed authenticated (Contributor+) stored XSS vulnerability via date parameter (CVE-2025-8293)
* Security: Added comprehensive input sanitization for all date inputs
* Security: Added proper output escaping for all HTML attributes and content
* Security: Implemented validation for shortcode parameters
* Improvement: Enhanced date validation to prevent malformed input

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

= 2.0.1 =
Fixes a critical error that could take the front end down on sites using a language ICU does not carry, such as South Azerbaijani. Update if you run 2.0.0.

= 2.0.0 =
Dates are now written on the server. The flash of Gregorian dates while a page loads is gone. Dates remain correct without JavaScript. Adds a choice of digits for Thai, Arabic, Persian and other numerals. Fixes custom date formats, which were previously ignored. Requires PHP 8.1 and WordPress 6.5.

= 1.0.3 =
Performance update with WordPress 6.9 compatibility. Reduced database queries, removed jQuery dependency, and added minified JavaScript for faster page loads.

= 1.0.2 =
SECURITY UPDATE: Critical fix for stored XSS vulnerability (CVE-2025-8293). All users should update immediately to prevent script injection attacks by authenticated users with Contributor+ access. This patches a security issue affecting all versions up to 1.0.1.

= 1.0.1 =
Renamed main plugin file to fix compatibility with WordPress.org submission requirements

= 1.0.0 =
Initial release

== Privacy ==

This plugin does not collect, store or send any personal data.
