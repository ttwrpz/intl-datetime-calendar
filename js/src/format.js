/**
 * Client-side date rendering, mirroring src/Format/CalendarRenderer.php.
 *
 * The two must agree character for character, because a page rendered on the
 * server can be extended on the client when a Query block navigates without
 * a reload. tests/fixtures/formats.json is rendered by both and compared.
 */

export const LITERAL = 'literal';
export const FIELD = 'field';

const FIELD_CHARS = 'dDjlNSwzWFmMntLoXxYyaABgGhHisuveIOPpTZcrU';

/**
 * Split a PHP date format string into literals and fields.
 *
 * A port of FormatSpec::tokenize().
 *
 * @param {string} format PHP date format string.
 * @returns {Array<{type: string, value: string}>} Ordered token list.
 */
export function tokenize(format) {
    const tokens = [];

    const pushLiteral = (text) => {
        const last = tokens.length - 1;
        if (last >= 0 && tokens[last].type === LITERAL) {
            tokens[last].value += text;
            return;
        }
        tokens.push({type: LITERAL, value: text});
    };

    for (let i = 0; i < format.length; i++) {
        const char = format.charAt(i);

        // A backslash escapes the next character into literal text.
        if (char === '\\') {
            if (i + 1 < format.length) {
                pushLiteral(format.charAt(++i));
            }
            continue;
        }

        if (FIELD_CHARS.indexOf(char) !== -1) {
            tokens.push({type: FIELD, value: char});
            continue;
        }

        pushLiteral(char);
    }

    return tokens;
}

/**
 * PHP format characters Intl can resolve, and how to ask for them.
 *
 * `width` reapplies PHP's width, which Intl does not honour: asking for a
 * numeric month gives whatever width the locale prefers, so Russian pads it
 * to "05" where PHP's `n` means "5". 2 pads, 1 strips zeros, 0 leaves it.
 *
 * `unitMarker` flags name forms. Japanese names the month "5月", which Intl
 * reports as a month of "5" and a following literal "月".
 */
const INTL_FIELDS = {
    d: {options: {day: '2-digit'}, part: 'day', width: 2},
    j: {options: {day: 'numeric'}, part: 'day', width: 1},
    D: {options: {weekday: 'short'}, part: 'weekday', width: 0},
    l: {options: {weekday: 'long'}, part: 'weekday', width: 0},
    F: {options: {month: 'long'}, part: 'month', width: 0, unitMarker: true},
    M: {options: {month: 'short'}, part: 'month', width: 0, unitMarker: true},
    m: {options: {month: '2-digit'}, part: 'month', width: 2},
    n: {options: {month: 'numeric'}, part: 'month', width: 1},
    Y: {options: {year: 'numeric'}, part: 'year', width: 0},
    y: {options: {year: '2-digit'}, part: 'year', width: 2},
    g: {options: {hour: 'numeric', hour12: true}, part: 'hour', width: 1},
    G: {options: {hour: 'numeric', hourCycle: 'h23'}, part: 'hour', width: 1},
    h: {options: {hour: '2-digit', hour12: true}, part: 'hour', width: 2},
    H: {options: {hour: '2-digit', hourCycle: 'h23'}, part: 'hour', width: 2},
    i: {options: {minute: '2-digit'}, part: 'minute', width: 2},
    s: {options: {second: '2-digit'}, part: 'second', width: 2},
    a: {options: {hour: 'numeric', hour12: true}, part: 'dayPeriod', width: 0, lower: true},
    A: {options: {hour: 'numeric', hour12: true}, part: 'dayPeriod', width: 0},
};

/** Computed fields whose digits follow the active numbering system. */
const LOCALIZED_COMPUTED = 'NwzWtB';

const formatterCache = new Map();
const zeroCache = new Map();
const digitCache = new Map();

/** Get a cached formatter for a locale and option set. */
function getFormatter(locale, options) {
    const key = locale + '|' + JSON.stringify(options);
    let formatter = formatterCache.get(key);

    if (!formatter) {
        formatter = new Intl.DateTimeFormat(locale, options);
        formatterCache.set(key, formatter);
    }

    return formatter;
}

