/*! Intl DateTime Calendar | GPL-2.0-or-later | built from js/src, do not edit by hand */
(() => {
  // js/src/format.js
  var LITERAL = "literal";
  var FIELD = "field";
  var FIELD_CHARS = "dDjlNSwzWFmMntLoXxYyaABgGhHisuveIOPpTZcrU";
  function tokenize(format) {
    const tokens = [];
    const pushLiteral = (text) => {
      const last = tokens.length - 1;
      if (last >= 0 && tokens[last].type === LITERAL) {
        tokens[last].value += text;
        return;
      }
      tokens.push({ type: LITERAL, value: text });
    };
    for (let i = 0; i < format.length; i++) {
      const char = format.charAt(i);
      if (char === "\\") {
        if (i + 1 < format.length) {
          pushLiteral(format.charAt(++i));
        }
        continue;
      }
      if (FIELD_CHARS.indexOf(char) !== -1) {
        tokens.push({ type: FIELD, value: char });
        continue;
      }
      pushLiteral(char);
    }
    return tokens;
  }
  var INTL_FIELDS = {
    d: { options: { day: "2-digit" }, part: "day", width: 2 },
    j: { options: { day: "numeric" }, part: "day", width: 1 },
    D: { options: { weekday: "short" }, part: "weekday", width: 0 },
    l: { options: { weekday: "long" }, part: "weekday", width: 0 },
    F: { options: { month: "long" }, part: "month", width: 0, unitMarker: true },
    M: { options: { month: "short" }, part: "month", width: 0, unitMarker: true },
    m: { options: { month: "2-digit" }, part: "month", width: 2 },
    n: { options: { month: "numeric" }, part: "month", width: 1 },
    Y: { options: { year: "numeric" }, part: "year", width: 0 },
    y: { options: { year: "2-digit" }, part: "year", width: 2 },
    g: { options: { hour: "numeric", hour12: true }, part: "hour", width: 1 },
    G: { options: { hour: "numeric", hourCycle: "h23" }, part: "hour", width: 1 },
    h: { options: { hour: "2-digit", hour12: true }, part: "hour", width: 2 },
    H: { options: { hour: "2-digit", hourCycle: "h23" }, part: "hour", width: 2 },
    i: { options: { minute: "2-digit" }, part: "minute", width: 2 },
    s: { options: { second: "2-digit" }, part: "second", width: 2 },
    a: { options: { hour: "numeric", hour12: true }, part: "dayPeriod", width: 0, lower: true },
    A: { options: { hour: "numeric", hour12: true }, part: "dayPeriod", width: 0 }
  };
  var LOCALIZED_COMPUTED = "NwzWtB";
  var formatterCache = /* @__PURE__ */ new Map();
  var digitCache = /* @__PURE__ */ new Map();
  function getFormatter(locale, options) {
    const key = locale + "|" + JSON.stringify(options);
    let formatter = formatterCache.get(key);
    if (!formatter) {
      formatter = new Intl.DateTimeFormat(locale, options);
      formatterCache.set(key, formatter);
    }
    return formatter;
  }
  function render(date, format, settings) {
    const locale = settings.locale || "en";
    const timeZone = settings.timeZone || void 0;
    const numberingSystem = settings.numberingSystem || "";
    const tokens = tokenize(format);
    const base = { timeZone };
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
        for (const key of Object.keys(field.options)) {
          if (context[key] === void 0) {
            context[key] = field.options[key];
          }
        }
      }
    }
    const hasIntlField = Object.keys(context).length > Object.keys(base).length;
    const gregorian = gregorianParts(date, locale, timeZone);
    let output = "";
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
      const options = field.unitMarker ? Object.assign({ day: "numeric", year: "numeric" }, context, field.options) : Object.assign({}, context, field.options);
      const standalone = field.unitMarker ? getFormatter(locale, Object.assign({}, base, { numberingSystem: "latn" }, field.options)) : null;
      let value = fieldValue(getFormatter(locale, options), date, field, standalone, numberingSystem);
      if (field.width && !isDigits(value)) {
        const neutral = partValue(
          getFormatter("en-US", Object.assign({}, base, { numberingSystem: "latn" }, field.options)),
          date,
          field.part
        );
        if (isDigits(neutral)) {
          const sized = field.width === 2 ? pad(neutral, 2, "0") : String(Number(neutral));
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
    return hasIntlField || output ? output : "";
  }
  function partValue(formatter, date, type) {
    const parts = formatter.formatToParts(date);
    for (const part of parts) {
      if (part.type === type) {
        return part.value;
      }
    }
    return "";
  }
  function fieldValue(formatter, date, field, standalone, numberingSystem) {
    const value = partValue(formatter, date, field.part);
    if (!field.unitMarker || !looksNumeric(value, numberingSystem)) {
      return value;
    }
    if (standalone) {
      const parts = standalone.formatToParts(date);
      for (let i = 0; i < parts.length; i++) {
        if (parts[i].type !== field.part) {
          continue;
        }
        const next = parts[i + 1];
        if (next && next.type === "literal" && isUnitMarker(next.value)) {
          return parts[i].value + next.value;
        }
        return parts[i].value;
      }
    }
    return value;
  }
  function looksNumeric(value, numberingSystem) {
    if (isDigits(value)) {
      return true;
    }
    const table = digitTable(numberingSystem);
    return Boolean(table) && value.length > 0 && [...value].every((char) => table.includes(char));
  }
  function isDigits(value) {
    return value.length > 0 && /^\p{Nd}+$/u.test(value);
  }
  function isUnitMarker(value) {
    return /^\p{L}+$/u.test(value);
  }
  function applyWidth(value, width, locale, numberingSystem) {
    if (!isDigits(value)) {
      return value;
    }
    const table = digitTable(numberingSystem);
    const ascii = table ? String(Number(value.replace(/./gu, (digit) => {
      const index = table.indexOf(digit);
      return index === -1 ? digit : String(index);
    }))) : String(Number(value));
    const sized = width === 2 ? pad(ascii, 2, "0") : ascii;
    return localizeDigits(sized, locale, numberingSystem);
  }
  function gregorianParts(date, locale, timeZone) {
    const formatter = getFormatter("en-US-u-ca-gregory-nu-latn", {
      timeZone,
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      weekday: "short",
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
      hourCycle: "h23",
      era: "short"
    });
    const parts = {};
    for (const part of formatter.formatToParts(date)) {
      parts[part.type] = part.value;
    }
    const weekdays = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };
    const year = parseInt(parts.year, 10);
    const month = parseInt(parts.month, 10);
    const day = parseInt(parts.day, 10);
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
      weekday: weekdays[parts.weekday] !== void 0 ? weekdays[parts.weekday] : date.getUTCDay(),
      offset: wallClock - Math.floor(date.getTime() / 1e3) * 1e3
    };
  }
  function computed(char, date, gregorian, locale, numberingSystem, base) {
    const { year, month, day, weekday } = gregorian;
    const leap = year % 4 === 0 && year % 100 !== 0 || year % 400 === 0;
    const monthLengths = [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    let value;
    switch (char) {
      case "N":
        value = String(weekday === 0 ? 7 : weekday);
        break;
      case "w":
        value = String(weekday);
        break;
      case "S":
        value = ordinalSuffix(day);
        break;
      case "L":
        return leap ? "1" : "0";
      case "t":
        value = String(calendarMonthLength(date, locale, base, monthLengths[month - 1], gregorian.offset));
        break;
      case "z":
        value = String(calendarDayOfYear(date, locale, base, dayOfYear(year, month, day, monthLengths) - 1, gregorian.offset));
        break;
      case "W":
        value = pad(String(isoWeek(year, month, day)), 2, "0");
        break;
      case "o":
        value = String(isoWeekYear(year, month, day));
        break;
      case "U":
        return String(Math.floor(date.getTime() / 1e3));
      case "u":
        return "000000";
      case "v":
        return pad(String(date.getUTCMilliseconds()), 3, "0");
      default:
        return "";
    }
    if (LOCALIZED_COMPUTED.indexOf(char) !== -1) {
      value = localizeDigits(value, locale, numberingSystem);
    }
    return value;
  }
  var DAY_MS = 864e5;
  var calendarCache = /* @__PURE__ */ new Map();
  function calendarLabels(date, locale, base) {
    const formatter = getFormatter(locale, Object.assign({}, base, {
      year: "numeric",
      month: "short"
    }));
    const parts = {};
    for (const part of formatter.formatToParts(date)) {
      parts[part.type] = part.value;
    }
    return { month: parts.month || "", year: parts.year || "" };
  }
  function calendarMonthLength(date, locale, base, fallback, offset) {
    const here = calendarLabels(date, locale, base);
    const key = "len|" + locale + "|" + JSON.stringify(base) + "|" + here.year + "|" + here.month;
    if (calendarCache.has(key)) {
      return calendarCache.get(key);
    }
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
  function calendarDayOfYear(date, locale, base, fallback, offset) {
    const year = calendarLabels(date, locale, base).year;
    const target = noon(date, offset);
    const key = "start|" + locale + "|" + JSON.stringify(base) + "|" + year;
    let start2 = calendarCache.get(key);
    if (start2 === void 0) {
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
      start2 = inYear;
      calendarCache.set(key, start2);
    }
    return Math.round((target - start2) / DAY_MS);
  }
  function noon(date, offset) {
    const local = date.getTime() + offset;
    return Math.floor(local / DAY_MS) * DAY_MS + DAY_MS / 2 - offset;
  }
  function localizeDigits(value, locale, numberingSystem) {
    if (!numberingSystem || numberingSystem === "latn") {
      return value;
    }
    const table = digitTable(numberingSystem);
    return table ? value.replace(/[0-9]/g, (digit) => table[Number(digit)]) : value;
  }
  function digitTable(numberingSystem) {
    if (digitCache.has(numberingSystem)) {
      return digitCache.get(numberingSystem);
    }
    let table = null;
    try {
      const formatter = new Intl.NumberFormat("en", {
        numberingSystem,
        useGrouping: false
      });
      const digits = [];
      for (let digit = 0; digit <= 9; digit++) {
        digits.push(formatter.format(digit));
      }
      if (new Set(digits).size === 10) {
        table = digits;
      }
    } catch (e) {
      table = null;
    }
    digitCache.set(numberingSystem, table);
    return table;
  }
  function pad(value, width, char) {
    while (value.length < width) {
      value = char + value;
    }
    return value;
  }
  function ordinalSuffix(day) {
    if (day % 100 >= 11 && day % 100 <= 13) {
      return "th";
    }
    return { 1: "st", 2: "nd", 3: "rd" }[day % 10] || "th";
  }
  function dayOfYear(year, month, day, monthLengths) {
    let total = day;
    for (let i = 0; i < month - 1; i++) {
      total += monthLengths[i];
    }
    return total;
  }
  function isoWeek(year, month, day) {
    const target = Date.UTC(year, month - 1, day);
    const dayNumber = (new Date(target).getUTCDay() + 6) % 7;
    const thursday = target + (3 - dayNumber) * 864e5;
    const firstThursday = Date.UTC(new Date(thursday).getUTCFullYear(), 0, 4);
    const firstDayNumber = (new Date(firstThursday).getUTCDay() + 6) % 7;
    const firstWeekMonday = firstThursday - firstDayNumber * 864e5;
    return Math.round((thursday - firstWeekMonday) / (7 * 864e5)) + 1;
  }
  function isoWeekYear(year, month, day) {
    const target = Date.UTC(year, month - 1, day);
    const dayNumber = (new Date(target).getUTCDay() + 6) % 7;
    const thursday = target + (3 - dayNumber) * 864e5;
    return new Date(thursday).getUTCFullYear();
  }

  // js/src/admin.js
  function start() {
    const settings = window.intlDateTimeCalendarAdmin || {};
    const output = document.getElementById("intl-preview-date");
    const calendar = document.getElementById("intl-calendar-type");
    const numbering = document.getElementById("intl-numbering-system");
    if (!output || !calendar) {
      return;
    }
    const update = () => {
      const format = settings.dateFormat || "F j, Y";
      try {
        output.textContent = render(/* @__PURE__ */ new Date(), format, {
          locale: settings.locale || "en",
          calendar: calendar.value,
          numberingSystem: numbering ? numbering.value : "",
          timeZone: settings.timeZone || void 0
        });
      } catch (e) {
        output.textContent = (/* @__PURE__ */ new Date()).toLocaleDateString(settings.locale || "en");
      }
    };
    calendar.addEventListener("change", update);
    if (numbering) {
      numbering.addEventListener("change", update);
    }
    update();
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start);
  } else {
    start();
  }
})();
