/**
 * Builds the browser bundles from js/src.
 *
 * The shipped files are committed because WordPress.org serves the plugin
 * straight from source control. That is why this exists: the minified file
 * used to be maintained by hand, so an edit to the readable source could
 * ship without reaching the file visitors actually run.
 *
 * `node build.mjs --check` rebuilds into memory and fails if the committed
 * output differs, which is what CI runs.
 */

import {build} from 'esbuild';
import {readFile, writeFile} from 'node:fs/promises';
import {existsSync} from 'node:fs';
import {dirname, join} from 'node:path';
import {fileURLToPath} from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const check = process.argv.includes('--check');

const BUNDLES = [
    {entry: 'js/src/index.js', out: 'js/intl-datetime-calendar'},
    {entry: 'js/src/admin.js', out: 'js/intl-datetime-calendar-admin'},
];

const BANNER = '/*! Intl DateTime Calendar | GPL-2.0-or-later | built from js/src, do not edit by hand */';

/** Bundle one entry point at a given minification setting. */
async function bundle(entry, minify) {
    const result = await build({
        entryPoints: [join(here, entry)],
        bundle: true,
        minify,
        format: 'iife',
        target: ['es2020'],
        legalComments: 'none',
        banner: {js: BANNER},
        write: false,
    });

    return result.outputFiles[0].text;
}

let failed = false;

for (const {entry, out} of BUNDLES) {
    for (const [suffix, minify] of [['.js', false], ['.min.js', true]]) {
        const target = join(here, out + suffix);
        const code = await bundle(entry, minify);

        if (!check) {
            await writeFile(target, code, 'utf8');
            console.log(`built ${out}${suffix}  (${code.length} bytes)`);
            continue;
        }

        const committed = existsSync(target) ? await readFile(target, 'utf8') : null;

        if (committed !== code) {
            failed = true;
            console.error(
                committed === null
                    ? `MISSING ${out}${suffix}`
                    : `STALE   ${out}${suffix} does not match js/src; run "npm run build" and commit the result`
            );
        } else {
            console.log(`current ${out}${suffix}`);
        }
    }
}

if (failed) {
    process.exit(1);
}
