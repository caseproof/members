// Style related.
var styleSRC = './css/*.css'; // Path to .css files.
var styleDestination = './css/'; // Path to place the minified CSS files.

// JS related.
var jsSource = './js/*.js'; // Path to JS vendor folder.
var jsDestination = './js/'; // Path to place the minified JS files.

// Translation related.
var text_domain = 'members'; // Your textdomain here.
var translationFile = 'members.pot'; // Name of the transalation file.
var translationDestination = './lang'; // Where to save the translation files.
var packageName = 'members'; // Package name.
var bugReport = 'https://memberpress.com'; // Where can users report bugs.
var lastTranslator = 'The MemberPress Team <outreach@memberpress.com>'; // Last translator Email ID.
var team = 'The MemberPress Team <outreach@memberpress.com>'; // Team's Email ID.

// Files to watch
var stylesWatchFiles = './css/*.css'; // Path to all Sass partials.
var scriptsWatchFiles = './js/*.js'; // Path to all custom JS files.
var projectPHPWatchFiles = './**/*.php'; // Path to all PHP files.

/**
 * Load Plugins.
 */
var gulp = require('gulp'); // Gulp of-course

// CSS related plugins.
var minifycss = require('gulp-uglifycss'); // Minifies CSS files.
var autoprefixer = require('gulp-autoprefixer'); // Autoprefixing magic.


// JS related plugins.
var uglify = require('gulp-uglify'); // Minifies JS files


// Utility related plugins.
var replace = require('gulp-replace'); // Search and replace text
var rename = require('gulp-rename'); // Renames files E.g. style.css -> style.min.css
var lineec = require('gulp-line-ending-corrector'); // Consistent Line Endings for non UNIX systems. Gulp Plugin for Line Ending Corrector (A utility that makes sure your files have consistent line endings)
var notify = require('gulp-notify'); // Sends message notification to you
var wpPot = require('gulp-wp-pot'); // For generating the .pot file.
var sort = require('gulp-sort'); // Recommended to prevent unnecessary changes in pot-file.


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
  gulp.src([
      styleSRC,
      '!css/*.min.css'
    ])
    .pipe(autoprefixer(AUTOPREFIXER_BROWSERS))
    .pipe(rename({ suffix: '.min' }))
    .pipe(minifycss({
      maxLineLen: 0
    }))
    .pipe(gulp.dest(styleDestination))
    .pipe(notify({ message: 'TASK: "styles" Completed! 💯', onLast: true }))
});


/**
 * Task: `scripts`.
 *
 * Concatenate and uglify JS files.
 *
 * This task does the following:
 *     1. Gets the source folder for JS files
 *     2. Concatenates all the files
 *     3. Renames the concatenated JS file with suffix .min.js
 *     4. Uglifes/Minifies the JS file and generates minified JS file
 */
gulp.task('scripts', function () {
  gulp.src([
      jsSource,
      '!js/*.min.js'
    ])
    .pipe(rename({
      suffix: '.min'
    }))
    .pipe(uglify())
    .pipe(lineec()) // Consistent Line Endings for non UNIX systems.
    .pipe(gulp.dest(jsDestination))
    .pipe(notify({ message: 'TASK: "scripts" Completed! 💯', onLast: true }));
});


/**
 * WP POT Translation File Generator.
 *
 * * This task does the following:
 *     1. Gets the source of all the PHP files
 *     2. Search and replace the placeholder text for the text domain
 *     3. Sort files in stream by path or any custom sort comparator
 *     4. Applies wpPot with the variable set at the top of this file
 *     5. Generate a .pot file of i18n that can be used for l10n to build .mo file
 */
gulp.task('pot', function () {
  return gulp.src(projectPHPWatchFiles)
    .pipe( replace( /(?<="|')(tdomain)(?="|')/g, text_domain ) )
    .pipe(gulp.dest("./"))
    .pipe(sort())
    .pipe(wpPot({
      domain: text_domain,
      package: packageName,
      bugReport: bugReport,
      lastTranslator: lastTranslator,
      team: team
    }))
    .pipe(gulp.dest(translationDestination + '/' + translationFile))
    .pipe(notify({ message: 'TASK: "pot" Completed! 💯', onLast: true }))
});


/**
 * Watch Tasks.
 *
 * Watches for file changes and runs specific tasks.
 */
gulp.task('default', ['styles', 'scripts'], function () {
  gulp.watch([stylesWatchFiles, './css/*.css'], ['styles']);
  gulp.watch(scriptsWatchFiles, ['scripts']);
});
