'use strict';

/**
 * Verifies the built plugin ZIP.
 *
 * Checks that everything required at runtime is present and that development
 * material never ships. Runs as part of `npm run build`, so a packaging
 * mistake fails the build rather than reaching a WordPress site.
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );

const root = path.resolve( __dirname, '../..' );
const slug = 'fn-structured-data';
const version = JSON.parse( fs.readFileSync( path.join( root, 'package.json' ), 'utf8' ) ).version;
const zipPath = path.join( root, 'dist', `${ slug }-${ version }.zip` );

if ( ! fs.existsSync( zipPath ) ) {
	console.error( `Missing build artifact: ${ path.relative( root, zipPath ) }` );
	process.exit( 1 );
}

const listing = execFileSync( 'unzip', [ '-Z1', zipPath ], { encoding: 'utf8' } )
	.split( '\n' )
	.map( ( line ) => line.trim() )
	.filter( Boolean );

const required = [
	`${ slug }/${ slug }.php`,
	`${ slug }/uninstall.php`,
	`${ slug }/readme.txt`,
	`${ slug }/src/Plugin.php`,
	`${ slug }/src/Updater.php`,
	`${ slug }/src/Admin/Settings.php`,
	`${ slug }/src/Schema/RankMathIntegration.php`,
	`${ slug }/src/Schema/OrganizationTransformer.php`,
	`${ slug }/src/Schema/ArticleTransformer.php`,
	`${ slug }/src/PostMeta/ArticleType.php`,
	`${ slug }/assets/css/admin.css`,
	`${ slug }/assets/js/admin.js`,
	`${ slug }/vendor/autoload.php`,
	`${ slug }/vendor/composer/autoload_real.php`,
	`${ slug }/vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php`,
];

const forbidden = [
	/^[^/]+\/(tests|node_modules|dist|\.build|\.github|\.git)\//,
	/^[^/]+\/(composer\.(json|lock)|package(-lock)?\.json|phpunit[^/]*\.xml\.dist|phpcs\.xml\.dist|phpstan\.neon\.dist|\.releaserc\.json|\.gitignore|\.editorconfig|CLAUDE\.md|RELEASING\.md|TESTING\.md|README\.md|CHANGELOG\.md)$/,
	/^[^/]+\/vendor\/(phpunit|phpstan|squizlabs|wp-coding-standards|php-stubs|szepeviktor|phpcompatibility|phpcsstandards|dealerdirect|yoast|sebastian|doctrine|myclabs|nikic|phar-io|theseer|symfony)\//,
	/\.DS_Store$/,
	/\.map$/,
];

const missing = required.filter( ( file ) => ! listing.includes( file ) );
const leaked = listing.filter( ( file ) => forbidden.some( ( pattern ) => pattern.test( file ) ) );

// Every entry must live under the plugin slug directory.
const misplaced = listing.filter( ( file ) => ! file.startsWith( `${ slug }/` ) );

let failed = false;

if ( missing.length ) {
	console.error( 'Missing required runtime files:' );
	missing.forEach( ( file ) => console.error( `  - ${ file }` ) );
	failed = true;
}

if ( leaked.length ) {
	console.error( 'Development-only files present in the package:' );
	leaked.forEach( ( file ) => console.error( `  - ${ file }` ) );
	failed = true;
}

if ( misplaced.length ) {
	console.error( `Files outside the ${ slug }/ directory:` );
	misplaced.forEach( ( file ) => console.error( `  - ${ file }` ) );
	failed = true;
}

if ( failed ) {
	process.exit( 1 );
}

const header = fs.readFileSync( path.join( root, `${ slug }.php` ), 'utf8' );
const headerVersion = /^\s*\*\s*Version:\s*(.+)$/m.exec( header );

if ( ! headerVersion || headerVersion[ 1 ].trim() !== version ) {
	console.error( `Plugin header version does not match package version ${ version }` );
	process.exit( 1 );
}

console.log(
	`Verified ${ path.relative( root, zipPath ) }: ${ listing.length } entries, ${ required.length } runtime checks passed.`
);
