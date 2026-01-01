# Intl DateTime Calendar

[![WordPress Compatible](https://img.shields.io/badge/WordPress-5.0%20to%206.9-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.0%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-yellow.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![No jQuery](https://img.shields.io/badge/jQuery-Not%20Required-green.svg)](https://github.com/ttwrpz/intl-datetime-calendar)

A WordPress plugin that displays dates and times in various calendar systems using the ECMAScript Internationalization API (Intl) while maintaining SEO friendliness.

## Features

- Support for multiple calendar systems (Gregorian, Buddhist, Chinese, Hebrew, Islamic, etc.)
- Configurable date and time formatting styles
- Automatic localization based on language settings
- Full support for WordPress block editor (Gutenberg)
- SEO-friendly implementation (original datetime values are preserved in the HTML)
- Lightweight with no jQuery dependency
- Optimized with minified JavaScript for production

## Usage

Once activated, the plugin will automatically format all dates and times on your site according to your selected calendar system and locale.

### Configuration

1. Go to 'Settings' > 'Intl DateTime Calendar' in the WordPress admin
2. Select your preferred calendar system (Gregorian, Buddhist, Chinese, etc.)
3. Choose your locale (e.g., 'en', 'th-TH', 'ja-JP')
4. Set date and time style preferences (Full, Long, Medium, Short)
5. Save changes

### Calendar Systems

The plugin supports the following calendar systems:

- `buddhist`: Buddhist Calendar
- `chinese`: Chinese Calendar
- `coptic`: Coptic Calendar
- `dangi`: Dangi (Korean) Calendar
- `ethioaa`: Ethiopic (Amete Alem) Calendar
- `ethiopic`: Ethiopic Calendar
- `gregory`: Gregorian (Western) Calendar
- `hebrew`: Hebrew Calendar
- `indian`: Indian Calendar
- `islamic`: Islamic Calendar
- `islamic-civil`: Islamic (Civil) Calendar
- `islamic-rgsa`: Islamic (Saudi Arabia) Calendar
- `islamic-tbla`: Islamic (Tabular) Calendar
- `islamic-umalqura`: Islamic (Umm al-Qura) Calendar
- `iso8601`: ISO 8601 Calendar
- `japanese`: Japanese Calendar
- `persian`: Persian Calendar
- `roc`: Republic of China Calendar

### Date and Time Styles

Each style provides a different level of formatting detail:

- `full`: e.g., "Wednesday, December 31, 2023"
- `long`: e.g., "December 31, 2023"
- `medium`: e.g., "Dec 31, 2023"
- `short`: e.g., "12/31/23"

### Advanced Usage

#### Manual Integration

If you need to format a specific date manually in your theme or plugin, you can use this format:

```php
<?php
$timestamp = strtotime('2023-12-31 23:59:59') * 1000; // Convert to milliseconds for JS
?>

<span class="intl-datetime intl-datetime-auto" 
      data-intl-datetime="<?php echo esc_attr($timestamp); ?>" 
      data-calendar="buddhist" 
      data-locale="th-TH" 
      data-date-style="full" 
      data-time-style="long">
    December 31, 2023
</span>
```

#### JavaScript API

You can also format dates programmatically using the provided JavaScript API:

```javascript
// Example: Format the current date
const timestamp = Date.now();
const options = {
    calendar: 'buddhist',
    locale: 'th-TH',
    dateStyle: 'full',
    timeStyle: 'long'
};

const formatter = new Intl.DateTimeFormat(options.locale, {
    calendar: options.calendar,
    dateStyle: options.dateStyle,
    timeStyle: options.timeStyle
});

const formattedDate = formatter.format(new Date(timestamp));
console.log(formattedDate);
```

## Browser Compatibility

This plugin utilizes the Intl API, which is supported in all modern browsers:

- Chrome 76+
- Firefox 72+
- Safari 14+
- Edge 79+

For older browsers, the plugin will fall back to the browser's default date formatting.

## Updates

The plugin automatically checks for updates from the GitHub repository. When a new version is released, you'll receive an update notification in your WordPress admin dashboard.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This plugin is licensed under the GPL v2 or later.