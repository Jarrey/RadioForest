# Radio Forest 📻

A PHP-based online radio web player. It automatically scans M3U playlist files in a configurable playlist directory, parses station metadata, and provides search, region filtering, theme switching, and live playback in the browser.

## Table of contents

- [Project structure](#project-structure)
- [Overview](#overview)
- [Runtime configuration](#runtime-configuration)
- [Multilingual UI](#multilingual-ui)
- [Playlist generation scripts](#playlist-generation-scripts)
- [Playlist format](#playlist-format)
- [Deployment](#deployment)
  - [Option 1: Deploy the built file](#option-1-deploy-the-built-file)
  - [Option 2: Build from source](#option-2-build-from-source)
  - [Option 3: Docker deployment](#option-3-docker-deployment)
- [Key features](#key-features)
- [Requirements](#requirements)
- [Development notes](#development-notes)
- [Notes](#notes)

## Project structure

```text
radioweb/
├── index.dev.php          # development source file containing editable PHP/HTML/CSS/JS
├── index.php              # built deployment file
├── build.js               # build script that compresses CSS/JS and generates index.php
├── config.php             # optional runtime configuration
├── scripts/               # playlist synchronization scripts
│   ├── radioBrowserService.py
│   └── syncInternetRatio.py
├── lang/                  # UI translation dictionaries
└── package.json           # build dependency manifest
```

## Overview

- The app reads all `radio_*.m3u` files from a configurable playlist directory, defaulting to `./playlists`. This can be overridden via `config.php`.
- Playlist files should use the naming pattern `radio_<region_code>.m3u`.
- If no playlist files are found, the page will display an empty station list.
- The UI supports multiple languages and defaults to the browser locale.

## Runtime configuration

You can create an optional `config.php` in the project root to override the default playlist directory and cache file path without editing `index.php`:

```php
define('PLAYLIST_DIR', __DIR__ . '/playlists');
define('CACHE_FILE', __DIR__ . '/stations.cache.json');
```

If `config.php` is absent, the app will use the defaults `./playlists` and `./stations.cache.json`.

## Multilingual UI

- Translation dictionaries are stored in the `lang/` directory.
- Supported languages: Simplified Chinese, English, Spanish, French, German, Italian, Japanese, Korean, Russian.
- Users can switch languages from the top-right selector and see country flags for each option.
- Category filters, region labels and other UI text are also translated.

## Playlist generation scripts

The repository includes two helper Python scripts for fetching radio station data from radio-browser.info and generating playlist files for the web app:

- `scripts/radioBrowserService.py` — provides radio-browser API request functionality.
- `scripts/syncInternetRatio.py` — downloads stations for specified countries/regions and writes `radio_<code>.m3u` and `radio.m3u`.

Usage example:

```bash
python scripts/syncInternetRatio.py CN,US --target-dir . --backup-dir ./backup
```

Proxy is disabled by default. If you need proxy support, pass `--proxy` and the script will use the standard `HTTP_PROXY` / `HTTPS_PROXY` environment variables.

You can schedule the script to run automatically using cron on Linux/macOS or Task Scheduler on Windows.

### syncInternetRatio.py arguments

- `countries` (required)
  - Comma-separated ISO 3166-1 alpha-2 country/region codes, e.g. `CN,US,GB`.
- `--target-dir DIR`
  - Directory to write generated M3U files. Default: `.`
- `--backup-dir DIR`
  - Directory to save ZIP backups of existing playlist files. Default: `./backup`
- `--no-backup`
  - Skip backing up existing `radio_<code>.m3u` and `radio.m3u` files.
- `--show-broken`
  - Include stations that failed the last check. By default, broken stations are filtered out.
- `--page-size N`
  - Number of stations fetched per API request page. Default: `500`.
- `--timeout SEC`
  - Socket timeout in seconds for each API request. Default: `120`.
- `--proxy`
  - Enable HTTP proxy support using the standard `HTTP_PROXY` / `HTTPS_PROXY` environment variables.

## Playlist format

Example M3U content:

```m3u
#EXTM3U
#EXTINF:-1 tvg-name="CNR Radio" tvg-logo="https://example.com/logo.png" group-title="China",CNR Radio
http://lhttp.cnr.cn/live/zgzs/64k.mp3
```

Recommended file names:

| File name             | Meaning      |
| --------------------- | ------------ |
| `radio_cn.m3u`        | China radio  |
| `radio_us.m3u`        | USA radio    |
| `radio_jp.m3u`        | Japan radio  |
| `radio_<region>.m3u`  | Region radio |

Supported tags:

- `tvg-name` — station name
- `tvg-logo` — station logo URL
- `group-title` — country/group name for filtering

## Deployment

### Option 1: Deploy the built file

Upload `index.php` and the `radio_*.m3u` files to a PHP-capable web server. `node_modules/` and `package-lock.json` are not required.

### Option 2: Build from source

Edit `index.dev.php`, then run:

```bash
npm install
node build.js
```

The build script will compress inline CSS and JavaScript, preserve the PHP logic, and write the result to `index.php`.

### Option 3: Docker deployment

The project includes Docker deployment configuration and is intended to work with the prebuilt GHCR image. `docker/docker-compose.yml` already uses `ghcr.io/jarrey/radioforest:latest`, so local image builds are generally not required.

#### Prepare `.env`

Copy the sample configuration file and edit it:

```powershell
cd docker
copy .env.sample .env
```

Then set values in `docker/.env` such as:

- `HTTP_PORT` - host port exposed by the container for HTTP
- `HTTPS_PORT` - host port exposed by the container for HTTPS
- `SSL_CERT_PATH` - TLS certificate path inside the container
- `SSL_KEY_PATH` - TLS private key path inside the container
- `SYNC_COUNTRIES` - country codes for playlist synchronization
- `SYNC_TARGET_DIR` - target directory for generated playlists
- `SYNC_BACKUP_DIR` - backup directory
- `SYNC_CRON` - optional cron expression for scheduled sync

If you do not want scheduled sync, leave `SYNC_CRON` empty.

For HTTPS, mount your certificate files into the container and set `SSL_CERT_PATH` / `SSL_KEY_PATH` to the mounted paths, for example `/etc/nginx/ssl/server.crt` and `/etc/nginx/ssl/server.key`.

#### Run with Docker Compose

Start the container:

```bash
cd docker
docker compose up -d
```

Then open:

```text
http://localhost:18882
```

To stop the service:

```bash
docker compose down
```

#### Manual sync and scheduled tasks

To run sync manually inside the container:

```bash
cd docker
docker compose exec app sh -c "./sync.sh"
```

Or from the repository root:

```bash
docker compose -f docker/docker-compose.yml exec app sh -c "./sync.sh"
```

If `SYNC_CRON` is configured, the container will start `crond` and run scheduled sync jobs.

Manual sync logs are written to:

- `./logs/sync.log`

If scheduled sync is enabled, cron logs are written to:

- `./logs/cron.log`

#### Additional notes

- The image does not include local `radio_*.m3u` files.
- Use host mounts to provide playlists, backups, and logs.
- After changing Docker-related files, rebuild with:

```bash
cd docker
docker compose up --build -d
```

or from the repository root:

```bash
docker compose -f docker/docker-compose.yml up --build -d
```

## Key features

- Multiple playlists: reads all `radio_*.m3u` files and merges stations
- Region filtering: supports multiple regions with flag icons
- Multi-language UI: browser locale default with manual language switching and flags
- Keyword search: quickly filters station names
- Theme switching: 12 color themes available
- Fullscreen player: animated waveform, playback status, and clock display
- Responsive design: supports mobile, tablet, and desktop browsers

## Requirements

| Component    | Requirement                              |
| ------------ | ---------------------------------------- |
| PHP          | 5.6+                                     |
| Python       | 3.x                                      |
| requests     | Python package for HTTP API requests     |
| Web server   | Apache / Nginx / other PHP-capable server |
| Browser      | Modern browser with HTML5 `<audio>` support |
| Node.js      | Required only for build (recommended ≥ 14) |

## Development notes

- `index.dev.php` is the editable source file containing HTML, CSS, JavaScript, and PHP logic.
- `build.js` compresses inline CSS and JavaScript and writes the output to `index.php`.
- The build process depends on `terser`, which is declared in `package.json`.

## Notes

- Keep playlist files and `index.php` in the same directory.
- `group-title` values are mapped to Chinese country names for better filtering.
- To add themes or customize the UI, edit `index.dev.php` and run `node build.js` again.

---

For Chinese documentation, see [README_CN.md](README_CN.md).
