# Radio Forest 📻

A PHP-based online radio web player that scans local M3U playlist files in the project root, parses station metadata, and provides search, region filtering, theme switching, and live playback in the browser.

## Project structure

```text
radioweb/
├── index.dev.php    # Source file for development, contains editable PHP/HTML/CSS/JS
├── index.php        # Build output for deployment
├── build.js         # Build script that minifies CSS/JS and generates index.php
└── package.json     # Build dependency manifest
```

## Overview

- The app reads all `radio*.m3u` files in the root directory.
- It is recommended to use `radio_<region_code>.m3u` naming.
- If no playlist files are found, the page will show an empty station list.
- Multiple playlists are merged automatically.

## Playlist format

Example M3U content:

```m3u
#EXTM3U
#EXTINF:-1 tvg-name="CNR Radio" tvg-logo="https://example.com/logo.png" group-title="China",CNR Radio
http://lhttp.cnr.cn/live/zgzs/64k.mp3
```

Recommended file names:

| File name        | Meaning      |
| ---------------- | ------------ |
| `radio_cn.m3u`   | China radio  |
| `radio_us.m3u`   | USA radio    |
| `radio_jp.m3u`   | Japan radio  |
| `radio.m3u`     | Global radio |

Supported tags:

- `tvg-name` — station name
- `tvg-logo` — station logo URL
- `group-title` — country/group name for filtering

## Deployment

### Option 1: Deploy the built file

Upload `index.php` and `radio_*.m3u` to a PHP-capable web server. `node_modules/` and `package-lock.json` are not required.

### Option 2: Build from source

Edit `index.dev.php`, then run:

```bash
npm install
node build.js
```

The build script minifies the inlined CSS and JavaScript, preserves PHP code blocks, and writes the result to `index.php`.

## Key features

- Playlist merge: automatically reads and merges all `radio*.m3u` files
- Region filtering: supports multiple regions with flag icons
- Keyword search: filters station names in real time
- Theme switching: 12 color themes available
- Fullscreen player: animated waveform, playback status, and clock display
- Responsive design: works on mobile, tablet, and desktop

## Requirements

| Component    | Requirement                              |
| ------------ | ---------------------------------------- |
| PHP          | 5.6+                                     |
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
