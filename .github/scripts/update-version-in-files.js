'use strict';

const fs = require( 'fs' );
const path = require( 'path' );

const { VERSION } = process.env;

if ( ! VERSION ) {
	console.error( 'Missing VERSION env var' );
	process.exit( 1 );
}

const root = path.resolve( __dirname, '../..' );

const replacements = [
	{
		file: 'composer.json',
		from: /("version"\s*:\s*")[^"]*(")/,
		to: `$1${ VERSION }$2`,
	},
	{
		file: 'fn-structured-data.php',
		from: /^\s*\*\s*Version:\s*.*$/m,
		to: ` * Version: ${ VERSION }`,
	},
	{
		file: 'fn-structured-data.php',
		from: /define\(\s*'FN_STRUCTURED_DATA_VERSION'\s*,\s*'[^']*'\s*\)/,
		to: `define( 'FN_STRUCTURED_DATA_VERSION', '${ VERSION }' )`,
	},
	{
		file: 'readme.txt',
		from: /^Stable tag:\s*.*$/m,
		to: `Stable tag: ${ VERSION }`,
	},
];

for ( const { file, from, to } of replacements ) {
	const filePath = path.join( root, file );
	const contents = fs.readFileSync( filePath, 'utf8' );
	if ( ! from.test( contents ) ) {
		console.error( `Pattern not found in ${ file }: ${ from }` );
		process.exit( 1 );
	}
	fs.writeFileSync( filePath, contents.replace( from, to ) );
	console.log( `Updated ${ file }` );
}
