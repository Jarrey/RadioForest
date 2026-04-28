# Radio Forest 📻

A PHP-based online radio web player that scans local M3U playlist files in the project root, parses station metadata, and provides search, region filtering, theme switching, and live playback in the browser.

## Project structure

```text
radioweb/
├── index.dev.php          # Source file for development, contains editable PHP/HTML/CSS/JS
├── index.php              # Build output for deployment
├── build.js               # Build script that minifies CSS/JS and generates index.php
├── radioBrowserService.py # Helper module for radio-browser.info API requests
├── syncInternetRatio.py   # Generate M3U playlists from radio-browser station data
└── package.json           # Build dependency manifest
```

## Overview

- The app reads all `radio_*.m3u` files in the root directory.
- Use `radio_<region_code>.m3u` as the playlist file pattern.
- If no playlist files are found, the page will show an empty station list.

## Playlist generation scripts

This repository includes two helper Python scripts for fetching station data from radio-browser.info and generating playlist files that work with the web app.

- `radioBrowserService.py` — API client used to fetch station JSON data.
- `syncInternetRatio.py` — downloads country station lists and writes `radio_<code>.m3u` plus `radio.m3u`.

Usage example:

```bash
python syncInternetRatio.py CN,US --target-dir . --backup-dir ./backup
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

## Key features

- Multiple playlists: reads all `radio_*.m3u` files and merges stations
- Region filtering: supports multiple regions with flag icons
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
