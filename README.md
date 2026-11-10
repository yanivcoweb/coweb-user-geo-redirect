# CoWeb User Geo Redirect

A WordPress plugin that detects a visitor's country from a local MaxMind
GeoLite2-Country database and redirects them by country. It makes no external
API calls. It works on single sites and on multisite networks.

It replaces two earlier plugins, **PW User Geo** and **User Geo Redirect**
(multisite). See [Upgrading](#upgrading-from-pw-user-geo-or-user-geo-redirect).

## Features

- Detects the visitor's country name and ISO code from `GeoLite2-Country.mmdb`
- Works fully offline (no API requests)
- Proxy/CDN-aware IP detection (Cloudflare, `X-Real-IP`, `X-Forwarded-For`)
- Caches the result per IP in a transient (12 hours by default)
- Country → URL redirect rules, with comma lists (`IL,IT,PE|https://…`) and a
  `*` fallback
- Cache-safe: the redirect and the country display run in the browser through
  AJAX, so they work behind page caches
- Runs automatically on the homepage or on every page, or only where you place
  a shortcode
- Can redirect each visitor only once (remembered for 30 days)
- Skips logged-in users and administrators, and honours a `?geo_noredirect=1`
  bypass
- On multisite, when network-activated: one set of rules for the whole network,
  edited in Network Admin

## Requirements

- WordPress 6.0+
- PHP 7.4+ (tested up to PHP 8.3)
- The `geoip2/geoip2` Composer package (bundled in the release ZIP)
- A MaxMind GeoLite2-Country database (not bundled, see below)

## Installation

1. Download `coweb-user-geo-redirect.zip` from the
   [latest release](https://github.com/yanivcoweb/coweb-user-geo-redirect/releases/latest)
   and install it from **Plugins → Add New → Upload Plugin**.
   The release ZIP already contains the `vendor/` folder.
2. Download `GeoLite2-Country.mmdb` from
   [MaxMind](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data)
   (free account required) and place it in
   `wp-content/plugins/coweb-user-geo-redirect/`.
   MaxMind's licence does not allow bundling it with the plugin.
3. Activate the plugin. On a multisite network, **Network Activate** it to
   manage one set of rules for all sites.
4. Check **Tools → User Geo** to see the detected IP and country.

### Installing from source

If you clone the repository instead of using the release ZIP, install the
dependencies yourself:

```bash
cd wp-content/plugins/coweb-user-geo-redirect
composer install --no-dev
```

### Using a different database path

The plugin looks for the database in its own folder. To keep it elsewhere,
define the path in `wp-config.php`:

```php
define( 'PW_USER_GEO_MAXMIND_DB', '/path/to/GeoLite2-Country.mmdb' );
```

or use the `pw_user_geo_maxmind_db_path` filter.

## Redirect settings

- Single site, or a network site where the plugin is not network-activated:
  **Settings → User Geo Redirect**
- Network-activated: **Network Admin → Settings → User Geo Redirect**. The
  rules apply to every site, and the page lists the network's site URLs.

| Setting | What it does |
| --- | --- |
| Enable redirects | Master switch. Off by default. |
| Run automatically | Only where the shortcode or PHP helper is used (default), on the homepage, or on every page. |
| Redirect once | After the first redirect, the visitor is not redirected again on that site for 30 days. |
| Skip for logged-in users / administrators | On by default. |
| Bypass switch | `?geo_noredirect=1` stops redirects for that visitor for 30 days. |
| Country → URL rules | See below. No rules are set by default. |

### Rules

One rule per line:

```text
IL,IT|https://il.example.com{REQUEST_URI}
FR|https://fr.example.com{REQUEST_URI}
*|https://example.com{REQUEST_URI}
```

- The part before `|` is a two-letter ISO country code, or several separated
  by commas.
- `{REQUEST_URI}` is replaced with the current path and query string. Keep it
  in every rule: without it, visitors always land on the target's homepage and
  lose the query string (for example UTM tags).
- `*` is the fallback for every other country.
- Lines starting with `#` are ignored.

A visitor is never redirected to the URL they are already on. So on a network
where every site uses the same rules, a visitor who is already on the right
site stays there.

## Shortcodes and PHP functions

### Show the visitor's country (cache-safe)

```text
[pw_user_country label="Detecting…"]
```

```php
echo pw_user_geo_country_html( 'Loading country…' );
```

The placeholder is filled in by a small script, so the page itself can be cached.

### Show location fields (not cache-safe)

```text
[pw_user_geo fields="country,country_code" wrapper="inline" show_labels="1"]
[user_country]               → Israel
[user_country format="code"] → IL
```

`wrapper` can be `inline`, `list` or `none`. These are rendered on the server,
so use them only on pages that are not cached.

### Trigger the redirect manually

When **Run automatically** is set to the shortcode option:

```text
[pw_user_geo_redirect]
```

```php
echo pw_user_geo_redirect_html();
```

### Raw data

```php
$geo     = pw_user_geo_get();          // array with ip, country, country_code, … or an error string
$country = pw_user_geo_country();      // country name, or '' if unknown
$code    = ugr_get_user_country( 'code' ); // ISO code, or '' if unknown
```

## Filters

| Filter | Default | Purpose |
| --- | --- | --- |
| `pw_user_geo_maxmind_db_path` | `PW_USER_GEO_MAXMIND_DB` | Path to the `.mmdb` file |
| `pw_user_geo_cache_ttl` | `12 * HOUR_IN_SECONDS` | How long a lookup is cached per IP |

## Testing and debugging

- **Tools → User Geo** shows the detected IP, country and any error.
- The settings page warns when `GeoLite2-Country.mmdb` is missing.
- Append `?pw_geo_ip=8.8.8.8` to any page to look up that IP instead of yours.
  The redirect script passes it on too, so you can test a rule without a VPN.
- Append `?pw_geo_flush=1` to clear the cached lookup for the current IP.
- Append `?geo_noredirect=1` to stop redirects for 30 days (sets a cookie).
- Test while logged out (or turn off both **Skip** settings), and clear the
  `pw_geo_redirected` / `pw_geo_noredirect` cookies between tests.

## Upgrading from PW User Geo or User Geo Redirect

This plugin installs into a new folder, `coweb-user-geo-redirect`.

1. Install and activate CoWeb User Geo Redirect. While an older plugin is still
   active, it stays off and shows a notice.
2. Move `GeoLite2-Country.mmdb` from the old plugin folder to the new one.
3. Deactivate and delete the old plugin.

From **PW User Geo**: settings, shortcodes and PHP functions carry over
unchanged. The settings page moved to **Settings → User Geo Redirect**.
To keep the old behaviour, leave **Run automatically** on the shortcode option.

From **User Geo Redirect** (multisite): the old rules are not imported. Network
Activate this plugin, then in **Network Admin → Settings → User Geo Redirect**:
enable redirects, set **Run automatically** to *on the homepage*, tick
**Redirect once**, and add one line per site, for example
`IL,IT,PE|https://il.example.com{REQUEST_URI}`. `[user_country]` and
`ugr_get_user_country()` keep working.

## Author

Yaniv Sasson, WordPress developer: <https://yanivsasson.co.il>

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

This product includes GeoLite2 data created by MaxMind, available from
<https://www.maxmind.com>.
