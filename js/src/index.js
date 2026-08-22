/**
 * Front-end runtime.
 *
 * Sent only when the server could not convert dates itself, which means the
 * host is missing the intl PHP extension. On a host that has it, the plugin
 * adds no JavaScript to the page at all.
 */

import {render, canRender} from './format.js';

const PROCESSED = 'data-intl-done';
const RELATIVE = 'human-diff';

/** Read the configuration the server attached to the page. */
function siteSettings() {
    const settings = window.intlDateTimeCalendar || {};

    return {
        locale: settings.locale || 'en',
        calendar: settings.calendar || 'gregory',
        numberingSystem: settings.numberingSystem || '',
        timeZone: settings.timeZone || undefined,
        dateFormat: settings.dateFormat || 'F j, Y',
        timeFormat: settings.timeFormat || 'g:i a',
    };
}

/** Read one element's own settings, falling back to the site's. */
function elementSettings(element, site) {
    return {
        locale: element.getAttribute('data-intl-locale') || site.locale,
        calendar: element.getAttribute('data-intl-calendar') || site.calendar,
        numberingSystem: element.getAttribute('data-intl-numbering') || site.numberingSystem,
        timeZone: element.getAttribute('data-intl-timezone') || site.timeZone,
    };
}

/**
 * Describe a moment as elapsed time, in the reader's language.
 *
 * Used for the date block's relative option. Earlier versions skipped these
 * elements, leaving an English "3 days ago" among translated dates.
 */
function relativeTime(date, locale) {
    if (typeof Intl === 'undefined' || typeof Intl.RelativeTimeFormat !== 'function') {
        return '';
    }

    const units = [
        ['year', 31536000],
        ['month', 2592000],
        ['week', 604800],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
        ['second', 1],
    ];

    const elapsed = (date.getTime() - Date.now()) / 1000;
    const magnitude = Math.abs(elapsed);

    for (const [unit, seconds] of units) {
        if (magnitude >= seconds || unit === 'second') {
            const value = Math.round(elapsed / seconds);

            try {
                return new Intl.RelativeTimeFormat(locale, {numeric: 'auto'}).format(value, unit);
            } catch (e) {
                return '';
            }
        }
    }

    return '';
}

/** Convert one date element. */
function convert(element, site) {
    // The server already wrote this one correctly.
    if (element.getAttribute('data-intl-rendered') === 'server') {
        element.setAttribute(PROCESSED, '');
        return;
    }

    const timestamp = parseInt(element.getAttribute('data-intl-timestamp'), 10);
    const format = element.getAttribute('data-intl-format');

    if (!format || Number.isNaN(timestamp)) {
        return;
    }

    element.setAttribute(PROCESSED, '');

    const date = new Date(timestamp * 1000);
    if (Number.isNaN(date.getTime())) {
        return;
    }

    const settings = elementSettings(element, site);

    // Leave the server's text alone rather than replacing it with a date this
    // browser cannot actually write in the requested calendar.
    if (format !== RELATIVE && !canRender(settings)) {
        return;
    }

    let text;

    try {
        text = format === RELATIVE
            ? relativeTime(date, settings.locale)
            : render(date, format, settings);
    } catch (e) {
        // One unusable element must not stop every later date on the page.
        return;
    }

    if (!text) {
        return;
    }

    // Keep a permalink intact; replace only the text inside it.
    const link = element.querySelector('a');
    (link || element).textContent = text;
}

/** Convert every unprocessed date element inside a root. */
function convertWithin(root, site) {
    if (root instanceof Element && root.matches('.intl-datetime-element')) {
        convert(root, site);
    }

    const selector = '.intl-datetime-element:not([' + PROCESSED + '])';
    root.querySelectorAll(selector).forEach((element) => convert(element, site));
}

/**
 * Start converting, and keep up with content added later.
 *
 * A Query block can replace posts without reloading, so dates can appear
 * after the first pass. Only nodes the observer reports are scanned, so
 * replacing text in one date does not sweep every other.
 */
function start() {
    const site = siteSettings();

    convertWithin(document, site);

    if (typeof MutationObserver !== 'function') {
        return;
    }

    const observer = new MutationObserver((records) => {
        for (const record of records) {
            for (const node of record.addedNodes) {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    convertWithin(node, site);
                }
            }
        }
    });

    observer.observe(document.body, {childList: true, subtree: true});
}

if (typeof Intl !== 'undefined' && typeof Intl.DateTimeFormat === 'function') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
}
