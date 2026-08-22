/**
 * Guards against browsers that cannot do what the page asks of them.
 *
 * The browser renderer only runs on hosts without ext-intl, which are also
 * the hosts least likely to be running modern everything else. These tests
 * cover what happens when the engine cannot deliver the requested calendar,
 * either by refusing outright or, more awkwardly, by quietly ignoring the
 * request and returning a Gregorian date.
 */

import {test} from 'node:test';
import assert from 'node:assert/strict';

import {render, canRender, tokenize, FIELD, LITERAL} from '../../js/src/format.js';

test('a calendar the engine rejects is refused rather than thrown', () => {
    for (const calendar of ['notacalendar', 'bad_cal', '../etc/passwd', 'a']) {
        assert.equal(
            canRender({locale: 'en-US', calendar}),
            false,
            `${calendar} should be refused`
        );
    }
});

test('a supported calendar is accepted', () => {
    for (const calendar of ['gregory', 'buddhist', 'islamic-umalqura', 'japanese', 'hebrew']) {
        assert.equal(canRender({locale: 'en-US', calendar}), true, `${calendar} should be usable`);
    }
});

test('no calendar requested means nothing to refuse', () => {
    assert.equal(canRender({locale: 'en-US', calendar: ''}), true);
    assert.equal(canRender({locale: 'en-US'}), true);
});

test('an engine that ignores the calendar option is refused', async () => {
    // A browser predating the calendar option accepts it and formats a
    // Gregorian date anyway. Converting there would produce a Gregorian year
    // dressed in the requested digits, which reads as correct but is not.
    const original = Intl.DateTimeFormat.prototype.resolvedOptions;

    Intl.DateTimeFormat.prototype.resolvedOptions = function () {
        const resolved = original.call(this);
        resolved.calendar = 'gregory';

        return resolved;
    };

    try {
        const isolated = await import('../../js/src/format.js?ignores-calendar');

        assert.equal(
            isolated.canRender({locale: 'th-TH', calendar: 'buddhist'}),
            false,
            'a request the engine silently downgrades must be refused'
        );
    } finally {
        Intl.DateTimeFormat.prototype.resolvedOptions = original;
    }
});

test('adjacent identical fields stay separate fields', () => {
    const date = new Date('2025-05-04T13:05:07Z');
    const settings = {locale: 'en-US', calendar: 'gregory', timeZone: 'UTC'};

    // ICU treats a run of one pattern letter as a single wider field, so the
    // server had to be taught to keep these apart. The browser resolves each
    // token on its own and must agree.
    assert.equal(render(date, 'mm', settings), '0505');
    assert.equal(render(date, 'YY', settings), '20252025');
    assert.equal(render(date, 'FF', settings), 'MayMay');
    assert.equal(render(date, 'jj', settings), '44');
});

test('escapes and literals survive tokenizing', () => {
    const tokens = tokenize('j F Y \\a\\t g:i');
    const literals = tokens.filter((t) => t.type === LITERAL).map((t) => t.value);
    const fields = tokens.filter((t) => t.type === FIELD).map((t) => t.value);

    assert.ok(literals.join('').includes('at'), 'escaped text is kept as a literal');
    assert.deepEqual(fields, ['j', 'F', 'Y', 'g', 'i'], 'escaped characters are not fields');
});

test('numbering systems with non-sequential digits are written correctly', () => {
    const date = new Date('2025-05-04T13:05:07Z');

    // Han decimal is the one system whose ten digits are not ten consecutive
    // code points: its zero is U+3007 while its one is U+4E00. Deriving the
    // digits by adding to the value of zero produced 〇》/〇「/二〇二五.
    assert.equal(
        render(date, 'd/m/Y', {locale: 'en-US', calendar: 'gregory', numberingSystem: 'hanidec', timeZone: 'UTC'}),
        '〇四/〇五/二〇二五'
    );

    assert.equal(
        render(date, 'd/m/Y', {locale: 'en-US', calendar: 'gregory', numberingSystem: 'thai', timeZone: 'UTC'}),
        '๐๔/๐๕/๒๐๒๕'
    );
});

test('every numbering system the engine offers renders its own digits', () => {
    const date = new Date('2025-05-04T13:05:07Z');
    const wrong = [];

    for (const numberingSystem of Intl.supportedValuesOf('numberingSystem')) {
        const settings = {locale: 'en-US', calendar: 'gregory', numberingSystem, timeZone: 'UTC'};
        const actual = render(date, 'd/m/Y', settings);

        const parts = new Intl.DateTimeFormat(`en-US-u-nu-${numberingSystem}`, {
            calendar: 'gregory',
            timeZone: 'UTC',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        }).formatToParts(date);

        const part = (type) => (parts.find((p) => p.type === type) || {value: '?'}).value;
        const expected = `${part('day')}/${part('month')}/${part('year')}`;

        if (actual !== expected) {
            wrong.push(`${numberingSystem}: got ${actual}, expected ${expected}`);
        }
    }

    assert.equal(wrong.length, 0, `these numbering systems render wrongly: ${wrong.join(' | ')}`);
});

test('a month name is never rewritten in the active numbering system', () => {
    const date = new Date('2025-05-04T13:05:07Z');

    // A month name is a fixed piece of text in the locale's data, so the
    // server leaves it alone whatever digits the rest of the date uses.
    assert.equal(
        render(date, 'F', {locale: 'ja-JP', calendar: 'gregory', numberingSystem: 'fullwide', timeZone: 'UTC'}),
        '5月'
    );

    assert.equal(
        render(date, 'F', {locale: 'zh-CN', calendar: 'gregory', numberingSystem: 'hanidec', timeZone: 'UTC'}),
        '五月'
    );
});

test('a cyclic calendar is declined rather than rendered without a year', () => {
    // Chinese and Dangi report a year name and a related Gregorian year but no
    // year of their own, so a date assembled from Intl parts loses the year.
    assert.equal(canRender({locale: 'zh-CN', calendar: 'chinese'}), false);
    assert.equal(canRender({locale: 'ko-KR', calendar: 'dangi'}), false);
    assert.equal(canRender({locale: 'zh-CN', calendar: 'gregory'}), true);
});
