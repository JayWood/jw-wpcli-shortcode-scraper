# WP CLI Shortcode Scraper

This is a shortcode scraper with multisite compatability. The scraper will walk over any site you specify looking for
shortcodes. All found shortcodes in posts will be put together for a report, which is output at the end of the operation.

**Pull requests are always welcome**

## Screenshots
_( showing the output in console )_
![](https://www.plugish.com/wp-content/uploads/2018/07/scraper-results.png)

_( showing the resulting CSV output )_
![](https://www.plugish.com/wp-content/uploads/2018/07/scraper-results-csv.png)

## Usage

### Installation - Composer


`composer require jaywood/jw-shortcode-scraper`

If you happen to use the sweet [Composer Installers](https://github.com/composer/installers) library, this CLI script is
marked as a `wp-cli-package` for ease of use later.

### Installation - Manual

1. Download or clone the repository to `wp-content/mu-plugins/jw-wpcli-shortcode-scraper/` _( Name is up to you )_
1. Create a new file in `wp-content/mu-plugins` call it whatever you want, I use `init.php`
1. Require the file like so:
```
<?php

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once 'jw-wpcli-shortcode-scraper/jw-shortcode-scraper.php';
}
```

Now since your `init.php` file is in `wp-content/mu-plugins` it will always load.

### Command Syntax
- `wp jw-shortcode-scraper scrape [--export] [--site=<slug>]`
- `export` - Exports to a csv file, if possible, relative to the current directory.
- `site` - Use any site in a multi-site environment, like if you have a sub-site called `favorite-dogs` it would be `--site=favorite-dogs`

_( A wp-cli plugin by [Jay Wood](https://www.plugish.com) )_