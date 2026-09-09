'use strict';

/**
 * Verifies the plugin version is identical everywhere it is declared.
 *
 * Run in CI on every pull request so a release cannot ship with a version that
 * only got bumped in some of the files.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '../..' );

function read( file ) {
	return fs.readFileSync( path.join( root, file ), 'utf8' );
}

function match( file, pattern, label ) {
	const found = pattern.exec( read( file ) );

	if ( ! found ) {
		console.error( `Could not read ${ label } from ${ file }` );
		process.exit( 1 );
	}

	return found[ 1 ].trim();
}

const versions = {
	'package.json': JSON.parse( read( 'package.json' ) ).version,
	'composer.json': JSON.parse( read( 'composer.json' ) ).version,
	'fn-structured-data.php (header)': match(
		'fn-structured-data.php',
		/^\s*\*\s*Version:\s*(.+)$/m,
		'plugin header version'
	),
	'fn-structured-data.php (constant)': match(
		'fn-structured-data.php',
		/define\(\s*'FN_STRUCTURED_DATA_VERSION'\s*,\s*'([^']*)'\s*\)/,
		'version constant'
	),
	'readme.txt (stable tag)': match( 'readme.txt', /^Stable tag:\s*(.+)$/m, 'stable tag' ),
};

const unique = [ ...new Set( Object.values( versions ) ) ];

for ( const [ label, version ] of Object.entries( versions ) ) {
	console.log( `${ label }: ${ version }` );
}

if ( unique.length !== 1 ) {
	console.error( `\nVersion mismatch: found ${ unique.join( ', ' ) }` );
	process.exit( 1 );
}

const changelog = read( 'CHANGELOG.md' );
const escaped = unique[ 0 ].replace( /\./g, '\\.' );

if ( ! new RegExp( `^##\\s+(?:\\[${ escaped }\\]|${ escaped }\\b)`, 'm' ).test( changelog ) ) {
	console.error( `\nCHANGELOG.md has no section for ${ unique[ 0 ] }` );
	process.exit( 1 );
}

console.log( `\nAll version references agree on ${ unique[ 0 ] }` );
