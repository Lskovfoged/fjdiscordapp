const { mix } = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */
//mix.autoload({ 'jquery': ['window.$', 'window.jQuery'] });
mix.setResourceRoot('http://fjapp.dev/');
mix.js('resources/assets/js/app.js', 'public/js/app.js')
   .js('resources/assets/js/app-admin.js', 'public/js/app-admin.js')
   .sass('resources/assets/sass/app.scss', 'public/css/app.css')
   .browserSync({
        proxy: 'fjapp.dev'
   });