/** The digit zero in a numbering system, used for padding. */
function localizedZero(locale, numberingSystem) {
    const key = locale + '|' + numberingSystem;
    let zero = zeroCache.get(key);

    if (zero === undefined) {
        try {
            const options = numberingSystem ? {numberingSystem} : {};
            zero = new Intl.NumberFormat(locale, options).format(0);
        } catch (e) {
            zero = '0';
        }
        zeroCache.set(key, zero);
    }

    return zero;
}

const capabilityCache = new Map();

/**
 * Whether this browser can render the requested calendar.
 *
 * A name the engine does not know raises a RangeError. More quietly, an
 * older browser predating the calendar option ignores it and formats a
 * Gregorian date, so a Thai reader would see ๐๔/๐๕/๒๐๒๕, a Gregorian year
 * in Thai digits, with nothing about it looking wrong. Comparing what the
 * formatter resolved against what was asked catches both.
 *
 * @param {Object} settings Locale, calendar and numbering system.
 * @returns {boolean} True when the calendar is genuinely supported.
 */
export function canRender(settings) {
    const locale = settings.locale || 'en';
    const calendar = settings.calendar || '';

    if (!calendar) {
        return true;
    }

    const key = locale + '|' + calendar;

    if (!capabilityCache.has(key)) {
        let usable = false;

        try {
            const formatter = new Intl.DateTimeFormat(locale, {
                calendar,
                year: 'numeric',
                month: 'numeric',
                day: 'numeric',
            });

            // Chinese and Dangi report a year name and a related Gregorian
            // year, but no year of their own, so the year would come out empty.
            const hasYear = formatter.formatToParts(new Date()).some((part) => part.type === 'year');

            usable = formatter.resolvedOptions().calendar === calendar && hasYear;
        } catch (e) {
            usable = false;
        }

        capabilityCache.set(key, usable);
    }

    return capabilityCache.get(key);
}

/**
 * Render a date through a PHP date format string.
 *
 * Every field is requested in one formatToParts call so ICU applies the
 * right contextual word forms. Russian gives "май" for a month alone but
 * "мая" inside a date. It also keeps era markers out of the year: a
 * Buddhist year alone is "พ.ศ. 2568", but its year part is a clean "2568".
 *
 * @param {Date}   date     Moment to render.
 * @param {string} format   PHP date format string.
 * @param {Object} settings Locale, calendar, numberingSystem and timeZone.
 * @returns {string} Rendered date.
 */
export function render(date, format, settings) {
    const locale = settings.locale || 'en';
    const timeZone = settings.timeZone || undefined;
    const numberingSystem = settings.numberingSystem || '';
    const tokens = tokenize(format);

    // Shared by every lookup, so fields resolve in context, not isolation.
    const base = {timeZone};
    if (settings.calendar) {
        base.calendar = settings.calendar;
    }
    if (numberingSystem) {
        base.numberingSystem = numberingSystem;
    }

    const context = Object.assign({}, base);
    for (const token of tokens) {
        const field = token.type === FIELD ? INTL_FIELDS[token.value] : null;
        if (field) {
            // First form wins. Conflicts are resolved per token below.
            for (const key of Object.keys(field.options)) {
                if (context[key] === undefined) {
                    context[key] = field.options[key];
                }
            }
        }
    }

    const hasIntlField = Object.keys(context).length > Object.keys(base).length;
    const gregorian = gregorianParts(date, locale, timeZone);
    let output = '';

    for (const token of tokens) {
        if (token.type === LITERAL) {
            output += token.value;
            continue;
        }

        const field = INTL_FIELDS[token.value];

        if (!field) {
            output += computed(token.value, date, gregorian, locale, numberingSystem, base);
            continue;
        }

        // This token's exact form, with every other field left in place so
        // contextual wording survives. A month-only format is still resolved
        // as though a full date surrounded it, or Russian gives "май" not "мая".
        const options = field.unitMarker
            ? Object.assign({day: 'numeric', year: 'numeric'}, context, field.options)
            : Object.assign({}, context, field.options);

        const standalone = field.unitMarker
            ? getFormatter(locale, Object.assign({}, base, {numberingSystem: 'latn'}, field.options))
            : null;
        let value = fieldValue(getFormatter(locale, options), date, field, standalone, numberingSystem);

        if (field.width && !isDigits(value)) {
            // Hebrew replaces numeric months with names, so the number comes
            // from a neutral locale and is then written in the active digits.
            const neutral = partValue(
                getFormatter('en-US', Object.assign({}, base, {numberingSystem: 'latn'}, field.options)),
                date,
                field.part
            );

            if (isDigits(neutral)) {
                const sized = field.width === 2 ? pad(neutral, 2, '0') : String(Number(neutral));
                value = localizeDigits(sized, locale, numberingSystem);
            }
        } else if (field.width) {
            value = applyWidth(value, field.width, locale, numberingSystem);
        }

        if (field.lower) {
            value = value.toLocaleLowerCase(locale);
        }

        output += value;
    }

    // A format with no Intl fields at all still needs its computed values.
    return hasIntlField || output ? output : '';
}

