/**
 * Cross-language parity test.
 *
 * The PHP and JavaScript renderers must produce identical text for identical
 * input. A page can be rendered on the server and then extended on the client
 * when a Query block navigates without a reload, so any disagreement between
 * the two shows up as a date that changes when the visitor clicks. This test
 * renders the shared fixture matrix through both engines and compares.
 *
 * The PHP side is invoked through the CLI, so this test is skipped when PHP
 * or its intl extension is unavailable rather than reporting a false failure.
 */

import {test, skip} from 'node:test';
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {readFileSync} from 'node:fs';
import {fileURLToPath} from 'node:url';
import {dirname, join} from 'node:path';

import {render, canRender, tokenize, FIELD} from '../../js/src/format.js';

/**
 * Differences the browser platform imposes, which the server does not share.
 *
 * The server renders through ICU pattern letters and can ask for one exact
 * field. The browser renders through Intl option matching, where the locale
 * decides. Hebrew is the one case left where the two cannot agree: ICU's
 * day-period letter gives "אחה״צ", every Intl option gives "PM".
 *
 * Asserted exhaustive in both directions, so an entry that stops diverging
 * fails the test and gets removed rather than outliving the platform bug.
 */
const KNOWN_DIVERGENCES = [
    {
        locale: 'he-IL',
        fields: ['a', 'A'],
        reason: 'Intl returns AM/PM for Hebrew day periods where ICU returns אחה״צ',
    },
];

/** Whether a case is a known platform divergence rather than a defect. */
function isKnownDivergence(config, format) {
    const fields = tokenize(format)
        .filter((token) => token.type === FIELD)
        .map((token) => token.value);

    return KNOWN_DIVERGENCES.some(
        (entry) => entry.locale === config.locale && entry.fields.some((field) => fields.includes(field))
    );
}

const here = dirname(fileURLToPath(import.meta.url));
const fixtures = JSON.parse(readFileSync(join(here, '../fixtures/formats.json'), 'utf8'));

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
    const staleAllowances = [];
    const refused = [];
    let compared = 0;

    for (const config of fixtures.locales) {
        // A calendar the browser declines to render is never compared: it
        // leaves the server's text in place instead, which is the correct
        // outcome and has nothing to compare against.
        if (!canRender(config)) {
            refused.push(`${config.locale}/${config.calendar}`);
            continue;
        }

        for (const moment of fixtures.moments) {
            const date = new Date(moment);

            for (const format of fixtures.formats) {
                const key = [
                    config.locale,
                    config.calendar,
                    config.numberingSystem,
                    moment,
                    format,
                ].join('|');

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

                compared++;

                const known = isKnownDivergence(config, format);

                if (actual !== expected && !known) {
                    mismatches.push(`${key}\n    php=${JSON.stringify(expected)}\n    js =${JSON.stringify(actual)}`);
                }

                // An allowance that no longer diverges means the platform
                // caught up, so the allowance should go rather than linger.
                if (actual === expected && known) {
                    staleAllowances.push(key);
                }
            }
        }
    }

    assert.ok(compared > 0, 'expected at least one comparison');

    if (refused.length > 0) {
        // Reported rather than hidden, so a calendar that silently stops
        // being renderable in the browser is visible in the test output.
        console.log(`      browser declines (server text kept): ${refused.join(', ')}`);
    }

    assert.equal(
        mismatches.length,
        0,
        `${mismatches.length} of ${compared} cases disagree:\n  ${mismatches.slice(0, 25).join('\n  ')}`
    );

    assert.equal(
        staleAllowances.length,
        0,
        `KNOWN_DIVERGENCES no longer applies to ${staleAllowances.length} cases; remove the entry:\n  ` +
            staleAllowances.slice(0, 10).join('\n  ')
    );
});
