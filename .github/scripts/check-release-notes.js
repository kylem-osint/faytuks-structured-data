#!/usr/bin/env node
/**
 * Smoke-tests the release notes pipeline before it can break a real release.
 *
 * semantic-release only renders notes once it has decided to publish, so a
 * broken changelog preset stays invisible until the moment it matters and then
 * fails the release workflow after the version bump has already been worked
 * out. That is exactly how the 1.0.0 release failed: the conventionalcommits
 * preset was bumped to a major that requires conventional-changelog-writer 9,
 * while @semantic-release/release-notes-generator still depends on writer 8.
 *
 * This runs the real generateNotes step against a synthetic commit, so any
 * incompatibility between semantic-release, the preset and the writer surfaces
 * on the pull request instead of during the release.
 */

'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..', '..');

function moduleVersion(name) {
    try {
        return require(path.join(ROOT, 'node_modules', name, 'package.json')).version;
    } catch (error) {
        return null;
    }
}

/**
 * Reads the preset out of .releaserc.json so this check follows the release
 * configuration rather than hard-coding a preset that may later change.
 */
function configuredPreset() {
    const config = JSON.parse(fs.readFileSync(path.join(ROOT, '.releaserc.json'), 'utf8'));

    for (const plugin of config.plugins || []) {
        if (Array.isArray(plugin) && plugin[0] === '@semantic-release/release-notes-generator') {
            return (plugin[1] || {}).preset || 'angular';
        }
    }

    return 'angular';
}

async function main() {
    const generatorPath = path.join(
        ROOT,
        'node_modules',
        '@semantic-release',
        'release-notes-generator',
        'index.js'
    );

    if (!fs.existsSync(generatorPath)) {
        console.error('@semantic-release/release-notes-generator is not installed; run npm ci first.');
        process.exit(1);
    }

    const preset = configuredPreset();

    console.log(`preset:                        ${preset}`);
    console.log(`semantic-release:              ${moduleVersion('semantic-release')}`);
    console.log(`release-notes-generator:       ${moduleVersion('@semantic-release/release-notes-generator')}`);
    console.log(`conventional-changelog-writer: ${moduleVersion('conventional-changelog-writer')}`);
    console.log(`preset package:                ${moduleVersion(`conventional-changelog-${preset}`)}`);

    const { generateNotes } = await import(`file://${generatorPath}`);

    // A fix commit is enough to exercise the template: it renders a section
    // heading, a scope, a subject and a commit link.
    const context = {
        cwd: ROOT,
        options: { repositoryUrl: 'https://github.com/kylem-osint/faytuks-structured-data' },
        lastRelease: {},
        nextRelease: { version: '0.0.0-check', gitTag: 'v0.0.0-check', channel: null },
        commits: [
            {
                hash: '0000000000000000000000000000000000000000',
                message: 'fix(release): verify the notes pipeline renders\n',
                committerDate: new Date().toISOString(),
                author: { name: 'CI' },
                committer: { name: 'CI' },
            },
        ],
    };

    let notes;

    try {
        notes = await generateNotes({ preset }, context);
    } catch (error) {
        console.error('\nRelease notes generation failed.\n');
        console.error(error.message);
        console.error(
            '\nThe changelog preset, conventional-changelog-writer and ' +
                '@semantic-release/release-notes-generator must be compatible with each other. ' +
                'Check which major of the preset the installed writer supports before upgrading either.'
        );
        process.exit(1);
    }

    if (!notes || !notes.includes('verify the notes pipeline renders')) {
        console.error('\nRelease notes rendered but did not contain the test commit:\n');
        console.error(notes);
        process.exit(1);
    }

    console.log('\nRelease notes pipeline renders correctly.');
}

main().catch((error) => {
    console.error(error);
    process.exit(1);
});
