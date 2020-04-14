const copy   = require( 'copy'   );
const rimraf = require( 'rimraf' );

var dest = 'dist';

var src = [
	'**',
	'!_assets/',
	'!_assets/**',
	'!node_modules/',
	'!node_modules/**',
	'!resources/build/',
	'!resources/build/**',
	'!package.json',
	'!package-lock.json'
];

function distribute() {

	copy( src, dest, function() {} );
}

rimraf( dest, distribute );
