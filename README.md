# Intl DateTime Calendar

[![WordPress](https://img.shields.io/badge/WordPress-6.5%20to%207.1-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-yellow.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

A WordPress plugin that writes dates in any calendar system. It uses the digits and wording your readers expect. It leaves your database and your machine readable dates untouched.

## What it does

Pick a calendar in Settings and your posts, archives and comments start showing dates the way your readers write them.

```
Gregorian    May 4, 2025
Buddhist     4 พฤษภาคม 2568        with Thai digits: ๔ พฤษภาคม ๒๕๖๘
Hijri        ٦ ذو القعدة ١٤٤٦
Persian      ۱۴ اردیبهشت ۱۴۰۴
Japanese     令和7年5月4日
Hebrew       ו׳ באייר תשפ״ה
```

Nothing in the database changes. The HTML `datetime` attribute that search engines read stays Gregorian and unambiguous, so only the text a person sees is converted.

## How it works

Where the PHP `intl` extension is available, dates are converted on the server through ICU. Readers see the right calendar straight away, there is no flicker while the page loads, dates are right for anyone without JavaScript, and the plugin adds no JavaScript to your pages at all.

Where `intl` is missing, the same conversion runs in the browser instead and a small script is loaded. Site Health reports which of the two your site is using.

Both paths render from a single format specification. CI compares them against each other on every commit so they cannot drift.

## Requirements

- WordPress 6.5 or newer
- PHP 8.1 or newer
- The PHP `intl` extension is recommended but not required

## Usage

### Settings

Go to Settings, then Intl DateTime Calendar. Choose a calendar. If you want something other than your language default, choose your digits. The preview shows today's date in whatever you pick.

Your existing WordPress date format is used as is, including any custom format set on an individual block.

### In a theme

```php
if ( function_exists( 'intl_datetime_calendar_format_date' ) ) {
    echo intl_datetime_calendar_format_date( '2026-08-19' );
    echo intl_datetime_calendar_format_date( $timestamp, 'l, j F Y' );
    echo intl_datetime_calendar_format_date( $post_date_object, 'd/m/Y' );
}
```

Accepts a date string, a Unix timestamp or any `DateTimeInterface`, and returns a complete `<time>` element.

### As a shortcode

```
[intl_datetime date="2026-08-19"]
[intl_datetime date="2026-08-19 14:30" type="datetime"]
[intl_datetime date="2026-08-19" format="l, j F Y"]
```

A date that cannot be read is shown back to you unchanged. It is never quietly turned into a different date.

## Filters

| Filter | Purpose |
| --- | --- |
| `intl_datetime_calendar_calendar` | Choose the calendar per request, for example a different one per language |
| `intl_datetime_calendar_locale` | Override the locale dates are written in |
| `intl_datetime_calendar_numbering_system` | Override which digits are used |
| `intl_datetime_calendar_should_convert` | Leave particular dates in the Gregorian calendar |

Conversion is hooked to `get_the_date`, `get_the_time`, `get_the_modified_date`, `get_the_modified_time`, `get_comment_date` and `get_comment_time`, and skipped for cron, REST, AJAX, XML-RPC, WP-CLI, feeds and the admin. `wp_date()` itself is deliberately not hooked: it is used for storage as well as display, and no format allow list can separate the two, because a site may set its date format to `Y-m-d`.

A multilingual site can give each language its own calendar:

```php
add_filter( 'intl_datetime_calendar_calendar', function ( $calendar, $locale ) {
    if ( str_starts_with( $locale, 'th' ) ) {
        return 'buddhist';
    }

    if ( str_starts_with( $locale, 'ar' ) ) {
        return 'islamic-umalqura';
    }

    return $calendar;
}, 10, 2 );
```

If something still needs a Gregorian date, exclude it:

```php
add_filter( 'intl_datetime_calendar_should_convert', function ( $convert, $format ) {
    return is_singular( 'invoice' ) ? false : $convert;
}, 10, 2 );
```

## Supported calendars

`buddhist`, `chinese`, `coptic`, `dangi`, `ethioaa`, `ethiopic`, `gregory`, `hebrew`, `indian`, `islamic`, `islamic-civil`, `islamic-rgsa`, `islamic-tbla`, `islamic-umalqura`, `iso8601`, `japanese`, `persian`, `roc`

## Supported digits

Every decimal numbering system Unicode defines, around 77 in total. The settings screen lists the familiar ones first (`latn`, `arab`, `arabext`, `beng`, `deva`, `thai`, `laoo`, `mymr`, `tibt`, `guru`, `gujr`, `knda`, `mlym`, `orya`, `tamldec`, `telu`, `sinh`, `khmr`, `hanidec`, `fullwide`) and then every other script, each shown next to a sample of its digits. Leave it unset to follow the site language.

The list is filtered against what the server's ICU actually supports, so an option is never offered that the server cannot honour. Algorithmic systems such as Roman numerals are excluded, because they are not ten positional digits and cannot write a date.

## Development

```bash
composer install
npm install

npm run build          # rebuild js/ from js/src/
node build.mjs --check # fail if the committed bundles are stale

php tests/php/native-parity.php   # the engine must match PHP's own date()
php tests/php/regressions.php     # bugs fixed in 2.0.0 must stay fixed
npm test                          # the PHP and browser renderers must agree

vendor/bin/phpcs
```

### Layout

```
src/Format/     the format specification and the ICU renderer
src/Render/     where dates and blocks are converted
src/Settings/   options and the settings screen
src/Support/    Site Health
js/src/         browser sources, bundled into js/ by build.mjs
tests/          parity and regression suites
```

### Notes on the engine

Two behaviours drive the design and are worth knowing before changing it.

ICU picks different word forms depending on which fields surround a date. Russian writes `май` for a month on its own but `мая` inside a full date, and Czech, Polish and Catalan behave the same way. Fields are therefore always resolved in the context of the whole format rather than one at a time.

The server renders through ICU pattern letters, which give exact control over width, while the browser renders through Intl option matching, where the locale decides. Widths are reapplied in the browser so the two agree. One difference remains, listed in `tests/js/parity.test.js`, and the test fails if it ever stops applying so the exception cannot outlive the platform behaviour that caused it.

## License

GPL v2 or later.
