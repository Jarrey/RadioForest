<?php
/**
 * Radio Forest - Configuration File
 *
 * Edit this file to customize the application without modifying index.php.
 * Changes take effect immediately — no rebuild required.
 */

// Directory where M3U playlist files are stored.
// Accepts an absolute path or a path relative to this file's location.
define('PLAYLIST_DIR', __DIR__ . '/playlists');

// Path for the parsed-station cache (speeds up page load when playlists haven't changed).
define('CACHE_FILE', __DIR__ . '/stations.cache.json');
