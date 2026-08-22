/**
 * Proves an edit touched only comments.
 *
 *   node tests/code-identity.mjs snapshot   before editing
 *   node tests/code-identity.mjs verify     after editing
 *
 * Strips comments and blank lines from every source file and compares the
 * result against the snapshot, so a comment cleanup that quietly changed a
 * line of code fails instead of shipping.
 */

import {readFileSync, writeFileSync, existsSync} from 'node:fs';
import {globSync} from 'node:fs';

const SNAPSHOT = 'tests/.code-identity.json';

const FILES = [
    ...globSync('src/**/*.php'),
    ...globSync('js/src/*.js'),
    'intl-datetime-calendar.php',
    'uninstall.php',
    'index.php',
].sort();

/** Strip comments and blank lines, leaving only code. */
function codeOnly(source) {
    let out = '';
    let i = 0;
    let quote = null;

    while (i < source.length) {
        const two = source.slice(i, i + 2);

        if (quote) {
            if (source[i] === '\\') {
                out += source.slice(i, i + 2);
                i += 2;
                continue;
            }
            if (source[i] === quote) {
                quote = null;
            }
            out += source[i++];
            continue;
        }

        if (source[i] === '"' || source[i] === "'" || source[i] === '`') {
            quote = source[i];
            out += source[i++];
            continue;
        }

        if (two === '/*') {
            const end = source.indexOf('*/', i + 2);
            i = end === -1 ? source.length : end + 2;
            continue;
        }

        if (two === '//' || source[i] === '#') {
            const end = source.indexOf('\n', i);
            i = end === -1 ? source.length : end;
            continue;
        }

        out += source[i++];
    }

    return out
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line.length > 0)
        .join('\n');
}

const current = {};
for (const file of FILES) {
    current[file] = codeOnly(readFileSync(file, 'utf8'));
}

const mode = process.argv[2];

if (mode === 'snapshot') {
    writeFileSync(SNAPSHOT, JSON.stringify(current, null, 2), 'utf8');
    console.log(`snapshot written for ${FILES.length} files`);
    process.exit(0);
}

if (!existsSync(SNAPSHOT)) {
    console.error('no snapshot; run "node tests/code-identity.mjs snapshot" first');
    process.exit(1);
}

const before = JSON.parse(readFileSync(SNAPSHOT, 'utf8'));
const changed = [];

for (const file of new Set([...Object.keys(before), ...FILES])) {
    if (before[file] !== current[file]) {
        changed.push(file);
    }
}

if (changed.length > 0) {
    console.error(`code changed in ${changed.length} file(s), not just comments:`);

    for (const file of changed) {
        const a = (before[file] || '').split('\n');
        const b = (current[file] || '').split('\n');

        console.error(`  ${file}`);
        for (let i = 0; i < Math.max(a.length, b.length); i++) {
            if (a[i] !== b[i]) {
                console.error(`     line ${i + 1}\n       before: ${a[i]}\n       after:  ${b[i]}`);
                break;
            }
        }
    }

    process.exit(1);
}

console.log(`code identical across ${FILES.length} files; only comments changed`);
