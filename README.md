# Radio Forest 📻

A PHP-based online radio web player that scans local M3U playlist files in a configurable playlist directory, parses station metadata, and provides search, region filtering, theme switching, and live playback in the browser.

## Table of contents

- [Project structure](#project-structure)
- [Recent updates](#recent-updates)
- [Overview](#overview)
- [Multilingual UI](#multilingual-ui)
- [Playlist generation scripts](#playlist-generation-scripts)
- [Playlist format](#playlist-format)
- [Deployment](#deployment)
  - [Option 1: Deploy the built file](#option-1-deploy-the-built-file)
  - [Option 2: Build from source](#option-2-build-from-source)
- [Docker deployment](#docker-deployment)
  - [What is included in the Docker image](#what-is-included-in-the-docker-image)
  - [Files mounted from the host](#files-mounted-from-the-host)
  - [Prepare `.env`](#prepare-env)
  - [Build the image](#build-the-image)
  - [Run with Docker Compose](#run-with-docker-compose)
  - [Manual sync and cron scheduling](#manual-sync-and-cron-scheduling)
- [Key features](#key-features)
- [Requirements](#requirements)
- [Development notes](#development-notes)
- [Notes](#notes)

## Project structure

```text
radioweb/
├── index.dev.php          # Source file for development, contains editable PHP/HTML/CSS/JS
├── index.php              # Build output for deployment
├── build.js               # Build script that minifies CSS/JS and generates index.php
├── config.php             # Optional runtime configuration for playlist directory and cache path
├── scripts/               # Playlist synchronization scripts
│   ├── radioBrowserService.py
│   └── syncInternetRatio.py
├── lang/                  # UI translation dictionaries
└── package.json           # Build dependency manifest
```

## Recent updates

- Switched the UI translation system to use English label keys for consistent locale dictionaries.
- Fixed language switching so the total station count and player status text update correctly after changing locale.
- Translation dictionaries now cover regions, genres, provinces, and player interface labels consistently.

## Overview

- The app reads all `radio_*.m3u` files from a configurable playlist directory (`config.php`) or the default `./playlists` folder.
- Use `radio_<region_code>.m3u` as the playlist file pattern.
- If no playlist files are found, the page will show an empty station list.
- The UI supports multiple languages and automatically selects the browser locale by default.

## Runtime configuration

You can optionally create `config.php` in the project root to override the default playlist directory and cache file path without editing `index.php`:

```php
define('PLAYLIST_DIR', __DIR__ . '/playlists');
define('CACHE_FILE', __DIR__ . '/stations.cache.json');
```

If `config.php` is absent, the app defaults to `./playlists` and `./stations.cache.json`.

## Multilingual UI

- Language dictionaries are stored under `lang/`.
- Supported languages: Chinese (Simplified), English, Spanish, French, German, Italian, Japanese, Korean, Russian.
- Users can switch languages from the top-right selector and see country flags for each option.
- Category labels such as region/type filters are also translated.

## Playlist generation scripts

This repository includes two helper Python scripts for fetching station data from radio-browser.info and generating playlist files that work with the web app.

- `scripts/radioBrowserService.py` — API client used to fetch station JSON data.
- `scripts/syncInternetRatio.py` — downloads country station lists and writes `radio_<code>.m3u` plus `radio.m3u`.

Usage example:

```bash
python scripts/syncInternetRatio.py CN,US --target-dir . --backup-dir ./backup
```

Proxy is disabled by default; pass `--proxy` to enable HTTP proxy support using standard `HTTP_PROXY` / `HTTPS_PROXY` environment variables.

You can run the script from a scheduled task to keep playlists updated automatically. For example, use `cron` on Linux/macOS or Task Scheduler on Windows to run it at a fixed time every day.

### syncInternetRatio.py arguments

- `countries` (required)
  - Comma-separated ISO 3166-1 alpha-2 country codes, e.g. `CN,US,GB`.
- `--target-dir DIR`
  - Directory to write generated M3U files. Default: `.`
- `--backup-dir DIR`
  - Directory to store ZIP backups of existing playlist files. Default: `./backup`
- `--no-backup`
  - Skip backup of existing `radio_<code>.m3u` and `radio.m3u` files.
- `--show-broken`
  - Include stations that failed their last check. By default, broken stations are excluded.
- `--page-size N`
  - Number of stations fetched per API request page. Default: `500`.
- `--timeout SEC`
  - Socket timeout in seconds for each API request. Default: `120`.
- `--proxy`
  - Enable HTTP proxy for API requests using the standard `HTTP_PROXY` / `HTTPS_PROXY` environment variables.

## Playlist format

Example M3U content:

```m3u
#EXTM3U
#EXTINF:-1 tvg-name="CNR Radio" tvg-logo="https://example.com/logo.png" group-title="China",CNR Radio
http://lhttp.cnr.cn/live/zgzs/64k.mp3
```

Recommended file names:

| File name            | Meaning      |
| -------------------- | ------------ |
| `radio_cn.m3u`       | China radio  |
| `radio_us.m3u`       | USA radio    |
| `radio_jp.m3u`       | Japan radio  |
| `radio_<region>.m3u` | Region radio |

Supported tags:

- `tvg-name` — station name
- `tvg-logo` — station logo URL
- `group-title` — country/group name for filtering

## Deployment

### Option 1: Deploy the built file

Upload `index.php` and your `radio_*.m3u` files to a PHP-capable web server. `node_modules/` and `package-lock.json` are not required.

### Option 2: Build from source

Edit `index.dev.php`, then run:

```bash
npm install
node build.js
```

The build script minifies the inlined CSS and JavaScript, preserves PHP code blocks, and writes the result to `index.php`.

## Docker deployment

This project is designed to deploy from the prebuilt GHCR package. The `docker/docker-compose.yml` file already uses `ghcr.io/jarrey/radioforest:latest`, so you do not need to build a local image.

### Prepare `.env`

Copy the sample environment file and customize it before deployment:

```powershell
cd docker
copy .env.sample .env
```

Then edit `docker/.env` and set values such as:

- `HTTP_PORT` - public port exposed by the container
- `SYNC_COUNTRIES` - country codes for playlist sync
- `SYNC_TARGET_DIR` - target directory for generated playlists inside the container
- `SYNC_BACKUP_DIR` - backup directory inside the container
- `SYNC_CRON` - optional cron expression for scheduled sync

If you do not want scheduled sync, leave `SYNC_CRON` empty.

### Run with Docker Compose

Start the service using the GHCR image:

```bash
cd docker
docker compose up -d
```

Then open your browser and visit:

```text
http://localhost:18882
```

To stop the service:

```bash
docker compose down
```

### Manual sync and cron scheduling

To run the sync script manually inside the container, run either:

```bash
cd docker
docker compose exec app sh -c "./sync.sh"
```

or from the repository root:

```bash
docker compose -f docker/docker-compose.yml exec app sh -c "./sync.sh"
```

If `SYNC_CRON` is configured in `.env`, the container will start `crond` and run the sync job on the schedule you specify. Example:

```text
SYNC_CRON=30 1 * * *
```

This runs sync every day at 03:00 and writes output to:

- `./logs/cron.log`

Manual sync output is written to:

- `./logs/sync.log`

### Useful Docker notes

- The image does not include local `radio_*.m3u` files by design.
- Use host mounts to keep playlist files, backups, and logs persistent.
- You can inspect the container logs with either:

```bash
cd docker
docker compose logs -f
```

or from the repository root:

```bash
docker compose -f docker/docker-compose.yml logs -f
```

- To rebuild after changing Docker-related files, run either:

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
- Multi-language UI: browser locale default + manual language switcher with flags
- Keyword search: filters station names in real time
- Theme switching: 12 color themes available
- Fullscreen player: animated waveform, playback status, and clock display
- Responsive design: works on mobile, tablet, and desktop

## Requirements

| Component    | Requirement                              |
| ------------ | ---------------------------------------- |
| PHP          | 5.6+                                     |
| Python       | 3.x                                      |
| requests     | Python package for HTTP API calls        |
| Web server   | Apache / Nginx / other PHP-capable server |
| Browser      | Modern browser with HTML5 `<audio>` support |
| Node.js      | Required only for build (recommended ≥ 14) |

## Development notes

- `index.dev.php` is the editable source file containing HTML, CSS, JavaScript, and PHP logic.
- `build.js` minifies inline CSS and JavaScript before writing `index.php`.
- The build process uses `terser`, which is declared in `package.json`.

## Notes

- Keep playlist files and `index.php` in the same directory.
- `group-title` values are mapped to Chinese country names for better filtering.
- To add themes or customize UI, edit `index.dev.php` and run `node build.js` again.

---

For Chinese documentation, see `README_cn.md`.