/** Pull one named part out of a formatToParts result. */
function partValue(formatter, date, type) {
    const parts = formatter.formatToParts(date);

    for (const part of parts) {
        if (part.type === type) {
            return part.value;
        }
    }

    return '';
}

/**
 * Read one field, repairing a name form the locale resolved to a number.
 *
 * Czech gives "kvě" alone but "5" inside a full date, and Chinese gives "5"
 * where the name is "五月". The replacement is looked up alone and in Latin
 * digits, because a month name is fixed text the server never renumbers:
 * Japanese stays "5月" even when the rest of the date is full width.
 */
function fieldValue(formatter, date, field, standalone, numberingSystem) {
    const value = partValue(formatter, date, field.part);

    if (!field.unitMarker || !looksNumeric(value, numberingSystem)) {
        return value;
    }

    // The locale resolved the name away: Czech gives "5" inside a full
    // date where the name is "kvě". Looked up alone and in Latin digits,
    // because a month name is fixed text the server never renumbers.
    if (standalone) {
        const parts = standalone.formatToParts(date);

        for (let i = 0; i < parts.length; i++) {
            if (parts[i].type !== field.part) {
                continue;
            }

            const next = parts[i + 1];

            // Some locales split the name into a number and a marker.
            if (next && next.type === 'literal' && isUnitMarker(next.value)) {
                return parts[i].value + next.value;
            }

            return parts[i].value;
        }
    }

    return value;
}

/**
 * Whether text reads as a number in the active numbering system.
 *
 * Han decimal digits are ordinary letters to Unicode, so a character class
 * alone would not recognise them.
 */
function looksNumeric(value, numberingSystem) {
    if (isDigits(value)) {
        return true;
    }

    const table = digitTable(numberingSystem);

    return Boolean(table) && value.length > 0 && [...value].every((char) => table.includes(char));
}

/** Whether text is entirely decimal digits. */
function isDigits(value) {
    return value.length > 0 && /^\p{Nd}+$/u.test(value);
}

/** Whether a literal belongs to the field before it, such as 月. */
function isUnitMarker(value) {
    return /^\p{L}+$/u.test(value);
}

/**
 * Reapply PHP's field width to a value Intl produced.
 *
 * Digits map to ASCII first so padding works the same for every system.
 */
function applyWidth(value, width, locale, numberingSystem) {
    if (!isDigits(value)) {
        return value;
    }

    const table = digitTable(numberingSystem);
    const ascii = table
        ? String(Number(value.replace(/./gu, (digit) => {
            const index = table.indexOf(digit);

            return index === -1 ? digit : String(index);
        })))
        : String(Number(value));

    const sized = width === 2 ? pad(ascii, 2, '0') : ascii;

    return localizeDigits(sized, locale, numberingSystem);
}

/**
 * Read a date's Gregorian components in the target timezone.
 *
 * Week numbers and the day of the year are computed against the site
 * timezone rather than the visitor's, so the components come from Intl
 * rather than the Date object's local-time getters.
 */
