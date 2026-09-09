#!/usr/bin/env node
/**
 * Appends hand-written notes to the generated release notes.
 *
 * semantic-release builds release notes purely from commit subjects, so a
 * version whose notes were written by hand before any tag existed would publish
 * a GitHub Release describing only the commit that happened to trigger it. That
 * is the situation for 1.0.0: the plugin was built under non-releasing commit
 * types, and the real notes live in CHANGELOG.md.
 *
 * Wired in as @semantic-release/exec's generateNotesCmd, whose stdout is
 * appended to the generated notes. If CHANGELOG.md has no pre-existing section
 * for the version being released — the normal case once a project is releasing
 * regularly — this prints nothing and changes nothing.
 *
 * It never fails. Anything unexpected results in empty output, leaving the
 * generated notes exactly as they were, because this must not be able to break
 * a release.
 *
 * Usage: VERSION=1.2.3 node .github/scripts/release-notes-supplement.js
 */

'use strict';

const fs = require('fs');
const path = require('path');

function supplement(version) {
    if (!version) {
        return '';
    }

    const changelog = fs.readFileSync(path.join(__dirname, '..', '..', 'CHANGELOG.md'), 'utf8');
    const escaped = version.replace(/\./g, '\\.');
    // Same heading shapes accepted by sync-readme-changelog.js and
    // merge-changelog-sections.js.
    const heading = new RegExp(`^##\\s+(?:\\[${escaped}\\]|${escaped})(?![\\w.-])[^\\n]*$`, 'm');
    const match = heading.exec(changelog);

    if (!match) {
        return '';
    }

    const rest = changelog.slice(match.index + match[0].length);
    const boundary = rest.search(/^##\s+/m);

    return (boundary === -1 ? rest : rest.slice(0, boundary)).trim();
}

try {
    const notes = supplement(process.env.VERSION);

    if (notes !== '') {
        process.stdout.write(`${notes}\n`);
    }
} catch (error) {
    // Deliberately silent: empty output is a safe no-op, and stderr noise here
    // would look like a release failure.
}
