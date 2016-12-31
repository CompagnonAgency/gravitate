// -----------------------------------------------------------------------------
// Dependencies
// -----------------------------------------------------------------------------

var gulp = require('gulp');
var sass = require('gulp-sass');
var autoprefixer = require('gulp-autoprefixer');
var browserify = require('browserify');
var uglify = require('gulp-uglifyjs');
var watchify = require('watchify');
var source = require('vinyl-source-stream');
var notify = require('gulp-notify');
var babelify = require('babelify');
var streamify = require('gulp-streamify');
var rename = require("gulp-rename");

// -----------------------------------------------------------------------------
// Configuration
// -----------------------------------------------------------------------------

var userPath = './user/themes/personaltrainersam/';
var sassInput = userPath + 'scss/**/*.scss';
var sassOutput = userPath + 'css';
var sassOptions = { outputStyle: 'compressed' };
var autoprefixerOptions = { browsers: ['last 2 versions', '> 5%', 'Firefox ESR'] };
var javascriptMainFile = userPath + 'js/src/application.js';
var javascriptInput = userPath + 'js/src/**/*.js';
var javascriptOutput = 'application.min.js';
var output = userPath + 'js/dist';

// -----------------------------------------------------------------------------
// Browserify
// -----------------------------------------------------------------------------

gulp.task('browserify', function () {
  watchify(browserify({
      entries: [javascriptMainFile],
      debug: true
    }))
    .transform(babelify, {presets: ['es2016', 'stage-0']})
    .bundle()
    .on('error', function(err) {
      console.error(err.message);
    })
    .pipe(source(javascriptOutput))
    .pipe(streamify(uglify()))
    .pipe(gulp.dest(output))
    .pipe(notify('Built source!'));
});

// -----------------------------------------------------------------------------
// Sass compilation
// -----------------------------------------------------------------------------

gulp.task('sass', function () {
  return gulp
    .src(sassInput)
    .pipe(sass(sassOptions).on('error', sass.logError))
    .pipe(autoprefixer(autoprefixerOptions))
    .pipe(rename('application.min.css'))
    .pipe(gulp.dest(sassOutput));
});

// -----------------------------------------------------------------------------
// Watch
// -----------------------------------------------------------------------------

gulp.task('watch', function() {
  gulp.start('compile');
  gulp.watch([javascriptInput], ['browserify']);
  gulp.watch([sassInput], ['sass']);
});

// -----------------------------------------------------------------------------
// Default task
// -----------------------------------------------------------------------------

gulp.task('compile', ['sass', 'browserify']);
gulp.task('default', ['watch']);
