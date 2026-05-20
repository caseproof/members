// Style related.
var styleSRC = './css/*.css'; // Path to .css files.
var styleDestination = './css/'; // Path to place the minified CSS files.

// JS related.
var jsSource = './js/*.js'; // Path to JS vendor folder.
var jsDestination = './js/'; // Path to place the minified JS files.

// Files to watch
var stylesWatchFiles = ['./css/*.css', '!./css/*.min.css']; // Path to CSS files, excluding minified files.
var scriptsWatchFiles = ['./js/*.js', '!./js/*.min.js']; // Path to JS files, excluding minified files.

/**
 * Load Plugins.
 */
var gulp = require('gulp'); // Gulp of-course

// CSS related plugins.
var minifycss = require('gulp-uglifycss'); // Minifies CSS files.
var autoprefixer = require('gulp-autoprefixer'); // Autoprefixing magic.


// JS related plugins.
var terser = require('gulp-terser'); // Minifies JS files (supports ES6+).


// Utility related plugins.
var rename = require('gulp-rename'); // Renames files E.g. style.css -> style.min.css
var lineec = require('gulp-line-ending-corrector'); // Consistent Line Endings for non UNIX systems. Gulp Plugin for Line Ending Corrector (A utility that makes sure your files have consistent line endings)


// Browsers you care about for autoprefixing.
// Browserlist https        ://github.com/ai/browserslist
const AUTOPREFIXER_BROWSERS = [
  'last 2 version'
];


/**
 * Task: `styles`.
 *
 * Compiles Sass, Autoprefixes it and Minifies CSS.
 *
 * This task does the following:
 *    1. Gets the source scss file
 *    2. Compiles Sass to CSS
 *    3. Autoprefixes it and generates style.css
 *    4. Renames the CSS file with suffix .min.css
 *    5. Minifies the CSS file and generates .min.css
 */
gulp.task('styles', function () {
  return gulp.src([
      styleSRC,
      '!css/*.min.css'
    ])
    .pipe(autoprefixer(AUTOPREFIXER_BROWSERS))
    .pipe(rename({ suffix: '.min' }))
    .pipe(minifycss({
      maxLineLen: 0
    }))
    .pipe(gulp.dest(styleDestination))
});


/**
 * Task: `scripts`.
 *
 * Minify JS files.
 *
 * This task does the following:
 *     1. Gets the source folder for JS files
 *     2. Renames each JS file with suffix .min.js
 *     3. Minifies the JS file and generates minified JS file
 */
gulp.task('scripts', function () {
  return gulp.src([
      jsSource,
      '!js/*.min.js'
    ])
    .pipe(rename({
      suffix: '.min'
    }))
    .pipe(terser())
    .pipe(lineec()) // Consistent Line Endings for non UNIX systems.
    .pipe(gulp.dest(jsDestination))
});


/**
 * Watch Task.
 */
function watchFiles() {
  // Watch CSS files but ignore minified files
  gulp.watch(stylesWatchFiles, gulp.series('styles'));
  
  // Watch JS files but ignore minified files
  gulp.watch(scriptsWatchFiles, gulp.series('scripts'));
}


/**
 * Define default task using Gulp 4.x syntax.
 */
gulp.task('default', gulp.series(
  gulp.parallel('styles', 'scripts'),
  watchFiles
));
