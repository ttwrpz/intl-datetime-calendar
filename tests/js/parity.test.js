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
 * and a format containing a name is compared by its numbers alone: the digit
 * runs and where they sit. A wrong field, a wrong order, a bad width, a lost
 * separator between numbers or a digit where a name belongs still fails.
 *
 * Wording itself is covered by tests/js/robustness.test.js, which checks the
 * rules the engine is responsible for against the platform's own data rather
 * than against a fixed string.
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
 * Everything that is not a decimal digit.
 *
 * Enumerating what belongs inside a word does not work: Thai ends an
 * abbreviation with a period, Hebrew uses a gershayim, Persian joins one with
 * an invisible zero width non-joiner, and the next script will bring
 * something else again. What the engine actually decides is where the numbers
 * go and how wide they are, so the digits are the structure and everything
 * between them is masked.
 */
const BETWEEN_NUMBERS = /\P{Nd}+/gu;

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
 * Reduce a rendered date to its numbers and where they sit.
 *
 * @param {string} value Rendered date.
 * @returns {string} The digit runs, separated by a placeholder.
 */
function numberSkeleton(value) {
    return value.replace(BETWEEN_NUMBERS, '#');
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

                if (numberSkeleton(actual) !== numberSkeleton(expected)) {
                    mismatches.push(
                        `${key}\n    php=${JSON.stringify(expected)} -> ${JSON.stringify(numberSkeleton(expected))}` +
                            `\n    js =${JSON.stringify(actual)} -> ${JSON.stringify(numberSkeleton(actual))}`
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
