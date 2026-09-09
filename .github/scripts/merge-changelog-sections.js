#!/usr/bin/env node
/**
 * Merges duplicate CHANGELOG.md sections for a single version into one.
 *
 * @semantic-release/changelog always prepends a freshly generated section and
 * never checks whether the file already documents that version. When a release
 * is prepared by hand ahead of time — as 1.0.0 was, with the full feature list
 * written before any tag existed — the result is two "## 1.0.0" headings: a
 * thin generated one on top and the real notes below.
 *
 * This runs in the prepare step, after the changelog plugin has written its
 * section, and folds them together: one heading, one "### Features", one
 * "### Bug Fixes", in first-seen order with duplicate bullets dropped.
 *
 * Usage: VERSION=1.2.3 node .github/scripts/merge-changelog-sections.js
 */

'use strict';

const fs = require('fs');
const path = require('path');

const { VERSION } = process.env;

if (!VERSION) {
    console.error('Missing VERSION env var');
    process.exit(1);
}

const CHANGELOG = path.join(__dirname, '..', '..', 'CHANGELOG.md');

/**
 * Matches every heading shape the tooling emits for a version: "## 1.0.0
 * (date)" for a first release, "## [1.0.1](compare) (date)" afterwards, and
 * "## 1.0.0 - date" for hand-written entries. The lookahead keeps 1.0.0 from
 * matching 1.0.0-beta.1. Kept in step with sync-readme-changelog.js.
 */
function headingPattern(version) {
    const escaped = version.replace(/\./g, '\\.');

    return new RegExp(`^##\\s+(?:\\[${escaped}\\]|${escaped})(?![\\w.-])[^\\n]*$`, 'gm');
}

/**
 * Splits a section body into its "### Group" buckets, preserving any text that
 * appears before the first group.
 */
function parseBody(body) {
    const groups = new Map();
    const preamble = [];
    let current = null;

    for (const line of body.split('\n')) {
        const heading = line.match(/^###\s+(.+?)\s*$/);

        if (heading) {
            current = heading[1];

            if (!groups.has(current)) {
                groups.set(current, []);
            }

            continue;
        }

        if (line.trim() === '') {
            continue;
        }

        if (current === null) {
            preamble.push(line);
            continue;
        }

        groups.get(current).push(line);
    }

    return { preamble, groups };
}

function main() {
    const original = fs.readFileSync(CHANGELOG, 'utf8');
    const pattern = headingPattern(VERSION);
    const matches = [...original.matchAll(pattern)];

    if (matches.length < 2) {
        console.log(`CHANGELOG.md has ${matches.length} section(s) for ${VERSION}; nothing to merge.`);

        return;
    }

    const sections = matches.map((match, index) => {
        const start = match.index + match[0].length;
        const next = matches[index + 1];
        const rest = original.slice(start, next ? next.index : undefined);
        // A later section for a *different* version ends this one.
        const boundary = rest.search(/^##\s+/m);

        return {
            heading: match[0],
            body: boundary === -1 ? rest : rest.slice(0, boundary),
            end: start + (boundary === -1 ? rest.length : boundary),
        };
    });

    const mergedGroups = new Map();
    const mergedPreamble = [];

    for (const section of sections) {
        const { preamble, groups } = parseBody(section.body);

        for (const line of preamble) {
            if (!mergedPreamble.includes(line)) {
                mergedPreamble.push(line);
            }
        }

        for (const [name, bullets] of groups) {
            const target = mergedGroups.get(name) || [];

            for (const bullet of bullets) {
                if (!target.includes(bullet)) {
                    target.push(bullet);
                }
            }

            mergedGroups.set(name, target);
        }
    }

    const rendered = [sections[0].heading, ''];

    if (mergedPreamble.length > 0) {
        rendered.push(...mergedPreamble, '');
    }

    for (const [name, bullets] of mergedGroups) {
        rendered.push(`### ${name}`, '', ...bullets, '');
    }

    // Rebuild the file: everything before the first duplicate heading, the
    // merged section, then everything after the last one.
    const head = original.slice(0, matches[0].index);
    const tail = original.slice(sections[sections.length - 1].end);
    const updated = `${head}${rendered.join('\n').trimEnd()}\n${tail.startsWith('\n') ? '' : '\n'}${tail}`;

    fs.writeFileSync(CHANGELOG, updated.replace(/\n{3,}/g, '\n\n'));

    console.log(
        `Merged ${sections.length} CHANGELOG.md sections for ${VERSION} into one ` +
            `(${mergedGroups.size} group(s)).`
    );
}

main();
