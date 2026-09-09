#!/usr/bin/env node
/**
 * Asserts that the Plugin Update Checker source repository matches the
 * repository this workflow is running in.
 *
 * The plugin slug (fn-structured-data) and the GitHub repository name
 * (faytuks-structured-data) differ on purpose, which makes it easy for the
 * updater constant to drift and point every installed site at a 404. Runs only
 * when GITHUB_REPOSITORY is set, so local checkouts are unaffected.
 */

'use strict';

const fs = require('fs');
const path = require('path');

const repository = process.env.GITHUB_REPOSITORY;

if (!repository) {
	console.log('GITHUB_REPOSITORY is not set; skipping update source check.');
	process.exit(0);
}

const updaterPath = path.join(__dirname, '..', '..', 'src', 'Updater.php');
const source = fs.readFileSync(updaterPath, 'utf8');
const match = source.match(/const\s+REPOSITORY_URL\s*=\s*'([^']+)'/);

if (!match) {
	console.error('Could not find REPOSITORY_URL in src/Updater.php.');
	process.exit(1);
}

const configured = match[1];
const expected = `https://github.com/${repository}/`;

console.log(`configured update source: ${configured}`);
console.log(`current repository:       ${expected}`);

const normalize = (value) => value.replace(/\/+$/, '').toLowerCase();

if (normalize(configured) !== normalize(expected)) {
	console.error(
		`\nUpdate source mismatch.\n` +
			`src/Updater.php points at ${configured}, but this repository is ${expected}.\n` +
			`Update REPOSITORY_URL so Plugin Update Checker can reach the releases.`
	);
	process.exit(1);
}

console.log('\nUpdate source matches the current repository.');
