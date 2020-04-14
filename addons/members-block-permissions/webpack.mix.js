/**
 * Lavarel Mix Config.
 *
 * Laravel Mix is a simplified wrapper for Webpack.  Use this file to add CSS/JS
 * files to compile.
 *
 * @package   CustomizeSectionButton
 * @author    WPTRT <themes@wordpress.org>
 * @copyright 2019 WPTRT
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/WPTRT/customize-section-button
 */

// Import required packages.
const mix = require( 'laravel-mix' );

// Set dev path.
const devPath  = 'resources';

// Run the export process.
if ( process.env.export ) {
	const exportTheme = require( './webpack.mix.export.js' );
	return;
}

// Set public path.
mix.setPublicPath( 'public' );

// Set options.
mix.options( {
	postCss        : [ require( 'postcss-preset-env' )() ],
	processCssUrls : false
} );

// Source maps.
mix.sourceMaps();

// Versioning and cache-busting with `mix-manifest.json`.
mix.version();

// Compile JS.
mix.react( `${devPath}/js/editor.js`, 'js' );
mix.react( `${devPath}/js/upsell.js`, 'js' );

// Sass configuration.
var sassConfig = {
	outputStyle : 'expanded',
	indentType  : 'tab',
	indentWidth : 1
};

// Compile SASS/CSS.
mix.sass( `${devPath}/scss/editor.scss`, 'css', sassConfig );

// Extra Webpack config.
mix.webpackConfig( {
	stats       : 'minimal',
	devtool     : mix.inProduction() ? false : 'source-map',
	performance : { hints  : false    },
	externals   : { jquery : 'jQuery' },
} );
