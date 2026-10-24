/**
 * Babel config for `wp-scripts` builds.
 *
 * Same as WordPress's default preset, except JSX compiles to the classic
 * `wp.element.createElement` calls instead of the automatic `react/jsx-runtime`
 * import. The automatic runtime needs the `react-jsx-runtime` script, which only
 * exists on WordPress 6.6+, and Members supports older versions.
 */
module.exports = ( api ) => {
	api.cache( true );

	return {
		presets: [ '@wordpress/babel-preset-default' ],
		// Plugins run before presets, so JSX is already compiled by the time
		// the preset's own (automatic) JSX transform would see it.
		plugins: [
			[
				'@babel/plugin-transform-react-jsx',
				{
					runtime: 'classic',
					pragma: 'wp.element.createElement',
					pragmaFrag: 'wp.element.Fragment',
				},
			],
		],
	};
};
