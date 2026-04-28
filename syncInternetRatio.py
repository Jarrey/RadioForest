#!/usr/bin/python
# -*- coding: utf-8 -*-
#

import sys
import os
import os.path
import time
import zipfile
import argparse
from radioBrowserService import downloadRadiobrowserStationsByCountryAll

# ── defaults (used both as argparse defaults and documentation) ──────────────
DEFAULT_TARGET_DIR = "."
DEFAULT_BACKUP_DIR = "./backup"
TARGET_FILE = "radio_{tag}.m3u"
AGG_FILE    = "radio.m3u"

CONVERT_FORMAT = (
    "\r\n#RADIOBROWSERUUID:{id}\r\n"
    "#EXTINF:-1 tvg-id=\"{id}\" tvg-name=\"{name}\" tvg-logo=\"{logo}\" "
    "group-title=\"{group}\",{name} [{votes}]\r\n{url}\r\n"
)


# ── helpers ──────────────────────────────────────────────────────────────────

def backup(files, target_dir, backup_dir):
    stamp = time.strftime('%Y-%m-%d', time.localtime())
    output_file = os.path.join(backup_dir, f"radio_station_{stamp}.bak.zip")
    if not os.path.exists(backup_dir):
        os.makedirs(backup_dir)
    with zipfile.ZipFile(output_file, 'w') as zout:
        for file in files:
            input_file = os.path.join(target_dir, file)
            if not os.path.exists(input_file):
                continue
            with open(input_file, 'rb') as fin:
                zout.writestr(file, fin.read(), compress_type=zipfile.ZIP_DEFLATED)
    print(f"Backup written to {output_file}")


def write_file(file, content):
    with open(file, 'a', encoding='utf-8') as f:
        f.write(content)


def create_m3u_file(file):
    """Create (or overwrite) a UTF-8 BOM M3U file with the EXTM3U header."""
    with open(file, 'w', encoding='utf-8-sig') as f:
        f.write("#EXTM3U\r\n")


# ── argument parsing ─────────────────────────────────────────────────────────

def parse_args():
    parser = argparse.ArgumentParser(
        description=(
            "Download radio stations from radio-browser.info "
            "and generate M3U playlists per country."
        )
    )
    parser.add_argument(
        'countries',
        help=(
            "Comma-separated ISO 3166-1 alpha-2 country codes to download, "
            "e.g.  CN,US,GB"
        )
    )
    parser.add_argument(
        '--target-dir', default=DEFAULT_TARGET_DIR,
        metavar='DIR',
        help=f"Directory for generated M3U files (default: {DEFAULT_TARGET_DIR})"
    )
    parser.add_argument(
        '--backup-dir', default=DEFAULT_BACKUP_DIR,
        metavar='DIR',
        help=f"Directory for backup ZIP files (default: {DEFAULT_BACKUP_DIR})"
    )
    parser.add_argument(
        '--no-backup', action='store_true',
        help="Skip backup of existing M3U files"
    )
    parser.add_argument(
        '--show-broken', action='store_true',
        help=(
            "Include stations that failed their last connection check "
            "(default: broken stations are hidden)"
        )
    )
    parser.add_argument(
        '--page-size', type=int, default=500,
        metavar='N',
        help="Stations fetched per API request for pagination (default: 500)"
    )
    parser.add_argument(
        '--timeout', type=int, default=120,
        metavar='SEC',
        help="Socket timeout in seconds per API request (default: 120)"
    )
    parser.add_argument(
        '--proxy', action='store_true',
        help="Enable HTTP proxy for API requests using standard environment variables (default: disabled)"
    )
    return parser.parse_args()


# ── main ─────────────────────────────────────────────────────────────────────

def main():
    args = parse_args()

    tags       = [t.strip().upper() for t in args.countries.split(',') if t.strip()]
    target_dir = args.target_dir
    backup_dir = args.backup_dir
    hidebroken = not args.show_broken
    page_size  = args.page_size
    timeout    = args.timeout
    use_proxy  = args.proxy

    if not tags:
        print("No country codes provided. Exiting.", file=sys.stderr)
        sys.exit(1)

    if not os.path.exists(target_dir):
        os.makedirs(target_dir)

    if not args.no_backup:
        backup_files = [TARGET_FILE.format(tag=tag.lower()) for tag in tags] + [AGG_FILE]
        backup(backup_files, target_dir, backup_dir)

    aggfile = os.path.join(target_dir, AGG_FILE)
    create_m3u_file(aggfile)

    for tag in tags:
        print(f"\n{'=' * 60}")
        print(f"  Downloading stations for [{tag}]")
        print(f"{'=' * 60}")
        try:
            stations = downloadRadiobrowserStationsByCountryAll(
                tag, hidebroken=hidebroken, page_size=page_size,
                timeout=timeout, use_proxy=use_proxy
            )
            print(f"[{tag}] Total stations fetched: {len(stations)}")

            filename   = TARGET_FILE.format(tag=tag.lower())
            targetfile = os.path.join(target_dir, filename)
            os.makedirs(target_dir, exist_ok=True)
            create_m3u_file(targetfile)

            written = 0
            for station in stations:
                name = station.get("name", "")
                if not name or not name.strip():
                    continue
                url = station.get('url_resolved') or station.get('url', '')
                if not url:
                    continue
                stationline = CONVERT_FORMAT.format(
                    id=station['stationuuid'],
                    name=name.strip(),
                    logo=station.get('favicon', ''),
                    group=station.get('country', tag),
                    url=url,
                    votes=station.get('votes', 0),
                )
                write_file(targetfile, stationline)
                write_file(aggfile, stationline)
                written += 1

            print(f"[{tag}] Written {written} stations → {targetfile}")

        except Exception as exc:
            import traceback
            print(f"[{tag}] ERROR: {exc}", file=sys.stderr)
            traceback.print_exc()
            continue

    print(f"\nDone. Aggregate file: {aggfile}")


if __name__ == '__main__':
    main()
