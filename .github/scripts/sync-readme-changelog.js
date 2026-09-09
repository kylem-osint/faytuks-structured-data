'use strict';

const fs = require( 'fs' );
const path = require( 'path' );

const { VERSION } = process.env;

if ( ! VERSION ) {
	console.error( 'Missing VERSION env var' );
	process.exit( 1 );
}

const root = path.resolve( __dirname, '../..' );
const changelogPath = path.join( root, 'CHANGELOG.md' );
const readmePath = path.join( root, 'readme.txt' );

const SECTION_PREFIX = {
	Features: 'Added',
	'Bug Fixes': 'Fixed',
	'Performance Improvements': 'Improved',
	Reverts: 'Reverted',
	Documentation: 'Docs',
	Styles: 'Styles',
	'Code Refactoring': 'Refactored',
	Tests: 'Tests',
	'Build System': 'Build',
	'Continuous Integration': 'CI',
	Chores: 'Chore',
	Added: 'Added',
	Changed: 'Changed',
	Deprecated: 'Deprecated',
	Removed: 'Removed',
	Fixed: 'Fixed',
	Security: 'Security',
};

function stripMarkdown( text ) {
	return String( text )
		.replace( /\s*\(\[[0-9a-f]{7,40}\]\([^)]+\)\)/gi, '' )
		.replace( /\[([^\]]+)\]\([^)]+\)/g, '$1' )
		.replace( /\*\*([^*]+)\*\*/g, '$1' )
		.replace( /`([^`]+)`/g, '$1' )
		.replace( /\s+/g, ' ' )
		.trim();
}

function extractReleaseBody( changelog, version ) {
	const escaped = version.replace( /\./g, '\\.' );
	// Accepts every heading shape the release tooling produces: the first
	// release is written "## 1.0.0 (2026-09-09)", later ones link to a compare
	// view as "## [1.0.1](...) (2026-09-10)", and hand-written entries use
	// "## 1.0.0 - 2026-09-09". Requiring brackets or a dash silently missed the
	// first-release form and failed the release at the prepare step. The
	// lookahead stops 1.0.0 from matching 1.0.0-beta.1.
	const headerRe = new RegExp(
		`^##\\s+(?:\\[${escaped}\\]|${escaped})(?![\\w.-])[^\\n]*$`,
		'm'
	);
	const match = headerRe.exec( changelog );
	if ( ! match ) {
		console.error( `No CHANGELOG.md section found for version ${ version }` );
		process.exit( 1 );
	}

	const start = match.index + match[ 0 ].length;
	const rest = changelog.slice( start );
	const nextHeading = rest.search( /\n##\s+/ );
	const endMarker = rest.search( /\n#\s+Changelog\b/ );
	let end = rest.length;
	if ( nextHeading !== -1 ) {
		end = Math.min( end, nextHeading );
	}
	if ( endMarker !== -1 ) {
		end = Math.min( end, endMarker );
	}
	return rest.slice( 0, end ).trim();
}

function bodyToBullets( body ) {
	const bullets = [];
	let prefix = '';

	for ( const rawLine of body.split( /\r?\n/ ) ) {
		const line = rawLine.trim();
		if ( ! line ) {
			continue;
		}

		const sectionMatch = line.match( /^###\s+(.+)$/ );
		if ( sectionMatch ) {
			prefix = SECTION_PREFIX[ sectionMatch[ 1 ].trim() ] || sectionMatch[ 1 ].trim();
			continue;
		}

		const bulletMatch = line.match( /^[-*]\s+(.+)$/ );
		if ( ! bulletMatch ) {
			continue;
		}

		let item = stripMarkdown( bulletMatch[ 1 ] );
		const scoped = item.match( /^([a-z0-9_-]+):\s+(.+)$/i );
		if ( scoped ) {
			item = `${ scoped[ 1 ] }: ${ scoped[ 2 ] }`;
		}

		if ( prefix ) {
			const lower = item.charAt( 0 ).toLowerCase() + item.slice( 1 );
			item = `${ prefix } ${ lower }`;
		}

		if ( ! /[.!?]$/.test( item ) ) {
			item += '.';
		}

		bullets.push( `- ${ item }` );
	}

	return bullets;
}

function toUpgradeNotice( bullets ) {
	const parts = bullets.map( ( b ) =>
		b
			.replace( /^-\s*/, '' )
			.replace( /\.$/, '' )
	);
	let notice = parts.join( '; ' );
	if ( notice.length > 280 ) {
		notice = `${ notice.slice( 0, 277 ).replace( /\s+\S*$/, '' ) }…`;
	}
	if ( ! /[.!?…]$/.test( notice ) ) {
		notice += '.';
	}
	return notice;
}

function insertVersionBlock( contents, sectionHeading, version, blockBody ) {
	const headingRe = new RegExp( `^==\\s*${ sectionHeading }\\s*==\\s*$`, 'mi' );
	const headingMatch = headingRe.exec( contents );
	if ( ! headingMatch ) {
		console.error( `Missing "== ${ sectionHeading } ==" in readme.txt` );
		process.exit( 1 );
	}

	const versionHeader = `= ${ version } =`;
	const afterHeadingIdx = headingMatch.index + headingMatch[ 0 ].length;
	const afterHeading = contents.slice( afterHeadingIdx );

	// Already present for this version — leave unchanged.
	const existingRe = new RegExp( `^=\\s*${ version.replace( /\./g, '\\.' ) }\\s*=\\s*$`, 'm' );
	if ( existingRe.test( afterHeading.split( /\n==\s+/ )[ 0 ] || afterHeading ) ) {
		console.log( `readme.txt ${ sectionHeading } already has ${ version }` );
		return contents;
	}

	const insertion = `\n${ versionHeader }\n${ blockBody }\n`;
	return contents.slice( 0, afterHeadingIdx ) + insertion + contents.slice( afterHeadingIdx );
}

const changelog = fs.readFileSync( changelogPath, 'utf8' );
const body = extractReleaseBody( changelog, VERSION );
const bullets = bodyToBullets( body );

if ( ! bullets.length ) {
	console.error( `No changelog bullets parsed for version ${ VERSION }` );
	process.exit( 1 );
}

let readme = fs.readFileSync( readmePath, 'utf8' );
readme = insertVersionBlock( readme, 'Changelog', VERSION, bullets.join( '\n' ) );
readme = insertVersionBlock( readme, 'Upgrade Notice', VERSION, toUpgradeNotice( bullets ) );
fs.writeFileSync( readmePath, readme );
console.log( `Synced readme.txt Changelog and Upgrade Notice for ${ VERSION }` );
