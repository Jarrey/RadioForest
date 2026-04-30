#!/usr/bin/python
# -*- coding: utf-8 -*-
#

import socket
import random
import urllib.parse
import json
import requests

APP_NAME = "RadioBrowserSync/1.0"
DEFAULT_PAGE_SIZE = 500

_cached_base_urls = None


def get_radiobrowser_base_urls():
    """
    Get all base urls of all currently available radiobrowser servers
    via DNS lookup of all.api.radio-browser.info followed by reverse-DNS
    to obtain friendly hostnames.  Result is cached after the first call.

    Returns:
        list: sorted list of HTTPS base URL strings
    """
    global _cached_base_urls
    if _cached_base_urls is not None:
        return _cached_base_urls
    hosts = []
    ips = socket.getaddrinfo('all.api.radio-browser.info', 80, 0, 0, socket.IPPROTO_TCP)
    for ip_tuple in ips:
        ip = str(ip_tuple[4][0])
        host_addr = socket.gethostbyaddr(ip)
        if host_addr[0] not in hosts:
            hosts.append(host_addr[0])
    hosts.sort()
    _cached_base_urls = ["https://" + h for h in hosts]
    return _cached_base_urls


def downloadUri(uri, params=None, timeout=30, use_proxy=False):
    """
    Issue a GET request to *uri*, appending *params* as a query string.

    Args:
        uri (str): Full URL to request.
        params (dict, optional): Query parameters to encode into the URL.
        timeout (int): Socket timeout in seconds.
        use_proxy (bool): Whether to use environment proxy settings (default: False).

    Returns:
        bytes: Response body.

    Raises:
        requests.exceptions.RequestException: On connection / HTTP errors.
    """
    if params:
        query_string = urllib.parse.urlencode(params)
        full_uri = f"{uri}?{query_string}"
    else:
        full_uri = uri

    proxies = None if use_proxy else {}
    print(f'  GET {full_uri}  (proxy={use_proxy})')
    response = requests.get(
        full_uri,
        headers={"User-Agent": APP_NAME, "Accept": "application/json"},
        proxies=proxies,
        timeout=timeout,
    )
    response.raise_for_status()
    return response.content


def downloadRadiobrowser(path, params=None, timeout=30, use_proxy=False):
    """
    Download from a relative API path using a randomly chosen available server.
    Retries with other servers on failure.

    Args:
        path (str): Relative path, e.g. '/json/stations/bycountrycodeexact/CN'.
        params (dict, optional): Query parameters to append.
        timeout (int): Per-request socket timeout in seconds.
        use_proxy (bool): Whether to use environment proxy settings (default: False).

    Returns:
        bytes or None: Response body, or None if all servers failed.
    """
    servers = get_radiobrowser_base_urls()
    random.shuffle(servers)
    for i, server_base in enumerate(servers):
        print(f'[attempt {i + 1}/{len(servers)}] server: {server_base}')
        try:
            return downloadUri(server_base + path, params, timeout, use_proxy)
        except Exception as e:
            print(f"  Failed: {e}")
    print("All servers failed.")
    return None


def downloadRadiobrowserStats():
    """Fetch server statistics. Returns a dict."""
    data = downloadRadiobrowser("/json/stats")
    if data is None:
        return {}
    return json.loads(data.decode('utf-8'))


def downloadRadiobrowserStationsByCountry(countrycode, hidebroken=True,
                                          limit=DEFAULT_PAGE_SIZE, offset=0,
                                          timeout=60, use_proxy=False):
    """
    Fetch one page of stations for a country code.

    Args:
        countrycode (str): ISO 3166-1 alpha-2 code, e.g. 'CN' or 'US'.
        hidebroken (bool): Exclude stations that failed their last check.
        limit (int): Maximum number of results for this page.
        offset (int): Starting position for pagination.
        timeout (int): Socket timeout in seconds per request.
        use_proxy (bool): Whether to use environment proxy settings (default: False).

    Returns:
        list: List of station dicts (may be empty).
    """
    params = {
        'limit': limit,
        'offset': offset,
        'hidebroken': 'true' if hidebroken else 'false',
        'order': 'name',
    }
    path = f'/json/stations/bycountrycodeexact/{countrycode}'
    data = downloadRadiobrowser(path, params, timeout=timeout, use_proxy=use_proxy)
    if data is None:
        return []
    return json.loads(data.decode('utf-8'))


def downloadRadiobrowserStationsByCountryAll(countrycode, hidebroken=True,
                                              page_size=DEFAULT_PAGE_SIZE,
                                              timeout=60, use_proxy=False):
    """
    Fetch ALL stations for a country using automatic pagination.

    Repeatedly calls the API with increasing offsets until a page returns
    fewer results than *page_size*, indicating the last page.

    Args:
        countrycode (str): ISO 3166-1 alpha-2 code, e.g. 'CN' or 'US'.
        hidebroken (bool): Exclude stations that failed their last check.
        page_size (int): Number of stations requested per API call.
        timeout (int): Socket timeout in seconds per request.
        use_proxy (bool): Whether to use environment proxy settings (default: False).

    Returns:
        list: Complete deduplicated list of station dicts.
    """
    all_stations = []
    seen_uuids = set()
    offset = 0
    while True:
        print(f'[{countrycode}] page offset={offset}, limit={page_size}')
        page = downloadRadiobrowserStationsByCountry(
            countrycode, hidebroken=hidebroken, limit=page_size,
            offset=offset, timeout=timeout, use_proxy=use_proxy
        )
        if not page:
            break
        for station in page:
            uuid = station.get('stationuuid')
            if uuid and uuid not in seen_uuids:
                seen_uuids.add(uuid)
                all_stations.append(station)
        print(f'[{countrycode}] page returned {len(page)}, total so far: {len(all_stations)}')
        if len(page) < page_size:
            break
        offset += page_size
    return all_stations


def downloadRadiobrowserStationsByName(name, hidebroken=True,
                                        limit=DEFAULT_PAGE_SIZE, offset=0,
                                        use_proxy=False):
    """
    Search stations by name (partial match), sorted by votes descending.

    Args:
        name (str): Station name search term.
        hidebroken (bool): Exclude broken stations.
        limit (int): Maximum number of results.
        offset (int): Pagination offset.
        use_proxy (bool): Whether to use environment proxy settings (default: False).

    Returns:
        list: List of matching station dicts.
    """
    params = {
        'name': name,
        'hidebroken': 'true' if hidebroken else 'false',
        'limit': limit,
        'offset': offset,
        'order': 'votes',
        'reverse': 'true',
    }
    data = downloadRadiobrowser("/json/stations/search", params, use_proxy=use_proxy)
    if data is None:
        return []
    return json.loads(data.decode('utf-8'))
