/**
 * Cross-language parity test.
 *
 * The PHP and browser renderers must agree on everything they control: which
 * field goes where, how wide it is, how it is padded, which digits it uses and
 * what separates it from the next one.
 *
 * They cannot be held to identical wording. Each side asks a different ICU
 * build, and CLDR revises its text between versions, so Thai abbreviates
 * Sunday as "อา." in one and "อาทิตย์" in another. Neither engine chooses
 * that, and it is harmless in production, where the two never render the same
 * element: anything the server wrote carries data-intl-rendered and the
 * browser leaves it alone.
 *
 * So a format built only from numbers and literals is compared byte for byte,
 * and a format containing a name is compared with the names masked. A wrong
 * field, a wrong order, a lost separator, a bad width or a digit where a name
 * belongs still fails. Only the wording itself may vary.
 */

import {test} from 'node:test';
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {readFileSync} from 'node:fs';
import {fileURLToPath} from 'node:url';
import {dirname, join} from 'node:path';

import {render, canRender, tokenize, FIELD} from '../../js/src/format.js';

const here = dirname(fileURLToPath(import.meta.url));
const fixtures = JSON.parse(readFileSync(join(here, '../fixtures/formats.json'), 'utf8'));

/** Fields whose value is a word from CLDR rather than a number. */
const NAME_FIELDS = ['F', 'M', 'D', 'l', 'a', 'A'];

/**
 * A run of letters, including the punctuation scripts use to mark an
 * abbreviation: the period in Thai "อา.", the gershayim in Hebrew "אחה״צ".
 */
const NAMES = /[\p{L}\p{M}][\p{L}\p{M}.׳״'’]*/gu;

/**
 * Whether a format asks for any CLDR word.
 *
 * @param {string} format PHP date format string.
 * @returns {boolean} True when a name field is present.
 */
function hasName(format) {
    return tokenize(format)
        .filter((token) => token.type === FIELD)
        .some((token) => NAME_FIELDS.includes(token.value));
}

/**
 * Replace every word with a placeholder, leaving the structure behind.
 *
 * @param {string} value Rendered date.
 * @returns {string} The same date with its words masked.
 */
function maskNames(value) {
    return value.replace(NAMES, '#');
}

/** Render the fixture matrix with the PHP engine. */
function renderWithPhp() {
    const script = join(here, '../php/render-fixtures.php');

    for (const args of [['-d', 'extension=intl', script], [script]]) {
        try {
            const output = execFileSync('php', args, {encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore']});
            const parsed = JSON.parse(output);
            if (parsed && Object.keys(parsed).length > 0) {
                return parsed;
            }
        } catch (e) {
            // Try the next invocation, then give up and skip.
        }
    }

    return null;
}

const phpResults = renderWithPhp();

test('PHP and JavaScript renderers agree on every fixture', (t) => {
    if (!phpResults) {
        t.skip('PHP with ext-intl is not available on this machine');
        return;
    }

    const mismatches = [];
    const refused = [];
    let exact = 0;
    let structural = 0;
    let worded = 0;

    for (const config of fixtures.locales) {
        // A calendar the browser declines is never compared: it leaves the
        // server's text alone, which is the correct outcome.
        if (!canRender(config)) {
            refused.push(`${config.locale}/${config.calendar}`);
            continue;
        }

        for (const moment of fixtures.moments) {
            const date = new Date(moment);

            for (const format of fixtures.formats) {
                const key = [config.locale, config.calendar, config.numberingSystem, moment, format].join('|');
                const expected = phpResults[key];

                if (expected === undefined) {
                    continue;
                }

                const actual = render(date, format, {
                    locale: config.locale,
                    calendar: config.calendar,
                    numberingSystem: config.numberingSystem,
                    timeZone: config.timeZone,
                });

                if (!hasName(format)) {
                    exact++;

                    if (actual !== expected) {
                        mismatches.push(`${key}\n    php=${JSON.stringify(expected)}\n    js =${JSON.stringify(actual)}`);
                    }

                    continue;
                }

                structural++;

                if (actual === expected) {
                    worded++;
                    continue;
                }

                if (maskNames(actual) !== maskNames(expected)) {
                    mismatches.push(
                        `${key}\n    php=${JSON.stringify(expected)} -> ${JSON.stringify(maskNames(expected))}` +
                            `\n    js =${JSON.stringify(actual)} -> ${JSON.stringify(maskNames(actual))}`
                    );
                }
            }
        }
    }

    console.log(`      ICU: php ${phpResults.__icu__ || 'unknown'}, node ${process.versions.icu}`);
    console.log(`      ${exact} numeric formats compared exactly`);
    console.log(`      ${structural} named formats compared structurally, ${worded} also matched word for word`);

    if (refused.length > 0) {
        console.log(`      browser declines (server text kept): ${refused.join(', ')}`);
    }

    assert.ok(exact + structural > 0, 'expected at least one comparison');

    assert.equal(
        mismatches.length,
        0,
        `${mismatches.length} of ${exact + structural} cases disagree:\n  ${mismatches.slice(0, 25).join('\n  ')}`
    );
});
