# PW User Geo

A lightweight WordPress plugin that detects a visitor's country from a local
MaxMind GeoLite2-Country database. It makes no external API calls.

It also offers optional country-based redirect rules and a cache-safe way to
show the visitor's country through shortcodes or PHP helpers.

## Features

- Detects the visitor's country name and ISO code from `GeoLite2-Country.mmdb`
- Works fully offline (no API requests)
- Proxy/CDN-aware IP detection (Cloudflare, `X-Real-IP`, `X-Forwarded-For`)
- Caches the result per IP in a transient (12 hours by default)
- Settings page for country → URL redirect rules
- Can skip redirects for logged-in users and administrators, and honours a
  `?geo_noredirect=1` bypass
- Redirects and country output run through AJAX, so they work behind page caches

## Requirements

- WordPress 6.0+
- PHP 7.4+ (tested up to PHP 8.3)
- The `geoip2/geoip2` Composer package (bundled in the release ZIP)
- A MaxMind GeoLite2-Country database (not bundled, see below)

## Installation

1. Download `pw-user-geo.zip` from the
   [latest release](https://github.com/yanivcoweb/pw-user-geo/releases/latest)
   and install it from **Plugins → Add New → Upload Plugin**.
   The release ZIP already contains the `vendor/` folder.
2. Download `GeoLite2-Country.mmdb` from
   [MaxMind](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data)
   (free account required) and place it in `wp-content/plugins/pw-user-geo/`.
   MaxMind's licence does not allow bundling it with the plugin.
3. Activate the plugin.
4. Check **Tools → PW User Geo** to see the detected IP and country.

### Installing from source

If you clone the repository instead of using the release ZIP, install the
dependencies yourself:

```bash
cd wp-content/plugins/pw-user-geo
composer install --no-dev
```

### Using a different database path

The plugin looks for the database next to `pw-user-geo.php`. To keep it
elsewhere, define the path in `wp-config.php`:

```php
define( 'PW_USER_GEO_MAXMIND_DB', '/path/to/GeoLite2-Country.mmdb' );
```

or use the `pw_user_geo_maxmind_db_path` filter.

## Redirect rules

Go to **Settings → PW User Geo Redirects**, tick **Enable redirects** and add
one rule per line:

```text
IL|https://il.example.com{REQUEST_URI}
FR|https://fr.example.com{REQUEST_URI}
*|https://example.com{REQUEST_URI}
```

- `CC` is a two-letter ISO country code (`IL`, `FR`, `US`, …).
- `{REQUEST_URI}` is replaced with the current path and query string.
- `*` is the fallback for every other country.
- Lines starting with `#` are ignored.

No rules are set by default. Redirects only run on pages that output the
redirect script (see below), and never when the target URL is the current URL.

## Shortcodes and PHP functions

### Show the visitor's country (cache-safe)

Shortcode:

```text
[pw_user_country label="Detecting…"]
```

PHP:

```php
echo pw_user_geo_country_html( 'Loading country…' );
```

The placeholder is filled in by a small script, so the page itself can be cached.

### Show location fields (not cache-safe)

```text
[pw_user_geo fields="country,country_code" wrapper="inline" show_labels="1"]
```

`wrapper` can be `inline`, `list` or `none`. Use this only on pages that are
not cached, because the value is rendered on the server.

### Trigger the redirect

Shortcode:

```text
[pw_user_geo_redirect]
```

PHP (for example in the theme header):

```php
echo pw_user_geo_redirect_html();
```

### Raw data

```php
$geo = pw_user_geo_get();        // array with ip, country, country_code, … or an error string
$country = pw_user_geo_country(); // country name, or '' if unknown
```

## Filters

| Filter | Default | Purpose |
| --- | --- | --- |
| `pw_user_geo_maxmind_db_path` | `PW_USER_GEO_MAXMIND_DB` | Path to the `.mmdb` file |
| `pw_user_geo_cache_ttl` | `12 * HOUR_IN_SECONDS` | How long a lookup is cached per IP |

## Debugging

- **Tools → PW User Geo** shows the detected IP, country and any error.
- Append `?pw_geo_ip=8.8.8.8` to look up a specific IP instead of yours.
- Append `?pw_geo_flush=1` to clear the cached lookup for the current IP.
- Append `?geo_noredirect=1` to stop redirects for 30 days (sets a cookie).

## Author

Yaniv Sasson, WordPress developer: <https://yanivsasson.co.il>

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

This product includes GeoLite2 data created by MaxMind, available from
<https://www.maxmind.com>.
