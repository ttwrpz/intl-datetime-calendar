/**
 * Intl DateTime Calendar Plugin JavaScript
 *
 * This script handles formatting dates and times using the Intl API
 */
(function ($) {
    'use strict';

    const formatterCache = new Map();

    /**
     * Check if browser supports Intl API with necessary features
     * @returns {boolean} Whether the browser has adequate Intl support
     */
    function isIntlSupported() {
        try {
            if (typeof Intl === 'undefined' || typeof Intl.DateTimeFormat === 'undefined') {
                return false;
            }

            // Check for Intl.supportedValuesOf for calendar types
            if (typeof Intl.supportedValuesOf !== 'function') {
                console.warn('Intl.supportedValuesOf not available - limited calendar support');
            }

            new Intl.DateTimeFormat('en', {dateStyle: 'full'}).format(new Date());
            return true;
        } catch (e) {
            console.error('Intl API check failed:', e);
            return false;
        }
    }

    /**
     * Get cached formatter or create a new one
     * @param {string} locale - Locale string
     * @param {Object} options - Formatter options
     * @returns {Intl.DateTimeFormat} The formatter object
     */
    function getFormatter(locale, options) {
        const cacheKey = `${locale}|${JSON.stringify(options)}`;

        if (!formatterCache.has(cacheKey)) {
            formatterCache.set(cacheKey, new Intl.DateTimeFormat(locale, options));
        }

        return formatterCache.get(cacheKey);
    }

    /**
     * Converts WordPress PHP date format to Intl.DateTimeFormat options
     *
     * @param {String} phpFormat - PHP date format string (e.g., 'F j, Y')
     * @param {String} type - 'date', 'time', or 'datetime'
     * @returns {Object} Intl.DateTimeFormat options
     */
    function phpFormatToIntlOptions(phpFormat, type) {
        const options = {};

        if (type === 'date' || type === 'datetime') {
            options.year = 'numeric';
            options.month = 'long';
            options.day = 'numeric';
        }

        if (type === 'time' || type === 'datetime') {
            options.hour = '2-digit';
            options.minute = '2-digit';
        }

        if (!phpFormat) {
            console.warn('Intl DateTime Calendar - No format string provided, using defaults');
            return options;
        }

        // PHP format characters and their Intl equivalent options
        const formatMap = {
            // Year
            'Y': {year: 'numeric'},          // 2023
            'y': {year: '2-digit'},          // 23

            // Month
            'F': {month: 'long'},            // January
            'M': {month: 'short'},           // Jan
            'm': {month: '2-digit'},         // 01
            'n': {month: 'numeric'},         // 1

            // Day
            'd': {day: '2-digit'},           // 01
            'j': {day: 'numeric'},           // 1
            'D': {weekday: 'short'},         // Mon
            'l': {weekday: 'long'},          // Monday

            // Time
            'g': {hour: 'numeric', hour12: true},       // 1-12
            'h': {hour: '2-digit', hour12: true},       // 01-12
            'G': {hour: 'numeric', hour12: false},      // 0-23
            'H': {hour: '2-digit', hour12: false},      // 00-23
            'i': {minute: '2-digit'},                   // 00-59
            's': {second: '2-digit'},                   // 00-59
            'a': {hour12: true, hourCycle: 'h12'},      // am/pm
            'A': {hour12: true, hourCycle: 'h12'}       // AM/PM
        };

        // Check for specific format patterns
        for (const [char, intlOpt] of Object.entries(formatMap)) {
            if (phpFormat.includes(char)) {
                Object.assign(options, intlOpt);
            }
        }

        return options;
    }

    /**
     * Format a date using a custom PHP format string leveraging Intl API
     *
     * @param {Date} date - JavaScript date object
     * @param {String} format - PHP date format string
     * @param {String} locale - Locale string (e.g., 'th-TH', 'en-US')
     * @param {String} calendar - Calendar type (e.g., 'buddhist', 'gregory')
     * @returns {String} Formatted date string
     */
    function formatDateWithCustomFormat(date, format, locale, calendar) {
        if (!date || !format) {
            return '';
        }

        const getFormatterFor = (formatChar, extraOptions = {}) => {
            const cacheKey = `${formatChar}|${locale}|${calendar}|${JSON.stringify(extraOptions)}`;

            if (!formatterCache.has(cacheKey)) {
                const options = {calendar, ...extraOptions};

                switch (formatChar) {
                    // Year formatters
                    case 'Y':
                        options.year = 'numeric';
                        break;
                    case 'y':
                        options.year = '2-digit';
                        break;

                    // Month formatters
                    case 'F':
                        options.month = 'long';
                        break;
                    case 'M':
                        options.month = 'short';
                        break;
                    case 'm':
                        options.month = '2-digit';
                        break;
                    case 'n':
                        options.month = 'numeric';
                        break;

                    // Day formatters
                    case 'd':
                        options.day = '2-digit';
                        break;
                    case 'j':
                        options.day = 'numeric';
                        break;
                    case 'D':
                        options.weekday = 'short';
                        break;
                    case 'l':
                        options.weekday = 'long';
                        break;

                    // Time formatters
                    case 'a':
                    case 'A':
                        options.hour = 'numeric';
                        options.hour12 = true;
                        break;
                    case 'g':
                        options.hour = 'numeric';
                        options.hour12 = true;
                        break;
                    case 'h':
                        options.hour = '2-digit';
                        options.hour12 = true;
                        break;
                    case 'G':
                        options.hour = 'numeric';
                        options.hour12 = false;
                        break;
                    case 'H':
                        options.hour = '2-digit';
                        options.hour12 = false;
                        break;
                    case 'i':
                        options.minute = '2-digit';
                        break;
                    case 's':
                        options.second = '2-digit';
                        break;
                }

                formatterCache.set(cacheKey, new Intl.DateTimeFormat(locale, options));
            }

            return formatterCache.get(cacheKey);
        };

        // Handle day of week numerical value separately - can't use Intl API for this
        const dayOfWeekMap = {
            'w': date.getDay(), // 0-6 (Sunday is 0)
            'N': date.getDay() === 0 ? 7 : date.getDay() // 1-7 (Monday is 1, Sunday is 7)
        };

        // Special case for Buddhist years when using Thai locale
        const isThai = locale && locale.startsWith('th');
        const isBuddhist = calendar === 'buddhist';

        // Process each character in the format string
        let result = '';
        let i = 0;
        while (i < format.length) {
            const char = format.charAt(i);

            // Handle special cases for Thai Buddhist calendar
            if (isThai && isBuddhist && (char === 'B' || char === 'b')) {
                if (char === 'B') {
                    result += String(date.getFullYear() + 543);
                } else {
                    result += String(date.getFullYear() + 543).slice(-2); // 2 digits
                }
                i++;
                continue;
            }

            // Handle numeric day of week (w, N) that can't use Intl API
            if (dayOfWeekMap[char] !== undefined) {
                result += dayOfWeekMap[char];
                i++;
                continue;
            }

            // Handle escaped characters
            if (char === '\\' && i + 1 < format.length) {
                result += format.charAt(++i);
                i++;
                continue;
            }

            // Try to use Intl formatter for this character
            if (/[YyFMmndjDlaAgGhHis]/.test(char)) {
                try {
                    const formatter = getFormatterFor(char);

                    // Special handling for AM/PM indicators
                    if (char === 'a' || char === 'A') {
                        const timeParts = formatter.formatToParts(date);
                        const ampmPart = timeParts.find(part => part.type === 'dayPeriod');
                        if (ampmPart) {
                            result += char === 'a' ?
                                ampmPart.value.toLowerCase() :
                                ampmPart.value.toUpperCase();
                        } else {
                            // Fallback if no dayPeriod part found
                            result += char === 'a' ?
                                (date.getHours() < 12 ? 'am' : 'pm') :
                                (date.getHours() < 12 ? 'AM' : 'PM');
                        }
                    }
                    // Special handling for 12-hour format numeric hours
                    else if (char === 'g') {
                        const timeParts = formatter.formatToParts(date);
                        const hourPart = timeParts.find(part => part.type === 'hour');
                        if (hourPart) {
                            result += hourPart.value;
                        } else {
                            // Fallback
                            result += date.getHours() % 12 || 12;
                        }
                    }
                    // For all other formatters, use the formatted value directly
                    else {
                        result += formatter.format(date);
                    }
                } catch (e) {
                    console.warn(`Error formatting '${char}' with Intl API:`, e);
                    // Fall back to basic implementation for this character
                    result += fallbackFormat(date, char);
                }
            }
            // Add any other characters as-is (e.g., separators, text)
            else {
                result += char;
            }

            i++;
        }

        return result;
    }

    /**
     * Format a date using Intl.DateTimeFormat with WordPress or custom settings
     *
     * @param {Number} timestamp - The timestamp in milliseconds
     * @param {Object} settings - Formatting settings
     * @returns {String} Formatted date string
     */
    function formatDateTime(timestamp, settings) {
        if (!timestamp) {
            return '';
        }

        try {
            const date = new Date(parseInt(timestamp, 10));
            if (isNaN(date.getTime())) {
                console.error('Invalid timestamp:', timestamp);
                return '';
            }

            // Get the locale and calendar settings
            const locale = settings.locale ||
                (window.intlDateTimeCalendarSettings && window.intlDateTimeCalendarSettings.locale) ||
                'en';
            const calendar = settings.calendar ||
                (window.intlDateTimeCalendarSettings && window.intlDateTimeCalendarSettings.calendar_type) ||
                'gregory';

            // Check if a custom format specified by the block
            if ((settings.dateFormat === 'custom' || settings.timeFormat === 'custom') &&
                settings.customFormat) {
                // Use our improved custom format function
                return formatDateWithCustomFormat(date, settings.customFormat, locale, calendar);
            }

            // Determine if formatting date, time, or both
            const formatType = settings.type || 'date';

            // Get appropriate WordPress format with fallbacks
            let wpFormat;
            if (formatType === 'date') {
                wpFormat = window.intlDateTimeCalendarSettings?.wp_date_format || 'F j, Y';
            } else if (formatType === 'time') {
                wpFormat = window.intlDateTimeCalendarSettings?.wp_time_format || 'g:i a';
            } else {
                const dateFormat = window.intlDateTimeCalendarSettings?.wp_date_format || 'F j, Y';
                const timeFormat = window.intlDateTimeCalendarSettings?.wp_time_format || 'g:i a';
                wpFormat = `${dateFormat} ${timeFormat}`;
            }

            // Convert WordPress format to Intl options
            const formatOptions = phpFormatToIntlOptions(wpFormat, formatType);
            formatOptions.calendar = calendar;

            const formatter = getFormatter(locale, formatOptions);
            return formatter.format(date);
        } catch (e) {
            console.error('Intl DateTime Calendar - Error formatting date:', e, settings);
            try {
                const date = new Date(parseInt(timestamp, 10));
                return date.toLocaleString(
                    (settings.locale ||
                        (window.intlDateTimeCalendarSettings?.locale) ||
                        'en')
                );
            } catch (fallbackError) {
                console.error('Even fallback formatting failed:', fallbackError);
                return '';
            }
        }
    }

    /**
     * Fallback formatter for when Intl API fails
     * @param {Date} date - Date to format
     * @param {string} char - Format character
     * @returns {string} Formatted output
     */
    function fallbackFormat(date, char) {
        const formatChars = {
            // Day
            'd': padZero(date.getDate()),                         // 01-31
            'j': date.getDate(),                                  // 1-31
            'D': ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][date.getDay()], // Short day name
            'l': ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][date.getDay()], // Full day name
            'w': date.getDay(),                                   // 0-6
            'N': date.getDay() === 0 ? 7 : date.getDay(),         // 1-7 (Monday is 1, Sunday is 7)

            // Month
            'm': padZero(date.getMonth() + 1),               // 01-12
            'n': date.getMonth() + 1,                             // 1-12
            'F': ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'][date.getMonth()], // Full month name
            'M': ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][date.getMonth()], // Short month name

            // Year
            'Y': date.getFullYear(),                              // 2023
            'y': String(date.getFullYear()).slice(-2),            // 23

            // Time
            'a': date.getHours() < 12 ? 'am' : 'pm',              // am/pm
            'A': date.getHours() < 12 ? 'AM' : 'PM',              // AM/PM
            'g': date.getHours() % 12 || 12,                      // 1-12
            'h': padZero(date.getHours() % 12 || 12),        // 01-12
            'G': date.getHours(),                                 // 0-23
            'H': padZero(date.getHours()),                        // 00-23
            'i': padZero(date.getMinutes()),                      // 00-59
            's': padZero(date.getSeconds()),                      // 00-59
        };

        return formatChars[char] !== undefined ? formatChars[char] : char;
    }

    /**
     * Helper function to pad numbers with leading zeros
     * @param {number} num - Number to pad
     * @returns {string|number} Padded number
     */
    function padZero(num) {
        return num < 10 ? '0' + num : num;
    }

    /**
     * Safely extract settings from global object
     * @returns {Object} Settings object with defaults
     */
    function getSettings() {
        const settings = window.intlDateTimeCalendarSettings || {};
        return {
            locale: settings.locale || 'en',
            calendar_type: settings.calendar_type || 'gregory',
            wp_date_format: settings.wp_date_format || 'F j, Y',
            wp_time_format: settings.wp_time_format || 'g:i a'
        };
    }

    /**
     * Process and format all datetime elements on the page
     */
    function processDateTimeElements() {
        const settings = getSettings();

        $('.intl-datetime-element').each(function () {
            const $el = $(this);
            const timestamp = $el.data('intl-datetime');

            if (!timestamp) {
                return;
            }

            const elementSettings = {
                calendar: $el.data('calendar') || settings.calendar_type,
                locale: settings.locale, // Always use WordPress locale
                type: 'datetime', // Default to full datetime
                dateFormat: $el.data('date-format') || 'wp',
                timeFormat: $el.data('time-format') || 'wp',
                customFormat: $el.data('custom-format') || null
            };

            const hasDate = elementSettings.dateFormat !== 'none';
            const hasTime = elementSettings.timeFormat !== 'none';

            if (hasDate && !hasTime) {
                elementSettings.type = 'date';
            } else if (!hasDate && hasTime) {
                elementSettings.type = 'time';
            }

            const formattedDate = formatDateTime(timestamp, elementSettings);

            if (formattedDate) {
                const $link = $el.find('a');
                if ($link.length) {
                    $link.text(formattedDate);
                } else {
                    $el.text(formattedDate);
                }

                try {
                    const originalDate = new Date(parseInt(timestamp, 10));
                    if (!isNaN(originalDate.getTime())) {
                        $el.attr('title', originalDate.toLocaleString(elementSettings.locale));
                    }
                } catch (e) {
                    console.warn('Failed to set title attribute:', e);
                }
            }
        });
    }

    /**
     * Fix date elements in WordPress block editor
     */
    function fixDateTimeInBlocks() {
        const settings = getSettings();

        $('time.intl-datetime-element').each(function () {
            const $time = $(this);
            const timestamp = $time.data('intl-datetime');

            if (!timestamp) {
                return;
            }

            const dateFormat = $time.data('date-format');
            const timeFormat = $time.data('time-format');
            const customFormat = $time.data('custom-format');

            const elementSettings = {
                calendar: $time.data('calendar') || settings.calendar_type,
                locale: settings.locale
            };

            // Determine if this is a date, time, or datetime display
            if (dateFormat === 'custom' || timeFormat === 'custom') {
                // Use custom format if specified
                elementSettings.dateFormat = dateFormat;
                elementSettings.timeFormat = timeFormat;
                elementSettings.customFormat = customFormat;
                elementSettings.type = 'custom';
            } else {
                // Otherwise determine type based on context or data attributes
                let formatType = 'datetime';

                // Check data attributes first
                if (dateFormat === 'wp' && timeFormat === 'none') {
                    formatType = 'date';
                } else if (dateFormat === 'none' && timeFormat === 'wp') {
                    formatType = 'time';
                } else {
                    // Fall back to checking container classes
                    const $container = $time.closest('.wp-block-post-date, .wp-block-post-time');
                    if ($container.length) {
                        formatType = $container.hasClass('wp-block-post-date') ? 'date' : 'time';
                    }
                }

                elementSettings.type = formatType;
            }

            const formattedDate = formatDateTime(timestamp, elementSettings);
            if (!formattedDate) {
                return;
            }

            const $link = $time.find('a');
            if ($link.length) {
                $link.text(formattedDate);
            } else {
                $time.text(formattedDate);
            }
        });
    }

    /**
     * Debounce function to limit how often a function can be called
     * @param {Function} func - Function to debounce
     * @param {number} wait - Milliseconds to wait
     * @returns {Function} Debounced function
     */
    function debounce(func, wait) {
        let timeout;
        return function (...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }

    /**
     * Initialize the plugin's JavaScript functionality
     */
    function init() {
        if (!window.intlDateTimeCalendarSettings) {
            console.error('Intl DateTime Calendar - Settings not loaded properly');
        }

        if (!isIntlSupported()) {
            console.warn('Intl API is not fully supported in this browser. Date formatting will use browser defaults.');
            return;
        }

        $(document).ready(function () {
            processDateTimeElements();
            fixDateTimeInBlocks();

            $(document).ajaxComplete(function () {
                processDateTimeElements();
                fixDateTimeInBlocks();
            });

            const processDebounced = debounce(function () {
                processDateTimeElements();
                fixDateTimeInBlocks();
            }, 250);

            if (typeof MutationObserver === 'function') {
                const observer = new MutationObserver(processDebounced);
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
        });
    }

    init();

})(jQuery);