function gregorianParts(date, locale, timeZone) {
    const formatter = getFormatter('en-US-u-ca-gregory-nu-latn', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        weekday: 'short',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hourCycle: 'h23',
        era: 'short',
    });

    const parts = {};
    for (const part of formatter.formatToParts(date)) {
        parts[part.type] = part.value;
    }

    const weekdays = {Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6};
    const year = parseInt(parts.year, 10);
    const month = parseInt(parts.month, 10);
    const day = parseInt(parts.day, 10);

    // Reading the wall clock back as UTC gives the site's offset.
    const wallClock = Date.UTC(
        year,
        month - 1,
        day,
        parseInt(parts.hour, 10) % 24,
        parseInt(parts.minute, 10),
        parseInt(parts.second, 10)
    );

    return {
        year,
        month,
        day,
        weekday: weekdays[parts.weekday] !== undefined ? weekdays[parts.weekday] : date.getUTCDay(),
        offset: wallClock - Math.floor(date.getTime() / 1000) * 1000,
    };
}

/** Resolve a PHP format character Intl does not expose. */
function computed(char, date, gregorian, locale, numberingSystem, base) {
    const {year, month, day, weekday} = gregorian;
    const leap = (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0;
    const monthLengths = [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    let value;

    switch (char) {
        case 'N':
            value = String(weekday === 0 ? 7 : weekday);
            break;
        case 'w':
            value = String(weekday);
            break;
        case 'S':
            // PHP's ordinal suffix is English regardless of locale.
            value = ordinalSuffix(day);
            break;
        case 'L':
            // Leap years are an ISO/Gregorian notion, as they are in PHP.
            return leap ? '1' : '0';
        case 't':
            // A Hebrew or Islamic month is 29 or 30 days, not 31.
            value = String(calendarMonthLength(date, locale, base, monthLengths[month - 1], gregorian.offset));
            break;
        case 'z':
            value = String(calendarDayOfYear(date, locale, base, dayOfYear(year, month, day, monthLengths) - 1, gregorian.offset));
            break;
        case 'W':
            value = pad(String(isoWeek(year, month, day)), 2, '0');
            break;
        case 'o':
            value = String(isoWeekYear(year, month, day));
            break;
        case 'U':
            return String(Math.floor(date.getTime() / 1000));
        case 'u':
            return '000000';
        case 'v':
            return pad(String(date.getUTCMilliseconds()), 3, '0');
        default:
            // Timezone names and offsets come from the server.
            return '';
    }

    if (LOCALIZED_COMPUTED.indexOf(char) !== -1) {
        value = localizeDigits(value, locale, numberingSystem);
    }

    return value;
}

const DAY_MS = 86400000;
const calendarCache = new Map();

/**
 * Read a date's month and year as the selected calendar labels them.
 *
 * Compared as text, because Intl reports Hebrew months by name.
 */
function calendarLabels(date, locale, base) {
    const formatter = getFormatter(locale, Object.assign({}, base, {
        year: 'numeric',
        month: 'short',
    }));

    const parts = {};
    for (const part of formatter.formatToParts(date)) {
        parts[part.type] = part.value;
    }

    return {month: parts.month || '', year: parts.year || ''};
}

/**
 * Days in the selected calendar's current month.
 *
 * Walks a day at a time until the month changes, bounded by the longest
 * month any supported calendar has.
 */
function calendarMonthLength(date, locale, base, fallback, offset) {
    const here = calendarLabels(date, locale, base);
    const key = 'len|' + locale + '|' + JSON.stringify(base) + '|' + here.year + '|' + here.month;

    if (calendarCache.has(key)) {
        return calendarCache.get(key);
    }

    // Snap to midday so a daylight-saving shift cannot skip or repeat a day.
    let cursor = noon(date, offset);
    while (calendarLabels(new Date(cursor - DAY_MS), locale, base).month === here.month) {
        cursor -= DAY_MS;
    }

    let length = 0;
    while (calendarLabels(new Date(cursor), locale, base).month === here.month && length < 40) {
        cursor += DAY_MS;
        length++;
    }

    const result = length > 0 ? length : fallback;
    calendarCache.set(key, result);

    return result;
}

/**
 * Day of the selected calendar's year, counting from zero as PHP does.
 *
 * The year start is bisected rather than stepped back through 400 days.
 */
function calendarDayOfYear(date, locale, base, fallback, offset) {
    const year = calendarLabels(date, locale, base).year;
    const target = noon(date, offset);
    const key = 'start|' + locale + '|' + JSON.stringify(base) + '|' + year;

    let start = calendarCache.get(key);

    if (start === undefined) {
        let before = target - 400 * DAY_MS;
        let inYear = target;

        if (calendarLabels(new Date(before), locale, base).year === year) {
            return fallback;
        }

        while (inYear - before > DAY_MS) {
            const middle = before + Math.floor((inYear - before) / 2 / DAY_MS) * DAY_MS;
            if (middle === before) {
                break;
            }
            if (calendarLabels(new Date(middle), locale, base).year === year) {
                inYear = middle;
            } else {
                before = middle;
            }
        }

        start = inYear;
        calendarCache.set(key, start);
    }

    return Math.round((target - start) / DAY_MS);
}

/** Snap a moment to midday in the site timezone, clear of DST shifts. */
function noon(date, offset) {
    const local = date.getTime() + offset;

    return Math.floor(local / DAY_MS) * DAY_MS + DAY_MS / 2 - offset;
}

/** Replace ASCII digits with the active numbering system's digits. */
function localizeDigits(value, locale, numberingSystem) {
    if (!numberingSystem || numberingSystem === 'latn') {
        return value;
    }

    const table = digitTable(numberingSystem);

    return table ? value.replace(/[0-9]/g, (digit) => table[Number(digit)]) : value;
}

/**
 * The ten digits of a numbering system, in order.
 *
 * Looked up rather than derived by adding to zero. Most systems use ten
 * consecutive code points, but Han decimal does not: its zero is U+3007 and
 * its one is U+4E00, so arithmetic there yields unrelated characters.
 */
function digitTable(numberingSystem) {
    if (digitCache.has(numberingSystem)) {
        return digitCache.get(numberingSystem);
    }

    let table = null;

    try {
        const formatter = new Intl.NumberFormat('en', {
            numberingSystem,
            useGrouping: false,
        });

        const digits = [];
        for (let digit = 0; digit <= 9; digit++) {
            digits.push(formatter.format(digit));
        }

        // An unknown system quietly returns Latin digits, so nothing to map.
        if (new Set(digits).size === 10) {
            table = digits;
        }
    } catch (e) {
        table = null;
    }

    digitCache.set(numberingSystem, table);

    return table;
}

/** Left-pad text to a width. */
function pad(value, width, char) {
    while (value.length < width) {
        value = char + value;
    }

    return value;
}

/** English ordinal suffix, matching PHP's S. */
function ordinalSuffix(day) {
    if (day % 100 >= 11 && day % 100 <= 13) {
        return 'th';
    }

    return {1: 'st', 2: 'nd', 3: 'rd'}[day % 10] || 'th';
}

/** Day of the year, counting from one. */
function dayOfYear(year, month, day, monthLengths) {
    let total = day;

    for (let i = 0; i < month - 1; i++) {
        total += monthLengths[i];
    }

    return total;
}

/** ISO-8601 week number. */
function isoWeek(year, month, day) {
    const target = Date.UTC(year, month - 1, day);
    const dayNumber = (new Date(target).getUTCDay() + 6) % 7;
    const thursday = target + (3 - dayNumber) * 86400000;
    const firstThursday = Date.UTC(new Date(thursday).getUTCFullYear(), 0, 4);
    const firstDayNumber = (new Date(firstThursday).getUTCDay() + 6) % 7;
    const firstWeekMonday = firstThursday - firstDayNumber * 86400000;

    return Math.round((thursday - firstWeekMonday) / (7 * 86400000)) + 1;
}

/** ISO-8601 week-numbering year, which can differ from the calendar year. */
function isoWeekYear(year, month, day) {
    const target = Date.UTC(year, month - 1, day);
    const dayNumber = (new Date(target).getUTCDay() + 6) % 7;
    const thursday = target + (3 - dayNumber) * 86400000;

    return new Date(thursday).getUTCFullYear();
}
