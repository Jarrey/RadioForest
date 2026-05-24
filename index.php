<?php
// 防止大数据量时脚本超时
set_time_limit(0);
/**
 * 解析 M3U 播放列表文件，返回电台数组
 * 每个电台包含 name、logo、url、group 字段
 */
function parseM3U($file) {
    $stations = [];
    $content = file_get_contents($file);
    if ($content === false) return $stations;
    $lines = explode("\n", $content);
    $lineCount = count($lines); // 缓存行数，避免循环内重复计算

    $i = 0;
    while ($i < $lineCount) {
        $line = trim($lines[$i]);

        if (strpos($line, '#EXTINF:') === 0) {
            $name = '';
            $logo = '';
            $group = '';

            if (preg_match('/tvg-name="([^"]*)"/', $line, $matches)) {
                $name = $matches[1];
            }
            if (empty($name) && preg_match('/#EXTINF:[^,]*,(.+)/', $line, $matches)) {
                $name = trim($matches[1]);
            }
            if (preg_match('/tvg-logo="([^"]*)"/', $line, $matches)) {
                $logo = $matches[1];
            }
            if (preg_match('/group-title="([^"]*)"/', $line, $matches)) {
                $group = $matches[1];
            }

            $i++;
            while ($i < $lineCount && trim($lines[$i]) === '') {
                $i++;
            }

            if ($i < $lineCount) {
                $url = trim($lines[$i]);
                if (!empty($url) && (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0)) {
                    $stations[] = [
                        'name' => $name ?: '未知电台',
                        'logo' => $logo,
                        'url' => $url,
                        'group' => $group
                    ];
                }
            }
        }
        $i++;
    }

    return $stations;
}

/**
 * 将 M3U group-title 字段转换为中文国家名
 * 使用 static 变量缓存小写键映射，避免每次调用重复构建
 */
function getCountryName($group) {
    static $lowerMap = null;
    if ($lowerMap === null) {
        // 数据源来自 config.php 中的 GROUP_TITLE_MAP 常量
        $lowerMap = [];
        foreach (GROUP_TITLE_MAP as $k => $v) $lowerMap[strtolower($k)] = $v;
    }
    $key = strtolower(trim($group));
    return $lowerMap[$key] ?? ($group ?: '其他');
}

function getRadioFilesState(array $files) {
    $state = [];
    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }
        $state[] = [
            'file'  => basename($file),
            'mtime' => filemtime($file),
            'size'  => filesize($file),
        ];
    }
    return $state;
}

function loadStationCache(string $cacheFile, array $files) {
    if (!is_file($cacheFile)) {
        return null;
    }
    $json = file_get_contents($cacheFile);
    if ($json === false) {
        return null;
    }
    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data['file_state'], $data['stations']) || !is_array($data['file_state']) || !is_array($data['stations'])) {
        return null;
    }
    $currentState = getRadioFilesState($files);
    if (count($currentState) !== count($data['file_state'])) {
        return null;
    }
    foreach ($currentState as $index => $item) {
        if (!isset($data['file_state'][$index]) || $data['file_state'][$index] !== $item) {
            return null;
        }
    }
    return $data['stations'];
}

function saveStationCache(string $cacheFile, array $files, array $stations) {
    $data = [
        'file_state' => getRadioFilesState($files),
        'stations'   => $stations,
    ];
    $tmpFile = $cacheFile . '.tmp';
    if (file_put_contents($tmpFile, json_encode($data, JSON_UNESCAPED_UNICODE)) === false) {
        return false;
    }
    if (!rename($tmpFile, $cacheFile)) {
        @unlink($tmpFile);
        return false;
    }
    return true;
}

// Load external configuration; fall back to built-in defaults if config.php is absent
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}
defined('PLAYLIST_DIR')    || define('PLAYLIST_DIR',    __DIR__ . '/playlists');
defined('CACHE_FILE')      || define('CACHE_FILE',      __DIR__ . '/stations.cache.json');
defined('REGION_NAMES')    || define('REGION_NAMES',    ['' => '全球']);
defined('GROUP_TITLE_MAP') || define('GROUP_TITLE_MAP', []);

$dir = PLAYLIST_DIR;
$files = glob($dir . '/radio_*.m3u') ?: [];
$cacheFile = CACHE_FILE;
$allStations = [];
$countries = [];

// 国别列表来自 config.php 中的 REGION_NAMES 常量
$regionNames = REGION_NAMES;

$cached = loadStationCache($cacheFile, $files);
if ($cached !== null) {
    $allStations = $cached;
} else {
    foreach ($files as $file) {
        $stations = parseM3U($file);
        $basename = basename($file, '.m3u');
        $region = $basename === 'radio' ? '' : str_replace('radio_', '', $basename);
        $regionName = REGION_NAMES[$region] ?? $region;

        foreach ($stations as $s) {
            $allStations[] = [
                'name'    => $s['name'],
                'logo'    => $s['logo'],
                'url'     => $s['url'],
                'region'  => $regionName,
                'country' => getCountryName($s['group']),
            ];
        }
    }
    if (!saveStationCache($cacheFile, $files, $allStations)) {
        error_log('RadioForest: Failed to save station cache to ' . $cacheFile);
    }
}

// 无论来自缓存还是实时解析，都重建 $countries 统计
foreach ($allStations as $s) {
    if (!isset($countries[$s['region']])) $countries[$s['region']] = 0;
    $countries[$s['region']]++;
}

$totalCount = count($allStations);

// ─── JSON API 端点：流式输出 NDJSON（每行一条电台），支持渐进加载 ──────────────
if (isset($_GET['action']) && $_GET['action'] === 'stations') {
    // 关闭 gzip 压缩（压缩会阻止流式推送）
    ini_set('zlib.output_compression', 'Off');
    // 清除 PHP 输出缓冲，让内容尽快发往浏览器
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/x-ndjson; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Accel-Buffering: no'); // 禁止 Nginx 缓冲
    $i = 0;
    foreach ($allStations as $station) {
        echo json_encode($station, JSON_UNESCAPED_UNICODE) . "\n";
        // 每 500 条强制刷新一次，确保数据持续送达浏览器
        if (++$i % 500 === 0) {
            flush();
        }
    }
    flush();
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="green">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="appTitle">电台森林</title>

    <script>!function(){const e=localStorage.getItem("theme")||"green";document.documentElement.setAttribute("data-theme",e)}();</script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <link rel="icon" type="image/svg+xml" href="radio-icon.svg">
    <style>:root{--primary:#22c55e;--primary-dim:#166534;--bg:#121212;--bg-card:#1a1a1a;--bg-input:#1a1a1a;--border:#333;--border-light:#444;--text:#e8e8e8;--text-dim:#777;--text-dimmer:#555;--tag-bg:#252525;--type-bg:#1e3a5f;--type-text:#60a5fa;--player-bg:linear-gradient(135deg,#0f1a0f 0%,#0a150a 100%);--player-border:#22c55e;--player-shadow:rgba(34,197,94,0.15)}[data-theme="orange"]{--primary:#f97316;--primary-dim:#9a3412;--bg:#121212;--bg-card:#1a1815;--bg-input:#1a1815;--border:#333;--border-light:#443830;--text:#e8e8e8;--text-dim:#777;--text-dimmer:#555;--tag-bg:#2a231a;--type-bg:#4a300a;--type-text:#fbbf24;--player-bg:linear-gradient(135deg,#1a1408 0%,#0f0a04 100%);--player-border:#f97316;--player-shadow:rgba(249,115,22,0.15)}[data-theme="red"]{--primary:#dc2626;--primary-dim:#991b1b;--bg:#121212;--bg-card:#1a1515;--bg-input:#1a1515;--border:#333;--border-light:#442828;--text:#e8e8e8;--text-dim:#777;--text-dimmer:#555;--tag-bg:#2a1a1a;--type-bg:#4a0a0a;--type-text:#f87171;--player-bg:linear-gradient(135deg,#1a0808 0%,#0f0404 100%);--player-border:#dc2626;--player-shadow:rgba(220,38,38,0.15)}[data-theme="blue"]{--primary:#3b82f6;--primary-dim:#1d4ed8;--bg:#121212;--bg-card:#151a1f;--bg-input:#151a1f;--border:#333;--border-light:#2a3544;--text:#e8e8e8;--text-dim:#777;--text-dimmer:#555;--tag-bg:#1a2530;--type-bg:#0a1a3a;--type-text:#60a5fa;--player-bg:linear-gradient(135deg,#0a1525 0%,#050a10 100%);--player-border:#3b82f6;--player-shadow:rgba(59,130,246,0.15)}[data-theme="purple"]{--primary:#a855f7;--primary-dim:#7e22ce;--bg:#121212;--bg-card:#1a1520;--bg-input:#1a1520;--border:#333;--border-light:#3a2a44;--text:#e8e8e8;--text-dim:#777;--text-dimmer:#555;--tag-bg:#251a2a;--type-bg:#2a0a3a;--type-text:#c084fc;--player-bg:linear-gradient(135deg,#150a1a 0%,#0a0510 100%);--player-border:#a855f7;--player-shadow:rgba(168,85,247,0.15)}[data-theme="teal"]{--primary:#14b8a6;--primary-dim:#0f766e;--bg:#121212;--bg-card:#152020;--bg-input:#152020;--border:#333;--border-light:#1a3030;--text:#e8e8e8;--text-dim:#777;--text-dimmer:#555;--tag-bg:#1a2828;--type-bg:#0a2020;--type-text:#2dd4bf;--player-bg:linear-gradient(135deg,#0a1515 0%,#050a0a 100%);--player-border:#14b8a6;--player-shadow:rgba(20,184,166,0.15)}[data-theme="cyan"]{--primary:#06b6d4;--primary-dim:#0891b2;--bg:#121212;--bg-card:#151d21;--bg-input:#151d21;--border:#333;--border-light:#1a2a30;--text:#e8e8e8;--text-dim:#777;--text-dimmer:#555;--tag-bg:#1a252a;--type-bg:#0a1a20;--type-text:#22d3ee;--player-bg:linear-gradient(135deg,#081515 0%,#040a0a 100%);--player-border:#06b6d4;--player-shadow:rgba(6,182,212,0.15)}[data-theme="amber"]{--primary:#f59e0b;--primary-dim:#d97706;--bg:#121212;--bg-card:#1f1a12;--bg-input:#1f1a12;--border:#333;--border-light:#3a3020;--text:#e8e8e8;--text-dim:#777;--text-dimmer:#555;--tag-bg:#2a2015;--type-bg:#201a08;--type-text:#fbbf24;--player-bg:linear-gradient(135deg,#150f08 0%,#0a0804 100%);--player-border:#f59e0b;--player-shadow:rgba(245,158,11,0.15)}[data-theme="rose"]{--primary:#f43f5e;--primary-dim:#e11d48;--bg:#121212;--bg-card:#1f1518;--bg-input:#1f1518;--border:#333;--border-light:#3a2025;--text:#e8e8e8;--text-dim:#777;--text-dimmer:#555;--tag-bg:#2a181d;--type-bg:#200a10;--type-text:#fb7185;--player-bg:linear-gradient(135deg,#15080c 0%,#0a0406 100%);--player-border:#f43f5e;--player-shadow:rgba(244,63,94,0.15)}[data-theme="pink"]{--primary:#ec4899;--primary-dim:#db2777;--bg:#121212;--bg-card:#1f151d;--bg-input:#1f151d;--border:#333;--border-light:#3a2030;--text:#e8e8e8;--text-dim:#777;--text-dimmer:#555;--tag-bg:#2a1822;--type-bg:#200a15;--type-text:#f472b6;--player-bg:linear-gradient(135deg,#150a10 0%,#0a0508 100%);--player-border:#ec4899;--player-shadow:rgba(236,72,153,0.15)}[data-theme="indigo"]{--primary:#6366f1;--primary-dim:#4f46e5;--bg:#121212;--bg-card:#161822;--bg-input:#161822;--border:#333;--border-light:#202030;--text:#e8e8e8;--text-dim:#777;--text-dimmer:#555;--tag-bg:#1a1828;--type-bg:#0a0a20;--type-text:#818cf8;--player-bg:linear-gradient(135deg,#0a0a15 0%,#050508 100%);--player-border:#6366f1;--player-shadow:rgba(99,102,241,0.15)}[data-theme="black"]{--primary:#ffffff;--primary-dim:#888888;--bg:#000000;--bg-card:#0a0a0a;--bg-input:#0a0a0a;--border:#222;--border-light:#333;--text:#ffffff;--text-dim:#888;--text-dimmer:#555;--tag-bg:#111;--type-bg:#222;--type-text:#ccc;--player-bg:linear-gradient(135deg,#111 0%,#000 100%);--player-border:#333;--player-shadow:rgba(255,255,255,0.05)}[data-theme="bw"]{--primary:#ffffff;--primary-dim:#cccccc;--bg:#000000;--bg-card:#000000;--bg-input:#000000;--border:#ffffff;--border-light:#ffffff;--text:#ffffff;--text-dim:#cccccc;--text-dimmer:#999999;--tag-bg:#000000;--type-bg:#000000;--type-text:#ffffff;--player-bg:#000000;--player-border:#ffffff;--player-shadow:rgba(255,255,255,0.2)}[data-theme="grayscale"]{--primary:#888888;--primary-dim:#666666;--bg:#1a1a1a;--bg-card:#222222;--bg-input:#222222;--border:#444;--border-light:#555;--text:#e0e0e0;--text-dim:#999;--text-dimmer:#666;--tag-bg:#2a2a2a;--type-bg:#333;--type-text:#aaa;--player-bg:linear-gradient(135deg,#2a2a2a 0%,#1a1a1a 100%);--player-border:#666;--player-shadow:rgba(136,136,136,0.15)}*{margin:0;padding:0;box-sizing:border-box}html,body{min-height:100%;overflow-x:hidden;max-width:100vw}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--text);transition:background 0.3s,color 0.3s}.container{max-width:1400px;width:100%;margin:0 auto;padding:24px 16px;overflow-x:hidden}header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding:0 8px}.header-left{display:flex;align-items:center;gap:12px}.logo{filter:drop-shadow(0 0 8px var(--player-shadow))}.header-right{display:flex;align-items:center;gap:8px}.picker-wrap{position:relative}.picker-btn{display:flex;align-items:center;gap:5px;padding:6px 10px;background:var(--bg-card);border:1px solid var(--border);border-radius:20px;color:var(--text);font-size:12px;cursor:pointer;outline:none;transition:border-color 0.2s,box-shadow 0.2s,background 0.2s;white-space:nowrap;user-select:none;line-height:1}.picker-btn:hover{border-color:var(--primary);background:color-mix(in srgb,var(--primary) 8%,var(--bg-card))}.picker-wrap.open>.picker-btn{border-color:var(--primary);box-shadow:0 0 0 2px color-mix(in srgb,var(--primary) 22%,transparent)}.picker-dot{width:11px;height:11px;border-radius:50%;flex-shrink:0;border:1.5px solid rgba(255,255,255,0.2)}.picker-flag{width:18px;height:13px;border-radius:2px;object-fit:cover;flex-shrink:0}.picker-caret{width:8px;height:5px;flex-shrink:0;fill:var(--text-dim);transition:transform 0.2s}.picker-wrap.open .picker-caret{transform:rotate(180deg)}.picker-label{font-size:12px}.picker-panel{position:absolute;top:calc(100%+6px);right:0;background:var(--bg-card);border:1px solid var(--border-light);border-radius:12px;box-shadow:0 8px 28px rgba(0,0,0,0.5);z-index:2000;overflow:hidden;visibility:hidden;opacity:0;pointer-events:none;transform:translateY(-6px);transition:opacity 0.15s,transform 0.15s,visibility 0s 0.15s}.picker-wrap.open .picker-panel{visibility:visible;opacity:1;pointer-events:all;transform:translateY(0);transition:opacity 0.15s,transform 0.15s}#themePanel{padding:8px;width:210px;display:grid;grid-template-columns:1fr 1fr;gap:3px}.theme-item{display:flex;align-items:center;gap:7px;padding:6px 8px;border-radius:8px;cursor:pointer;transition:background 0.15s;font-size:12px;color:var(--text);border:1px solid transparent}.theme-item:hover{background:color-mix(in srgb,var(--primary) 12%,transparent)}.theme-item.active{background:color-mix(in srgb,var(--primary) 18%,transparent);border-color:color-mix(in srgb,var(--primary) 55%,transparent)}.theme-item-dot{width:13px;height:13px;border-radius:50%;flex-shrink:0;border:1.5px solid rgba(255,255,255,0.18)}#langPanel{padding:6px;min-width:152px}.lang-item{display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:8px;cursor:pointer;transition:background 0.15s;font-size:13px;color:var(--text);border:1px solid transparent;white-space:nowrap}.lang-item:hover{background:color-mix(in srgb,var(--primary) 12%,transparent)}.lang-item.active{background:color-mix(in srgb,var(--primary) 18%,transparent);border-color:color-mix(in srgb,var(--primary) 55%,transparent)}.lang-item img{width:20px;height:14px;border-radius:2px;object-fit:cover;flex-shrink:0}.github-link{display:flex;align-items:center;color:var(--text-dim);text-decoration:none;flex-shrink:0;transition:color 0.2s}.github-link:hover{color:var(--primary)}.github-link svg{width:20px;height:20px;fill:currentColor;flex-shrink:0}@media (max-width:500px){.picker-label{display:none}.picker-btn{padding:7px 9px;gap:4px}.github-link{display:none}}h1{font-size:22px;font-weight:200;color:#fff;letter-spacing:3px;margin-bottom:4px}.subtitle{color:var(--text-dim);font-size:12px}#topProgressBar{position:fixed;top:0;left:0;right:0;height:3px;z-index:9999;pointer-events:none}#topProgressFill{height:100%;width:0%;background:linear-gradient(90deg,var(--primary),color-mix(in srgb,var(--primary) 60%,#fff));border-radius:0 2px 2px 0;transition:width 0.3s ease;box-shadow:0 0 8px color-mix(in srgb,var(--primary) 70%,transparent)}#topProgressFill.done{width:100%!important;transition:width 0.2s ease,opacity 0.5s ease 0.3s;opacity:0}.search-row{display:flex;align-items:center;gap:10px;max-width:520px;margin:0 auto 16px}.search-box{flex:1;min-width:0}.search-box input{width:100%;padding:12px 16px;border-radius:24px;border:1px solid var(--border);background:var(--bg-input);color:var(--text);font-size:14px;outline:none;transition:all 0.2s}.search-box input:focus{border-color:var(--primary);background:var(--bg-card)}.search-box input::placeholder{color:var(--text-dimmer)}.filter-section{margin-bottom:16px}.filter-header{display:flex;align-items:center;gap:8px;margin-bottom:8px;padding:6px 8px;cursor:pointer;user-select:none;border-radius:8px;transition:background 0.2s}.filter-header:hover{background:var(--bg-card)}.filter-header h3{font-size:14px;font-weight:500;color:var(--text);margin:0}.toggle-icon{font-size:10px;color:var(--primary);transition:transform 0.3s ease;display:inline-block;width:12px;text-align:center}.toggle-icon.collapsed{transform:rotate(-90deg)}.filter-content{overflow:hidden;transition:max-height 0.4s ease,opacity 0.3s ease;max-height:2000px;opacity:1}.filter-content.collapsed{max-height:0;opacity:0}.regions{display:flex;justify-content:center;flex-wrap:wrap;gap:6px}.region-btn{padding:5px 10px;border-radius:16px;border:1px solid var(--border);background:var(--bg-card);color:var(--text);font-size:13px;cursor:pointer;transition:all 0.15s;font-weight:400;display:inline-flex;align-items:center;gap:4px}.region-flag{width:18px;height:12px;object-fit:cover;border-radius:2px;flex-shrink:0}.region-btn:hover{background:var(--border-light);color:var(--text)}.region-btn.active{background:var(--primary);color:#000;border-color:var(--primary);font-weight:500}.types{display:flex;justify-content:center;flex-wrap:wrap;gap:5px;padding:10px 12px 6px;border:none;border-radius:12px;background:color-mix(in srgb,var(--primary) 15%,transparent)}.type-btn{padding:4px 10px;border-radius:12px;border:1px solid var(--border);background:var(--bg-card);color:var(--text);font-size:11px;cursor:pointer;transition:all 0.15s;font-weight:400}.type-btn:hover{background:var(--border-light);color:var(--text)}.type-btn.active{background:var(--primary);color:#000;border-color:var(--primary);font-weight:500}.player-bar{background:var(--player-bg);border-radius:20px;padding:24px 32px;margin-bottom:20px;display:flex;align-items:center;gap:24px;border:1px solid var(--player-border);box-shadow:0 0 30px var(--player-shadow),0 4px 20px rgba(0,0,0,0.4);position:relative;overflow:hidden;transition:all 0.3s;flex-wrap:wrap;cursor:pointer}.player-bar::before{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,var(--player-shadow),transparent);animation:shimmer 3s infinite}@keyframes shimmer{0%{left:-100%}100%{left:100%}}.player-logo{width:56px;height:56px;border-radius:12px;object-fit:cover;background:var(--bg-card);flex-shrink:0;border:2px solid var(--primary);box-shadow:0 0 12px var(--player-shadow)}.player-logo.placeholder{display:flex;align-items:center;justify-content:center;background:var(--bg-card);border:2px solid var(--primary)}.player-info{flex:1;min-width:0}.player-title{font-size:17px;font-weight:500;color:var(--text);margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.player-status{font-size:11px;color:var(--text-dim);display:flex;align-items:center;gap:6px}.status-dot{width:10px;height:10px;border-radius:50%;background:var(--text-dimmer)}.status-dot.playing{background:var(--primary);animation:pulse 1.5s infinite}.status-dot.paused{background:var(--text-dim);animation:pulse-dim 1.5s infinite}@keyframes pulse{0%,100%{opacity:1;box-shadow:0 0 4px var(--primary)}50%{opacity:0.6;box-shadow:0 0 8px var(--primary)}}@keyframes pulse-dim{0%,100%{opacity:1}50%{opacity:0.3}}audio{height:32px;flex-shrink:0;filter:invert(0.8);position:relative;z-index:2}.fullscreen-btn{width:40px;height:40px;background:var(--bg-card);border:1px solid var(--border);border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--primary);transition:all 0.2s;flex-shrink:0;z-index:2}.fullscreen-btn:hover{background:var(--primary);color:var(--bg);transform:scale(1.05)}.fullscreen-btn.fav-active{color:var(--primary);border-color:var(--primary);background:color-mix(in srgb,var(--primary) 15%,var(--bg-card))}.fullscreen-btn svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.fullscreen-btn.fav-active svg{fill:currentColor}.player-actions{display:flex;align-items:center;gap:8px}.volume-control{position:relative;display:flex;align-items:center;justify-content:center;flex-shrink:0}.volume-popup{position:fixed;background:color-mix(in srgb,var(--player-bg) 82%,transparent);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border:1px solid var(--player-border);border-radius:14px;padding:14px 10px 10px;display:flex;flex-direction:column;align-items:center;gap:6px;z-index:9999;opacity:0;visibility:hidden;transition:opacity 0.18s,visibility 0.18s;box-shadow:0 6px 24px rgba(0,0,0,0.4),0 0 12px var(--player-shadow);min-width:44px}.volume-popup.show{opacity:1;visibility:visible}.volume-slider-wrap{width:24px;height:88px;display:flex;align-items:center;justify-content:center;overflow:visible}.volume-slider-input{-webkit-appearance:none;appearance:none;width:88px;height:4px;background:linear-gradient(to right,var(--primary) var(--vol,50%),color-mix(in srgb,var(--primary) 22%,var(--bg-card)) var(--vol,50%));border-radius:2px;cursor:pointer;outline:none;transform:rotate(-90deg);transform-origin:center center}.volume-slider-input::-webkit-slider-thumb{-webkit-appearance:none;width:14px;height:14px;border-radius:50%;background:var(--primary);cursor:pointer;box-shadow:0 0 6px var(--player-shadow);border:2px solid var(--bg)}.volume-slider-input::-moz-range-thumb{width:14px;height:14px;border-radius:50%;background:var(--primary);cursor:pointer;border:2px solid var(--bg);box-shadow:0 0 6px var(--player-shadow)}.volume-label{font-size:10px;color:var(--primary);font-weight:600;line-height:1}#fsVolumeIcon{fill:none!important;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.result-count{text-align:center;color:var(--text-dim);font-size:12px;margin:8px 0;padding:6px 0}.stations-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:8px}.station-card{background:var(--bg-card);border-radius:8px;padding:12px;cursor:pointer;transition:all 0.15s ease;border:1px solid var(--border);display:flex;align-items:center;gap:12px;text-decoration:none;color:inherit;max-width:100%;overflow:hidden}.station-card:hover{background:var(--border-light);border-color:var(--border-light)}.station-card.active{background:var(--bg-card);border-color:var(--primary)}.station-logo{width:40px;height:40px;border-radius:8px;object-fit:cover;background:var(--bg);flex-shrink:0}.station-logo.placeholder{display:flex;align-items:center;justify-content:center}.station-content{flex:1;min-width:0}.station-name{font-size:13px;font-weight:400;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.station-meta{font-size:11px;color:var(--text-dim);margin-top:2px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;overflow:hidden}.region-tag{display:inline-flex;align-items:center;gap:4px;padding:1px 6px;border-radius:4px;background:var(--tag-bg);font-size:10px;flex-shrink:0;white-space:nowrap}.region-tag-flag{width:14px;height:10px;object-fit:cover;border-radius:1px;flex-shrink:0}.region-tag-name{color:var(--primary-dim)}.type-tag{padding:1px 6px;border-radius:4px;background:var(--type-bg);font-size:10px;color:var(--type-text);flex-shrink:0;white-space:nowrap}.loading-more{text-align:center;padding:20px;color:#666;font-size:12px;display:none}.loading-more.show{display:block}.no-results{text-align:center;padding:40px;color:#666}@media (max-width:600px){.container{padding:16px 8px;width:100%}h1{font-size:18px;letter-spacing:2px}.subtitle{font-size:11px}.logo{width:36px;height:31px}.stations-list{grid-template-columns:1fr;gap:6px;width:100%}.station-card{padding:8px;gap:8px;width:100%;box-sizing:border-box}.station-logo{width:36px;height:36px;border-radius:6px}.station-logo.placeholder{font-size:16px}.station-content{min-width:0;flex:1;overflow:hidden}.station-name{font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.station-meta{font-size:10px;gap:4px;flex-wrap:wrap}.region-tag{padding:1px 4px;font-size:9px;gap:3px}.region-tag-flag{width:12px;height:8px}.type-tag{padding:1px 4px;font-size:9px}.player-bar{flex-wrap:wrap;padding:10px}.player-logo{width:44px;height:44px}.player-title{font-size:15px}audio{width:100%;order:3;margin-top:8px}.fullscreen-btn{order:2;width:36px;height:36px}.fullscreen-btn svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.fullscreen-btn.fav-active svg{fill:currentColor}.region-btn,.type-btn{font-size:12px;padding:4px 8px}.filter-header h3{font-size:13px}}@media (max-width:400px){.container{padding:12px 6px;width:100%}.station-card{padding:6px;gap:6px;width:100%}.station-logo{width:32px;height:32px}.station-name{font-size:11px}.station-meta{font-size:9px;gap:3px}.region-tag,.type-tag{font-size:8px;padding:0 3px}.region-tag{gap:2px}.region-tag-flag{width:10px;height:7px}}body.mini-player-visible .container{padding-bottom:80px}.fullscreen-player{position:fixed;top:0;left:0;width:100vw;height:100vh;background:var(--bg);z-index:9999;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px;box-sizing:border-box;opacity:0;visibility:hidden;transition:opacity 0.3s ease,visibility 0.3s ease;overflow:hidden}.fullscreen-player.show{opacity:1;visibility:visible}.wave-background{position:absolute;top:10%;left:0;width:100%;height:80%;overflow:hidden;opacity:0.28;pointer-events:none}.wave-line{position:absolute;width:100%;height:100%}.wave-line svg{position:absolute;width:200%;height:100%}.fullscreen-player.show .wave-line:nth-child(1) svg{animation:wave-move 12s linear infinite}.fullscreen-player.show .wave-line:nth-child(2) svg{animation:wave-move 10s linear infinite;animation-delay:-3s}.fullscreen-player.show .wave-line:nth-child(3) svg{animation:wave-move 13s linear infinite;animation-delay:-2s}.fullscreen-player.show .wave-line:nth-child(4) svg{animation:wave-move 16s linear infinite;animation-delay:-9s}.fullscreen-player.show .wave-line:nth-child(5) svg{animation:wave-move 11s linear infinite;animation-delay:-4s}@keyframes wave-move{from{transform:translateX(-50%)}to{transform:translateX(0%)}}.fullscreen-top-bar{position:absolute;top:20px;left:20px;right:20px;display:flex;align-items:center;justify-content:space-between;z-index:10}.fullscreen-clock{font-size:15px;font-weight:500;color:var(--primary);opacity:0.7;letter-spacing:1px;font-variant-numeric:tabular-nums}.fullscreen-close{width:44px;height:44px;background:color-mix(in srgb,var(--primary) 15%,transparent);border:1px solid color-mix(in srgb,var(--primary) 40%,transparent);border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--primary);transition:all 0.2s}.fullscreen-close svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.fullscreen-close:hover{background:var(--primary);color:var(--bg);transform:scale(1.1)}.fullscreen-top-actions{display:flex;align-items:center;gap:10px}.fullscreen-close.fullscreen-fav-btn{position:relative}.fullscreen-close.fullscreen-fav-btn.fav-active{color:var(--primary);border-color:var(--primary);background:color-mix(in srgb,var(--primary) 18%,transparent)}.fullscreen-close.fullscreen-fav-btn svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.fullscreen-close.fullscreen-fav-btn.fav-active svg{fill:currentColor}.mini-player{position:fixed;bottom:12px;left:12px;right:12px;transform:translateY(calc(100%+12px));background:color-mix(in srgb,var(--bg) 55%,transparent);border:1px solid var(--player-border);border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,0.35),0 0 14px var(--player-shadow);display:flex;align-items:center;gap:8px;padding:8px 16px 8px 12px;opacity:0;visibility:hidden;transition:opacity 0.3s,visibility 0.3s,transform 0.3s;z-index:998;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px)}.mini-player.show{opacity:1;visibility:visible;transform:translateY(0)}.mini-player-logo{width:36px;height:36px;border-radius:8px;object-fit:cover;flex-shrink:0;background:var(--bg-card);display:flex;align-items:center;justify-content:center;overflow:hidden}.mini-player-logo img{width:100%;height:100%;object-fit:cover;border-radius:8px}.mini-player-logo.placeholder{background:color-mix(in srgb,var(--primary) 15%,var(--bg-card))}.mini-player-info{flex:1;min-width:0;overflow:hidden}.mini-player-name{font-size:0.85em;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.mini-player-status{font-size:0.72em;color:var(--primary);opacity:0.8;margin-top:1px}.mini-player-btn{width:34px;height:34px;border-radius:50%;border:none;background:color-mix(in srgb,var(--primary) 18%,transparent);color:var(--primary);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background 0.2s,box-shadow 0.2s}.mini-player-btn:hover{background:color-mix(in srgb,var(--primary) 30%,transparent);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 18%,transparent)}.mini-player-btn svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.mini-player-btn.fav-active svg{fill:currentColor}.mini-media{display:none;align-items:center;gap:8px;flex:1;min-width:0}.mini-player.has-station .mini-media{display:flex}.mini-player.has-station #backToTop{border-left:1px solid color-mix(in srgb,var(--border) 50%,transparent);margin-left:2px}.mini-extra-btns{display:flex;align-items:center;gap:8px;flex-shrink:0}.mini-player:not(.has-station) .mini-extra-btns{display:none}@media (max-width:520px){.mini-player.has-station .mini-extra-btns{display:none}.mini-player.has-station:hover .mini-extra-btns{display:flex}}.station-card.playing{border-color:var(--primary);background:color-mix(in srgb,var(--primary) 8%,var(--bg-card))}.station-card.playing .station-name{color:var(--primary);font-weight:500}.station-actions{display:flex;align-items:center;gap:6px;flex-shrink:0}.playing-badge{display:none;align-items:center;gap:4px;font-size:11px;color:var(--primary);white-space:nowrap}.station-card.playing .playing-badge{display:inline-flex}.playing-badge-dot{width:6px;height:6px;border-radius:50%;background:var(--primary);animation:pulse 1.5s infinite}.station-play-btn,.station-stop-btn{width:28px;height:28px;background:color-mix(in srgb,var(--primary) 20%,transparent);border:1px solid var(--primary);border-radius:6px;cursor:pointer;align-items:center;justify-content:center;color:var(--primary);flex-shrink:0;transition:all 0.15s}.station-play-btn{display:flex}.station-stop-btn{display:none}.station-card.playing .station-play-btn{display:none}.station-card.playing .station-stop-btn{display:flex}.station-play-btn:hover,.station-stop-btn:hover{background:var(--primary);color:#000}.station-play-btn svg,.station-stop-btn svg{width:14px;height:14px;fill:currentColor}.fullscreen-cover{width:clamp(160px,28vmin,280px);height:clamp(160px,28vmin,280px);border-radius:20px;object-fit:cover;background:var(--bg-card);border:4px solid var(--primary);box-shadow:0 20px 60px rgba(0,0,0,0.4),0 0 40px var(--player-shadow);margin-bottom:clamp(20px,4vmin,40px);position:relative}.fullscreen-cover.placeholder{display:flex;align-items:center;justify-content:center;font-size:clamp(60px,12vmin,120px);color:var(--primary)}.fullscreen-title{font-size:clamp(18px,3.5vmin,32px);font-weight:600;color:var(--text);margin-bottom:clamp(6px,1.5vmin,12px);text-align:center;max-width:80%}.fullscreen-status{font-size:clamp(12px,2vmin,16px);color:var(--text-dim);margin-bottom:clamp(20px,4vmin,40px);display:flex;align-items:center;gap:8px}.fullscreen-controls{display:flex;align-items:center;gap:20px}.fullscreen-control-btn{width:clamp(44px,8vmin,64px);height:clamp(44px,8vmin,64px);background:var(--bg-card);border:2px solid var(--primary);border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--primary);transition:all 0.2s}.fullscreen-control-btn:hover{background:var(--primary);color:var(--bg);transform:scale(1.1)}.fullscreen-control-btn.play-pause{width:clamp(56px,10vmin,80px);height:clamp(56px,10vmin,80px)}.fullscreen-control-btn svg{width:clamp(20px,4vmin,32px);height:clamp(20px,4vmin,32px);fill:currentColor}.fullscreen-control-btn.play-pause svg{width:clamp(26px,5vmin,40px);height:clamp(26px,5vmin,40px)}.sound-wave{display:flex;align-items:center;gap:4px;height:40px;position:absolute;bottom:-60px;left:50%;transform:translateX(-50%)}.sound-wave-bar{width:4px;background:var(--primary);border-radius:2px;opacity:0.6}.sound-wave.playing .sound-wave-bar{animation:wave 1.2s ease-in-out infinite}.sound-wave-bar:nth-child(1){animation-delay:0s}.sound-wave-bar:nth-child(2){animation-delay:0.1s}.sound-wave-bar:nth-child(3){animation-delay:0.2s}.sound-wave-bar:nth-child(4){animation-delay:0.3s}.sound-wave-bar:nth-child(5){animation-delay:0.4s}@keyframes wave{0%,100%{height:10px}50%{height:35px}}.fullscreen-content{display:flex;flex-direction:column;align-items:center;position:relative;z-index:1}.fullscreen-info{display:flex;flex-direction:column;align-items:center}@media (orientation:landscape) and (min-aspect-ratio:4/3){.fullscreen-player{padding:15px 5vw}.fullscreen-content{flex-direction:row;gap:clamp(24px,5vw,60px);align-items:center;justify-content:center;width:100%}.fullscreen-cover{width:clamp(120px,38vh,260px);height:clamp(120px,38vh,260px);margin-bottom:0;flex-shrink:0}.fullscreen-cover.placeholder{font-size:clamp(44px,13vh,90px)}.fullscreen-info{align-items:flex-start;flex:1;min-width:0;max-width:55vw}.fullscreen-title{font-size:clamp(16px,3.2vh,30px);margin-bottom:clamp(4px,1vh,10px);text-align:left;max-width:100%}.fullscreen-status{font-size:clamp(11px,1.8vh,15px);margin-bottom:clamp(12px,2.5vh,28px)}.fullscreen-controls{margin-top:0}.fullscreen-control-btn{width:clamp(40px,7vh,64px);height:clamp(40px,7vh,64px)}.fullscreen-control-btn.play-pause{width:clamp(52px,9vh,80px);height:clamp(52px,9vh,80px)}.fullscreen-control-btn svg{width:clamp(18px,3.5vh,32px);height:clamp(18px,3.5vh,32px)}.fullscreen-control-btn.play-pause svg{width:clamp(22px,4.5vh,40px);height:clamp(22px,4.5vh,40px)}.sound-wave{bottom:clamp(-40px,-8vh,-20px);height:clamp(20px,4vh,40px)}}@media (max-width:600px){.fullscreen-player{padding:20px}.fullscreen-cover{max-width:90vw;max-height:90vw}.fullscreen-title{max-width:90%}}.station-fav-btn{width:28px;height:28px;background:transparent;border:1px solid var(--border);border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-dimmer);flex-shrink:0;transition:all 0.15s}.station-fav-btn svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.station-fav-btn:hover{border-color:var(--primary);color:var(--primary)}.station-fav-btn.fav-active{border-color:var(--primary);color:var(--primary);background:color-mix(in srgb,var(--primary) 12%,transparent)}.station-fav-btn.fav-active svg{fill:var(--primary)}.mini-player-btn.fav-active{color:var(--primary);background:color-mix(in srgb,var(--primary) 20%,transparent)}.mini-player-btn.fav-active svg{fill:var(--primary)}.mini-player-btn.fav-active:hover{background:color-mix(in srgb,var(--primary) 40%,transparent);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 28%,transparent)}.fullscreen-control-btn.fav-active{border-color:var(--primary);color:var(--primary)}.fullscreen-control-btn.fav-active svg{fill:var(--primary);stroke:var(--primary)}.station-card.station-invalid{opacity:0.65}.invalid-badge{display:none;padding:1px 5px;border-radius:4px;background:color-mix(in srgb,#ef4444 15%,transparent);border:1px solid #ef4444;color:#ef4444;font-size:9px;white-space:nowrap;flex-shrink:0}.station-card.station-invalid .invalid-badge{display:inline-flex;align-items:center}.fav-only-btn{display:flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:50%;border:1px solid var(--border);background:var(--bg-input);cursor:pointer;color:var(--text-dim);flex-shrink:0;transition:color 0.15s,background 0.15s,border-color 0.15s}.fav-only-btn svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;transition:fill 0.15s,stroke 0.15s}.fav-only-btn.active{color:var(--primary);border-color:var(--primary);background:color-mix(in srgb,var(--primary) 12%,var(--bg-input))}.fav-only-btn.active svg{fill:var(--primary);stroke:var(--primary)}body.fav-only-mode .filter-section{display:none}</style>
</head>
<body>

    <div id="topProgressBar"><div id="topProgressFill"></div></div>
    <div class="container">
        <div class="fixed-header">
        <header>
            <div class="header-left">
                <svg class="logo" viewBox="0 0 256 256" width="52" height="46" role="img" aria-label="电台森林">
                    <defs>
                        <linearGradient id="logoBg" x1="0" x2="1" y1="0" y2="1">
                            <stop offset="0%" stop-color="var(--primary-dim, #3e2f33)"/>
                            <stop offset="100%" stop-color="var(--primary, #22c55e)"/>
                        </linearGradient>
                        <linearGradient id="panelBg" x1="0" x2="1" y1="0" y2="1">
                            <stop offset="0%" stop-color="var(--bg-card, #1a1a1a)"/>
                            <stop offset="100%" stop-color="var(--bg, #121212)"/>
                        </linearGradient>
                    </defs>
                    <rect x="16" y="56" width="224" height="152" rx="28" ry="28" fill="url(#logoBg)"/>
                    <rect x="28" y="68" width="200" height="132" rx="22" ry="22" fill="url(#panelBg)" opacity="0.95"/>
                    <g transform="translate(40,84)">
                        <rect x="0" y="0" width="116" height="96" rx="16" ry="16" fill="var(--bg, #121212)"/>
                        <rect x="8" y="8" width="100" height="80" rx="12" ry="12" fill="var(--primary-dim, #4a383c)" opacity="0.9"/>
                        <circle cx="58" cy="48" r="34" fill="var(--primary, #a6764b)"/>
                        <circle cx="58" cy="48" r="26" fill="var(--bg, #f8e0b8)"/>
                        <circle cx="58" cy="48" r="18" fill="var(--primary-dim, #4b3a41)"/>
                        <circle cx="58" cy="48" r="6" fill="var(--bg, #3d2c2f)"/>
                        <path d="M28 48h60" stroke="var(--bg, #f8e0b8)" stroke-width="5" stroke-linecap="round"/>
                    </g>
                    <g transform="translate(172,84)">
                        <rect x="0" y="0" width="60" height="36" rx="10" ry="10" fill="var(--primary-dim, #352a2d)"/>
                        <rect x="4" y="4" width="52" height="28" rx="8" ry="8" fill="var(--primary, #f8e0b8)" opacity="0.2"/>
                        <circle cx="32" cy="18" r="6" fill="var(--primary-dim, #4a383c)"/>
                        <circle cx="32" cy="18" r="2" fill="var(--bg, #ffe8b6)"/>
                        <line x1="14" y1="22" x2="50" y2="22" stroke="var(--bg, #4a383c)" stroke-width="4" stroke-linecap="round"/>
                        <line x1="14" y1="14" x2="50" y2="14" stroke="var(--bg, #4a383c)" stroke-width="4" stroke-linecap="round"/>
                    </g>
                    <path d="M56 32L44 8" stroke="var(--primary-dim, #4a383c)" stroke-width="12" stroke-linecap="round"/>
                    <path d="M44 8L64 4" stroke="var(--primary-dim, #4a383c)" stroke-width="12" stroke-linecap="round"/>
                    <rect x="40" y="160" width="176" height="18" rx="9" ry="9" fill="var(--primary-dim, #4a383c)"/>
                    <rect x="48" y="166" width="28" height="6" rx="3" ry="3" fill="var(--bg, #f9d9a6)"/>
                    <rect x="84" y="166" width="20" height="6" rx="3" ry="3" fill="var(--bg, #f9d9a6)"/>
                    <rect x="116" y="166" width="20" height="6" rx="3" ry="3" fill="var(--bg, #f9d9a6)"/>
                    <rect x="148" y="166" width="20" height="6" rx="3" ry="3" fill="var(--bg, #f9d9a6)"/>
                    <rect x="180" y="166" width="20" height="6" rx="3" ry="3" fill="var(--bg, #f9d9a6)"/>
                </svg>
                <h1 data-i18n="appTitle">电台森林</h1>
                <p class="subtitle" id="totalSubtitle">正在加载...</p>
            </div>
            <div class="header-right">

                <div class="picker-wrap" id="themePickerWrap">
                    <button class="picker-btn" id="themePickerBtn" type="button" aria-label="主题">
                        <span class="picker-dot" id="themePickerDot" style="background:#22c55e"></span>
                        <span class="picker-label" id="themePickerLabel">翠绿</span>
                        <svg class="picker-caret" viewBox="0 0 8 5" xmlns="http://www.w3.org/2000/svg"><path d="M0 0l4 5 4-5z"/></svg>
                    </button>
                    <div class="picker-panel" id="themePanel"></div>
                </div>

                <div class="picker-wrap" id="langPickerWrap">
                    <button class="picker-btn" id="langPickerBtn" type="button" aria-label="语言">
                        <img class="picker-flag" id="langPickerFlag" src="https://flagcdn.com/w20/cn.png" alt="">
                        <span class="picker-label" id="langPickerLabel">简体中文</span>
                        <svg class="picker-caret" viewBox="0 0 8 5" xmlns="http://www.w3.org/2000/svg"><path d="M0 0l4 5 4-5z"/></svg>
                    </button>
                    <div class="picker-panel" id="langPanel"></div>
                </div>

                <a class="github-link" href="https://github.com/jarrey/radioforest" target="_blank" rel="noopener noreferrer" data-i18n-title="githubLinkTitle" title="在 GitHub 上查看项目">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.477 2 2 6.477 2 12c0 4.418 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.009-.868-.013-1.703-2.782.604-3.369-1.341-3.369-1.341-.454-1.154-1.11-1.462-1.11-1.462-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.578 9.578 0 0 1 12 6.836a9.59 9.59 0 0 1 2.504.337c1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.202 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.163 22 16.418 22 12c0-5.523-4.477-10-10-10z"/></svg>
                </a>
            </div>
        </header>

        <div class="search-row">
            <div class="search-box">
                <input type="text" id="searchInput" data-i18n-placeholder="searchPlaceholder" placeholder="搜索电台...">
            </div>

            <button class="fav-only-btn" id="favOnlyBtn" data-i18n-title="favOnlyToggle" title="仅显示收藏">
                <svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </button>
        </div>

        <div class="filter-section">
            <div class="filter-header" id="regionsToggle">
                <span class="toggle-icon">▼</span>
                <h3 data-i18n="regionsHeader">国家/地区<span id="regionFilterLabel" style="font-weight:normal;opacity:0.65;margin-left:6px;font-size:0.85em;"></span></h3>
            </div>
            <div class="filter-content" id="regionsContent">
                <div class="regions" id="regionBtns">
            <button class="region-btn active" data-region="all" data-flag-code="un" data-count="<?php echo $totalCount; ?>"><img src="https://flagcdn.com/w20/un.png" alt="" class="region-flag"> 全部(<?php echo $totalCount; ?>)</button>
            <?php
            // 从 REGION_NAMES 自动生成"中文名 → ISO码"映射，与 config.php 保持同步
            $regionCodes = array_merge(array_flip(REGION_NAMES), ['其他' => 'un']);
            $regionOrder = ['中国', '日本', '韩国', '台湾', '香港', '新加坡', '美国', '加拿大', '墨西哥', '巴西', '阿根廷', '英国', '德国', '法国', '意大利', '西班牙', '瑞士', '俄罗斯', '澳大利亚', '新西兰', '南非', '其他'];
            // 将不在预设顺序中的国家追加到末尾，避免新增 M3U 文件后被忽略
            $allRegions = array_merge($regionOrder, array_diff(array_keys($countries), $regionOrder));
            foreach ($allRegions as $r):
                if (isset($countries[$r])):
                    $code = $regionCodes[$r] ?? 'un';
                    $flagImg = '<img src="https://flagcdn.com/w20/' . $code . '.png" alt="" class="region-flag">';
            ?>
                <button class="region-btn" data-region="<?php echo $r; ?>" data-flag-code="<?php echo $code; ?>" data-count="<?php echo $countries[$r]; ?>"><?php echo $flagImg . ' ' . $r; ?>(<?php echo $countries[$r]; ?>)</button>
            <?php endif; endforeach; ?>
                </div>
            </div>
        </div>


        <div class="filter-section">
            <div class="filter-header" id="typesToggle">
                <span class="toggle-icon">▼</span>
                <h3 data-i18n="typesHeader">分类<span id="typeFilterLabel" style="font-weight:normal;opacity:0.65;margin-left:6px;font-size:0.85em;"></span></h3>
            </div>
            <div class="filter-content" id="typesContent">
                <div class="types" id="typeBtns"></div>
            </div>
        </div>

        <div class="player-bar" id="playerBar">
            <div class="player-logo placeholder" id="playerLogo"><svg viewBox="0 0 60 52" width="44" height="38" xmlns="http://www.w3.org/2000/svg"><rect x="8" y="10" width="44" height="32" rx="12" fill="var(--primary)"/><rect x="10" y="12" width="40" height="28" rx="10" fill="var(--bg-card)" opacity="0.95"/><rect x="12" y="14" width="22" height="20" rx="6" fill="var(--bg)"/><rect x="14" y="16" width="18" height="16" rx="5" fill="var(--primary-dim)" opacity="0.85"/><circle cx="25" cy="24" r="9" fill="var(--bg)"/><circle cx="25" cy="24" r="6" fill="var(--primary-dim)"/><circle cx="25" cy="24" r="3" fill="var(--bg)"/><path d="M16 24h18" stroke="var(--bg)" stroke-width="2" stroke-linecap="round"/><rect x="36" y="18" width="10" height="14" rx="3" fill="var(--primary-dim)"/><circle cx="41" cy="25" r="2" fill="var(--bg)"/><line x1="38" y1="21" x2="44" y2="21" stroke="var(--bg)" stroke-width="1.5" stroke-linecap="round"/><line x1="38" y1="29" x2="44" y2="29" stroke="var(--bg)" stroke-width="1.5" stroke-linecap="round"/></svg></div>
            <div class="player-info">
                <div class="player-title" id="playerTitle" data-i18n="selectStationToPlay">选择一个电台开始播放</div>
                <div class="player-status">
                    <span class="status-dot" id="statusDot"></span>
                    <span id="playerStatus" data-i18n="playerWaiting">等待中</span>
                </div>
            </div>
            <audio controls id="audioPlayer"></audio>
            <div class="player-actions">
                <div class="volume-control" id="barVolumeControl">
                    <button class="fullscreen-btn" id="barVolumeBtn" data-i18n-title="volume" title="音量">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" id="barVolumeIcon">
                            <polygon points="11,5 6,9 2,9 2,15 6,15 11,19"/>
                            <path d="M15.54,8.46a5,5,0,0,1,0,7.07"/>
                            <path d="M19.07,4.93a10,10,0,0,1,0,14.14"/>
                        </svg>
                    </button>
                    <div class="volume-popup" id="barVolumePopup">
                        <div class="volume-slider-wrap">
                            <input type="range" class="volume-slider-input" id="barVolumeSlider" min="0" max="100" value="50">
                        </div>
                        <span class="volume-label" id="barVolumeLabel">50%</span>
                    </div>
                </div>
                <button class="fullscreen-btn" id="playerFavBtn" data-i18n-title="favAdd" title="收藏">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                </button>
                <button class="fullscreen-btn" id="fullscreenBtn" data-i18n-title="fullscreen" title="全屏播放">
                    <svg viewBox="0 0 24 24">
                        <path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>
                    </svg>
                </button>
            </div>
        </div>

        <p class="result-count" id="resultCount" data-i18n="loading">加载中...</p>

        <div class="stations-list" id="stationsGrid"></div>

        <div class="loading-more" id="loadingMore" data-i18n="loadMore">加载更多...</div>
    </div>


    <div class="mini-player" id="miniPlayer">
        <div class="mini-media" id="miniMedia">
            <div class="mini-player-logo placeholder" id="miniPlayerLogo"></div>
            <div class="mini-player-info">
                <div class="mini-player-name" id="miniPlayerName" data-i18n="selectStationToPlay">选择一个电台</div>
                <div class="mini-player-status" id="miniPlayerStatus" data-i18n="playerWaiting">等待中</div>
            </div>
            <button class="mini-player-btn" id="miniPlayBtn" data-i18n-title="playPause" title="播放/暂停">
                <svg viewBox="0 0 24 24" id="miniPlayIcon"><polygon points="6,4 20,12 6,20"/></svg>
            </button>
        </div>
        <div class="mini-extra-btns">
            <div class="volume-control" id="miniVolumeControl">
                <button class="mini-player-btn" id="miniVolumeBtn" data-i18n-title="volume" title="音量">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" id="miniVolumeIcon">
                        <polygon points="11,5 6,9 2,9 2,15 6,15 11,19"/>
                        <path d="M15.54,8.46a5,5,0,0,1,0,7.07"/>
                        <path d="M19.07,4.93a10,10,0,0,1,0,14.14"/>
                    </svg>
                </button>
                <div class="volume-popup" id="miniVolumePopup">
                    <div class="volume-slider-wrap">
                        <input type="range" class="volume-slider-input" id="miniVolumeSlider" min="0" max="100" value="50">
                    </div>
                    <span class="volume-label" id="miniVolumeLabel">50%</span>
                </div>
            </div>
            <button class="mini-player-btn" id="miniFavBtn" data-i18n-title="favAdd" title="收藏">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </button>
            <button class="mini-player-btn" id="miniFullscreenBtn" data-i18n-title="fullscreen" title="全屏播放">
                <svg viewBox="0 0 24 24"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>
            </button>
        </div>
        <button class="mini-player-btn" id="backToTop" data-i18n-title="backToTop" title="回到顶部">
            <svg viewBox="0 0 24 24"><path d="M12 4l-8 8h5v8h6v-8h5z"/></svg>
        </button>
    </div>


    <div class="fullscreen-player" id="fullscreenPlayer">
        <div class="wave-background">
            <div class="wave-line">
                <svg viewBox="0 0 2000 1000" preserveAspectRatio="none">
                    <path d="M0,500 Q250,420 500,500 T1000,500 T1500,500 T2000,500" 
                          stroke="var(--primary)" 
                          stroke-width="3" 
                          fill="none" 
                          stroke-linecap="round"
                          opacity="0.9"/>
                </svg>
            </div>
            <div class="wave-line">
                <svg viewBox="0 0 2000 1000" preserveAspectRatio="none">
                    <path d="M0,520 Q250,440 500,520 T1000,520 T1500,520 T2000,520" 
                          stroke="var(--primary)" 
                          stroke-width="2.5" 
                          fill="none" 
                          stroke-linecap="round"
                          opacity="0.7"/>
                </svg>
            </div>
            <div class="wave-line">
                <svg viewBox="0 0 2000 1000" preserveAspectRatio="none">
                    <path d="M0,540 Q250,460 500,540 T1000,540 T1500,540 T2000,540" 
                          stroke="var(--primary)" 
                          stroke-width="2" 
                          fill="none" 
                          stroke-linecap="round"
                          opacity="0.5"/>
                </svg>
            </div>
            <div class="wave-line">
                <svg viewBox="0 0 2000 1000" preserveAspectRatio="none">
                    <path d="M0,490 Q250,570 500,490 T1000,490 T1500,490 T2000,490" 
                          stroke="var(--primary)" 
                          stroke-width="3" 
                          fill="none" 
                          stroke-linecap="round"
                          opacity="0.8"/>
                </svg>
            </div>
            <div class="wave-line">
                <svg viewBox="0 0 2000 1000" preserveAspectRatio="none">
                    <path d="M0,510 Q250,430 500,510 T1000,510 T1500,510 T2000,510" 
                          stroke="var(--primary)" 
                          stroke-width="2.5" 
                          fill="none" 
                          stroke-linecap="round"
                          opacity="0.6"/>
                </svg>
            </div>
        </div>
        <div class="fullscreen-top-bar">
            <span class="fullscreen-clock" id="fullscreenClock"></span>
            <div class="fullscreen-top-actions">
                <button class="fullscreen-close fullscreen-fav-btn" id="fullscreenFavBtn" data-i18n-title="favAdd" title="收藏">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                </button>
                <button class="fullscreen-close" id="fullscreenClose">
                    <svg viewBox="0 0 24 24">
                        <polyline points="4 14 10 14 10 20"/>
                        <polyline points="20 10 14 10 14 4"/>
                        <line x1="14" y1="10" x2="21" y2="3"/>
                        <line x1="3" y1="21" x2="10" y2="14"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="fullscreen-content">
            <div class="fullscreen-cover placeholder" id="fullscreenCover">
                <svg viewBox="0 0 60 52" width="100" height="87" xmlns="http://www.w3.org/2000/svg"><rect x="8" y="10" width="44" height="32" rx="12" fill="var(--primary)"/><rect x="10" y="12" width="40" height="28" rx="10" fill="var(--bg-card)" opacity="0.95"/><rect x="12" y="14" width="22" height="20" rx="6" fill="var(--bg)"/><rect x="14" y="16" width="18" height="16" rx="5" fill="var(--primary-dim)" opacity="0.85"/><circle cx="25" cy="24" r="9" fill="var(--bg)"/><circle cx="25" cy="24" r="6" fill="var(--primary-dim)"/><circle cx="25" cy="24" r="3" fill="var(--bg)"/><path d="M16 24h18" stroke="var(--bg)" stroke-width="2" stroke-linecap="round"/><rect x="36" y="18" width="10" height="14" rx="3" fill="var(--primary-dim)"/><circle cx="41" cy="25" r="2" fill="var(--bg)"/><line x1="38" y1="21" x2="44" y2="21" stroke="var(--bg)" stroke-width="1.5" stroke-linecap="round"/><line x1="38" y1="29" x2="44" y2="29" stroke="var(--bg)" stroke-width="1.5" stroke-linecap="round"/></svg>
            </div>

            <div class="fullscreen-info">
                <h2 class="fullscreen-title" id="fullscreenTitle" data-i18n="selectStationToPlay">选择一个电台开始播放</h2>

                <div class="fullscreen-status">
                    <span class="status-dot" id="fullscreenDot"></span>
                    <span id="fullscreenStatus">等待中</span>
                </div>

                <div class="fullscreen-controls">
                    <button class="fullscreen-control-btn play-pause" id="fullscreenPlayBtn">
                        <svg viewBox="0 0 24 24">
                            <polygon points="8,5 19,12 8,19"/>
                        </svg>
                    </button>
                    <div class="volume-control" id="fsVolumeControl">
                        <button class="fullscreen-control-btn play-pause" id="fsVolumeBtn" data-i18n-title="volume" title="音量">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" id="fsVolumeIcon">
                                <polygon points="11,5 6,9 2,9 2,15 6,15 11,19"/>
                                <path d="M15.54,8.46a5,5,0,0,1,0,7.07"/>
                                <path d="M19.07,4.93a10,10,0,0,1,0,14.14"/>
                            </svg>
                        </button>
                        <div class="volume-popup" id="fsVolumePopup">
                            <div class="volume-slider-wrap">
                                <input type="range" class="volume-slider-input" id="fsVolumeSlider" min="0" max="100" value="50">
                            </div>
                            <span class="volume-label" id="fsVolumeLabel">50%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>const regionCodes=<?php echo json_encode(array_merge(array_flip(REGION_NAMES), ['其他' => 'un']), JSON_UNESCAPED_UNICODE); ?>;function makeSvgLogo(n,e){return`<svg viewBox="0 0 60 52" width="${n}" height="${e}" xmlns="http://www.w3.org/2000/svg"><rect x="8" y="10" width="44" height="32" rx="12" fill="var(--bg-card)" opacity="0.95" stroke="var(--primary)" stroke-width="1"/><rect x="12" y="14" width="22" height="20" rx="6" fill="var(--bg)"/><rect x="14" y="16" width="18" height="16" rx="5" fill="var(--primary-dim)" opacity="0.85"/><circle cx="25" cy="24" r="9" fill="var(--bg)"/><circle cx="25" cy="24" r="6" fill="var(--primary-dim)"/><circle cx="25" cy="24" r="3" fill="var(--bg)"/><path d="M16 24h18" stroke="var(--bg)" stroke-width="2" stroke-linecap="round"/><rect x="36" y="18" width="10" height="14" rx="3" fill="var(--primary-dim)"/><circle cx="41" cy="25" r="2" fill="var(--bg)"/><line x1="38" y1="21" x2="44" y2="21" stroke="var(--bg)" stroke-width="1.5" stroke-linecap="round"/><line x1="38" y1="29" x2="44" y2="29" stroke="var(--bg)" stroke-width="1.5" stroke-linecap="round"/></svg>`}const SVG_LOGO_SM=makeSvgLogo(36,28),SVG_LOGO_MD=makeSvgLogo(44,38),SVG_LOGO_LG=makeSvgLogo(100,87),FAV_HEART_SVG='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';var allStations=[];const typePatterns={"音乐":/音乐|Music|pop|rock|hit|jazz|classical/i,"新闻":/新闻|News|资讯|information/i,"财经":/财经|金融|股票|经济|Finance|Financial|Business|Economy|Stock|Market|Investment|Banking/i,"综合":/广播|Radio|综合|general/i,"交通":/交通|Traffic|汽车|Auto/i,"体育":/体育|Sports/i,"文艺":/文艺|Culture/i,"经典":/经典|Classic|怀旧|Old/i,"儿童":/儿童|少儿|Kids|children/i,"宗教":/宗教|上帝|Islam|Christian|Quran|佛教/i,"古典":/古典|Symphony|交响/i,"方言":/方言|闽南|粤语|Cantonese|吴语|沪语/i,"爵士":/jazz|爵士/i,"流行":/\bpop\b|流行|top\s*40|\bhits\b|k.?pop|j.?pop/i,"摇滚":/\brock\b|摇滚|\bindie\b|alternative|punk|grunge/i,"嘻哈":/hip.?hop|\brap\b|嘻哈|说唱/i,"电子":/\bedm\b|\belectronic\b|\belectro\b|techno|trance|dance\s*(?:music|fm|radio|hits)|舞曲|电子音乐/i,"R&B":/\br\s*&\s*b\b|rhythm.?blues|soul\s*(?:music|fm|radio)|灵魂乐|节奏蓝调/i,"乡村":/\bcountry\b|乡村音乐/i,"民谣":/\bfolk\b|民谣|\bacoustic\b/i,"蓝调":/\bblues\b|蓝调/i,"雷鬼":/reggae|雷鬼/i,"金属":/\bmetal\b|金属乐|heavy\s*metal/i,"拉丁":/\blatin\b|salsa|cumbia|merengue|bachata|拉丁/i,"央广":/央广|中央人民广播|中国之声|CNR\s*\d|CRI\b|中国国际广播|全国联播|中央广播电台/i,"央视":/央视|CCTV|中央电视台/i},typeKeys=Object.keys(typePatterns),provincePatterns={"全国":/央广|中央人民广播|中国之声|CNR\s*\d|CRI\b|中国国际广播|全国联播|中央广播电台/i,"北京":/北京|Beijing|Peking|Peiping|Peiking/i,"天津":/天津|Tianjin|Tientsin|Tientsien/i,"上海":/上海|Shanghai/i,"重庆":/重庆|Chongqing|Chungking|Chunking/i,"广东":/广东|珠江|Guangdong|Kwangtung|广州|深圳|珠海|汕头|佛山|韶关|湛江|肇庆|江门|茂名|惠州|梅州|汕尾|河源|阳江|清远|东莞|中山|潮州|揭阳|云浮|Guangzhou|Canton|Kwangchow|Shenzhen|Shumchun|Zhuhai|Shantou|Swatow|Foshan|Fatshan|Shaoguan|Shaokwan|Zhanjiang|Tsamkong|Chanchiang|Zhaoqing|Shiuhing|Jiangmen|Kongmoon|Maoming|Huizhou|Meizhou|Kaying|Shanwei|Heyuan|Yangjiang|Qingyuan|Dongguan|Zhongshan|Chaozhou|Chaochow|Jieyang|Yunfu/i,"广西":/广西|Guangxi|Kwangsi|南宁|柳州|桂林|梧州|北海|防城港|钦州|贵港|玉林|百色|贺州|河池|来宾|崇左|Nanning|Liuzhou|Liuchow|Guilin|Kweilin|Wuzhou|Beihai|Fangchenggang|Qinzhou|Guigang|Yulin|Baise|Hezhou|Hechi|Laibin|Chongzuo/i,"海南":/海南(?!州)|Hainan|海口|三亚|三沙|儋州|琼海|文昌|万宁|Haikou|Hoihow|Haikkow|Sanya|Danzhou|Qionghai|Wenchang|Wanning/i,"福建":/福建|闽南|闽北|闽东|闽西|Fujian|Fukien|福州|厦门|莆田|三明|泉州|漳州|南平|龙岩|宁德|Fuzhou|Foochow|Xiamen|Amoy|Putian|Hinghwa|Sanming|Quanzhou|Chinchew|Tsinkiang|Zhangzhou|Changchow|Cheangchew|Nanping|Longyan|Ningde/i,"江苏":/江苏|Jiangsu|Kiangsu|南京|无锡|徐州|常州|苏州|南通|连云港|淮安|盐城|扬州|镇江|泰州|宿迁|Nanjing|Nanking|Wuxi|Wusih|Xuzhou|Hsuchow|Suchow|Changzhou|Suzhou|Soochow|Nantong|Nantung|Lianyungang|Lienyunkang|Huaian|Yancheng|Yangzhou|Yangchow|Zhenjiang|Chinkiang|Taizhou|Suqian/i,"浙江":/浙江|Zhejiang|Chekiang|杭州|宁波|温州|嘉兴|湖州|绍兴|金华|衢州|舟山|台州|丽水|Hangzhou|Hangchow|Ningbo|Ningpo|Wenzhou|Wenchow|Jiaxing|Kashing|Huzhou|Huchow|Shaoxing|Shaohsing|Jinhua|Quzhou|Zhoushan|Lishui/i,"山东":/山东|Shandong|Shantung|济南|青岛|淄博|枣庄|东营|烟台|潍坊|济宁|泰安|威海|日照|临沂|德州|聊城|滨州|菏泽|Jinan|Tsinan|Chinan|Qingdao|Tsingtao|Chingtao|Zibo|Zaozhuang|Dongying|Yantai|Chefoo|Yentai|Weifang|Jining|Taian|Weihai|Weihaiwei|Rizhao|Linyi|Dezhou|Liaocheng|Binzhou|Heze/i,"安徽":/安徽|Anhui|Anhwei|Nganhwei|合肥|芜湖|蚌埠|淮南|马鞍山|淮北|铜陵|安庆|黄山|滁州|阜阳|宿州|六安|宣城|池州|亳州|Hefei|Hofei|Wuhu|Bengbu|Pengpu|Huainan|Maanshan|Huaibei|Tongling|Anqing|Anking|Huangshan|Chuzhou|Fuyang|Luan|Xuancheng|Chizhou|Bozhou/i,"江西":/江西|Jiangxi|Kiangsi|南昌|景德镇|萍乡|九江|新余|鹰潭|赣州|吉安|宜春|抚州|上饶|Nanchang|Jingdezhen|Kingtehchen|Pingxiang|Jiujiang|Kiukiang|Xinyu|Yingtan|Ganzhou|Jian|Yichun|Fuzhou|Shangrao/i,"湖南":/湖南|Hunan|长沙|株洲|湘潭|衡阳|邵阳|岳阳|常德|张家界|益阳|郴州|永州|怀化|娄底|湘西|Changsha|Zhuzhou|Xiangtan|Siangtan|Hengyang|Shaoyang|Yueyang|Yochow|Changde|Zhangjiajie|Yiyang|Chenzhou|Yongzhou|Huaihua|Loudi|Xiangxi/i,"湖北":/湖北|Hubei|Hupeh|Hupei|武汉|黄石|十堰|宜昌|襄阳|鄂州|荆门|孝感|荆州|黄冈|咸宁|随州|恩施|仙桃|潜江|天门|Wuhan|Wuchang|Hankow|Hankou|Hanyang|Huangshi|Shiyan|Yichang|Ichang|Xiangyang|Siangyang|Ezhou|Jingmen|Xiaogan|Jingzhou|Shashi|Huanggang|Xianning|Suizhou|Enshi|Xiantao|Qianjiang|Tianmen/i,"河南":/河南|Henan|Honan|郑州|开封|洛阳|平顶山|安阳|鹤壁|新乡|焦作|濮阳|许昌|漯河|三门峡|南阳|商丘|信阳|周口|驻马店|Zhengzhou|Chengchow|Kaifeng|Luoyang|Loyang|Pingdingshan|Anyang|Hebi|Xinxiang|Jiaozuo|Puyang|Xuchang|Luohe|Sanmenxia|Nanyang|Shangqiu|Xinyang|Zhoukou|Zhumadian/i,"河北":/河北|Hebei|Hopeh|Hopei|石家庄|唐山|秦皇岛|邯郸|邢台|保定|张家口|承德|沧州|廊坊|衡水|Shijiazhuang|Shihkiachwang|Shichiachiang|Tangshan|Qinhuangdao|Handan|Xingtai|Baoding|Paoting|Zhangjiakou|Kalgan|Changchiakow|Chengde|Cangzhou|Langfang|Hengshui/i,"山西":/山西|Shanxi|Shansi|太原|大同|阳泉|长治|晋城|朔州|晋中|运城|忻州|临汾|吕梁|Taiyuan|Datong|Tatung|Yangquan|Changzhi|Jincheng|Shuozhou|Jinzhong|Yuncheng|Xinzhou|Linfen|Luliang/i,"辽宁":/辽宁|Liaoning|沈阳|大连|鞍山|抚顺|本溪|丹东|锦州|营口|阜新|辽阳|盘锦|铁岭|朝阳|葫芦岛|Shenyang|Mukden|Fengtien|Dalian|Dairen|Talien|Anshan|Fushun|Benxi|Dandong|Antung|Jinzhou|Chinchow|Yingkou|Fuxin|Liaoyang|Panjin|Tieling|Chaoyang|Huludao/i,"吉林":/吉林|Jilin|Kirin|Chilin|长春|四平|辽源|通化|白山|松原|白城|延边|Changchun|Hsinking|Siping|Liaoyuan|Tonghua|Baishan|Songyuan|Baicheng|Yanbian/i,"黑龙江":/黑龙江|Heilongjiang|Heilungkiang|哈尔滨|齐齐哈尔|鸡西|鹤岗|双鸭山|大庆|伊春|佳木斯|七台河|牡丹江|黑河|绥化|大兴安岭|Harbin|Qiqihar|Tsitsihar|Chichihaerh|Jixi|Hegang|Shuangyashan|Daqing|Yichun|Jiamusi|Chiamussu|Qitaihe|Mudanjiang|Mutankiang|Heihe|Suihua|Daxinganling/i,"四川":/四川|Sichuan|Szechwan|Szechuan|成都|自贡|攀枝花|泸州|德阳|绵阳|广元|遂宁|内江|乐山|南充|眉山|宜宾|广安|达州|雅安|巴中|资阳|阿坝|甘孜|凉山|Chengdu|Chengtu|Zigong|Panzhihua|Luzhou|Luchow|Deyang|Mianyang|Mienchiang|Guangyuan|Suining|Neijiang|Leshan|Kiating|Nanchong|Nanchung|Meishan|Yibin|Ipin|Guangan|Dazhou|Yaan|Bazhong|Ziyang|Aba|Ganzi|Liangshan/i,"贵州":/贵州|Guizhou|Kweichow|贵阳|六盘水|遵义|安顺|毕节|铜仁|黔西南|黔东南|黔南|Guiyang|Kweiyang|Liupanshui|Zunyi|Tsunyi|Anshun|Bijie|Tongren|Qianxinan|Qiandongnan|Qiannan/i,"云南":/云南|Yunnan|昆明|曲靖|玉溪|保山|昭通|丽江|普洱|临沧|楚雄|红河|文山|西双版纳|大理|德宏|怒江|迪庆|Kunming|Yunnanfu|Qujing|Yuxi|Baoshan|Zhaotong|Lijiang|Likiang|Puer|Lincang|Chuxiong|Honghe|Wenshan|Xishuangbanna|Dali|Tali|Dehong|Nujiang|Diqing/i,"西藏":/西藏|Tibet|藏语|拉萨|日喀则|昌都|林芝|山南|那曲|阿里|Lhasa|Lasa|Shigatse|Xigazê|Chamdo|Changdu|Nyingchi|Linzhi|Shannan|Nagqu|Naqu/i,"陕西":/陕西|Shaanxi|Shensi|西安|铜川|宝鸡|咸阳|渭南|延安|汉中|榆林|安康|商洛|Xian|Xi.an|Sian|Hsian|Sianfu|Tongchuan|Baoji|Paochi|Xianyang|Hsienyang|Weinan|Yanan|Yenan|Hanzhong|Hanchung|Yulin|Ankang|Shangluo/i,"甘肃":/甘肃|Gansu|Kansu|兰州|嘉峪关|金昌|白银|天水|武威|张掖|平凉|酒泉|庆阳|定西|陇南|临夏|甘南|Lanzhou|Lanchow|Jiayuguan|Jinchang|Baiyin|Tianshui|Tienshui|Wuwei|Liangchow|Zhangye|Changyeh|Pingliang|Jiuquan|Chiuchuan|Qingyang|Dingxi|Longnan|Linxia|Gannan/i,"青海":/青海|Qinghai|Tsinghai|Chinghai|西宁|海东|海北|黄南|海南州|果洛|玉树|Xining|Sining|Haidong|Haibei|Huangnan|Hainanzhou|Guoluo|Yushu/i,"新疆":/新疆|Xinjiang|Sinkiang|维吾尔|乌鲁木齐|克拉玛依|吐鲁番|哈密|昌吉|博尔塔拉|巴音郭楞|阿克苏|克孜勒苏|喀什|和田|伊犁|塔城|阿勒泰|Urumqi|Urumchi|Wulumuqi|Karamay|Turpan|Tulufan|Hami|Changji|Bortala|Boertala|Bayingolin|Bayinguoleng|Aksu|Akesu|Kizilsu|Kashgar|Kashi|Hotan|Hetian|Khotan|Ili|Yili|Tacheng|Altay|Aletai/i,"宁夏":/宁夏|Ningxia|Ningsia|银川|石嘴山|吴忠|固原|中卫|Yinchuan|Shizuishan|Wuzhong|Guyuan|Zhongwei/i,"内蒙古":/内蒙古|内蒙|呼和浩特|包头|乌海|赤峰|通辽|鄂尔多斯|呼伦贝尔|巴彦淖尔|乌兰察布|兴安盟|锡林郭勒|阿拉善|蒙古语|Hohhot|Huhehaote|Kweisui|Kweihua|Suiyuan|Baotou|Paotow|Wuhai|Chifeng|Tongliao|Ordos|Eerduosi|Hulunbuir|Hulunbeier|Bayannur|Ulanqab|Wulanchabu|Hinggan|Xingan|Xilingol|Xilinguole|Alxa|Alasha/i};function detectProvince(n){for(const[e,a]of Object.entries(provincePatterns))if(a.test(n))return e;return"其他"}const T2S_PAIRS="電电廣广臺台灣湾國国語语樂乐聽听聞闻節节體体聯联頻频粵粤鳳凤閩闽衛卫娛娱兒儿時时藝艺經经綜综總总發发傳传興兴會会來来為为當当進进長长開开關关東东風风愛爱聲声話话見见號号陽阳業业際际與与個个們们無无網网訊讯線线頭头動动車车萬万問问題题員员點点書书學学習习導导親亲實实歡欢雲云務务優优麗丽農农濟济環环夢梦島岛億亿說说讀读寫写還还兩两橋桥飛飞遠远遊游運运龍龙馬马魚鱼鳥鸟畫画歷历參参義义專专權权產产術术設设備备試试驗验應应響响壓压質质標标達达請请項项紅红綠绿藍蓝純纯維维積积編编繼继給给結结絕绝視视報报後后廳厅銀银鐘钟論论錄录類类齊齐鬥斗頂顶",T2S_MAP={};for(let n=0;268>n;n+=2)T2S_MAP[T2S_PAIRS[n]]=T2S_PAIRS[n+1];function normalizeZh(n){return n.replace(/[\u4e00-\u9fff]/g,n=>T2S_MAP[n]||n)}function preprocessNewStations(n){n.forEach(n=>{const e=normalizeZh(n.name);if(n._nameLower=e.toLowerCase(),n._types=typeKeys.filter(n=>typePatterns[n].test(e)),"中国"===n.region){const a=detectProvince(e);"其他"!==a&&n._types.unshift(a)}0===n._types.length&&(n._types=["其他"])})}function preprocessStations(n){preprocessNewStations(allStations=n)}const BATCH_SIZE=100;let currentRegion="all",currentType="",currentSearch="",visibleCount=100,isLoading=!1,currentUrl="",currentStation=null,stationsFullyLoaded=!1,isManuallyStopped=!1,retryCount=0,retryStartTime=0,retryTimer=null,stallTimer=null;const MAX_RETRIES=30,MAX_RETRY_MS=6e5,STALL_TIMEOUT=15e3;let cachedStationTotal=0,currentLang="en",i18n={},showFavoritesOnly=!1,favorites=[];const SUPPORTED_LANGS=["zh-CN","zh-HK","en","es","fr","de","it","ja","ko","ru"],LABEL_KEYS={"中国":"china","日本":"japan","韩国":"korea","台湾":"taiwan","香港":"hongkong","新加坡":"singapore","英国":"uk","德国":"germany","法国":"france","意大利":"italy","西班牙":"spain","俄罗斯":"russia","美国":"usa","加拿大":"canada","澳大利亚":"australia","澳洲":"australia","新西兰":"newzealand","巴西":"brazil","墨西哥":"mexico","阿根廷":"argentina","瑞士":"switzerland","南非":"southafrica","葡萄牙":"portugal","马来西亚":"malaysia","奥地利":"austria","印度":"india","泰国":"thailand","越南":"vietnam","印尼":"indonesia","菲律宾":"philippines","土耳其":"turkey","荷兰":"netherlands","比利时":"belgium","波兰":"poland","瑞典":"sweden","挪威":"norway","丹麦":"denmark","芬兰":"finland","爱尔兰":"ireland","希腊":"greece","捷克":"czech","匈牙利":"hungary","罗马尼亚":"romania","埃及":"egypt","以色列":"israel","阿联酋":"uae","沙特":"saudi","其他":"other","全球":"global","乌克兰":"ukraine","白俄罗斯":"belarus","哈萨克斯坦":"kazakhstan","智利":"chile","哥伦比亚":"colombia","秘鲁":"peru","委内瑞拉":"venezuela","厄瓜多尔":"ecuador","澳门":"macau","巴基斯坦":"pakistan","孟加拉":"bangladesh","斯里兰卡":"srilanka","尼泊尔":"nepal","缅甸":"myanmar","柬埔寨":"cambodia","老挝":"laos","文莱":"brunei","卡塔尔":"qatar","科威特":"kuwait","巴林":"bahrain","阿曼":"oman","约旦":"jordan","黎巴嫩":"lebanon","叙利亚":"syria","伊拉克":"iraq","伊朗":"iran","阿富汗":"afghanistan","尼日利亚":"nigeria","摩洛哥":"morocco","肯尼亚":"kenya","加纳":"ghana","坦桑尼亚":"tanzania","埃塞俄比亚":"ethiopia","阿尔及利亚":"algeria","突尼斯":"tunisia","苏丹":"sudan","乌干达":"uganda","津巴布韦":"zimbabwe","纳米比亚":"namibia","博茨瓦纳":"botswana","赞比亚":"zambia","马达加斯加":"madagascar","音乐":"music","新闻":"news","综合":"general","交通":"traffic","体育":"sports","文艺":"arts","经典":"classic","儿童":"kids","宗教":"religion","古典":"classical","方言":"dialect","爵士":"jazz","流行":"pop","摇滚":"rock","嘻哈":"hiphop","电子":"electronic","R&B":"rnb","乡村":"country","民谣":"folk","蓝调":"blues","雷鬼":"reggae","金属":"metal","拉丁":"latin","财经":"finance","央广":"cnr","央视":"cctv","全国":"national","北京":"beijing","天津":"tianjin","上海":"shanghai","重庆":"chongqing","广东":"guangdong","广西":"guangxi","海南":"hainan","福建":"fujian","江苏":"jiangsu","浙江":"zhejiang","山东":"shandong","安徽":"anhui","江西":"jiangxi","湖南":"hunan","湖北":"hubei","河南":"henan","河北":"hebei","山西":"shanxi","辽宁":"liaoning","吉林":"jilin","黑龙江":"heilongjiang","四川":"sichuan","贵州":"guizhou","云南":"yunnan","西藏":"tibet","陕西":"shaanxi","甘肃":"gansu","青海":"qinghai","新疆":"xinjiang","宁夏":"ningxia","内蒙古":"innermongolia"},THEME_COLORS={green:"#22c55e",teal:"#14b8a6",cyan:"#06b6d4",orange:"#f97316",amber:"#f59e0b",rose:"#f43f5e",red:"#dc2626",pink:"#ec4899",purple:"#a855f7",indigo:"#6366f1",grayscale:"#888",bw:"#ddd",black:"#ffffff"},THEME_KEYS=["green","teal","cyan","orange","amber","rose","red","pink","purple","indigo","grayscale","bw","black"],LANG_OPTIONS=[{value:"zh-CN",flag:"cn",label:"简体中文"},{value:"zh-HK",flag:"hk",label:"繁體中文"},{value:"en",flag:"gb",label:"English"},{value:"es",flag:"es",label:"Español"},{value:"fr",flag:"fr",label:"Français"},{value:"de",flag:"de",label:"Deutsch"},{value:"it",flag:"it",label:"Italiano"},{value:"ja",flag:"jp",label:"日本語"},{value:"ko",flag:"kr",label:"한국어"},{value:"ru",flag:"ru",label:"Русский"}];function esc(n){return String(n).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#39;")}function isSameStation(n,e){return n&&e&&n.url===e.url&&n.name===e.name}let filteredCache=null;$(function(){function n(n){return favorites.some(e=>e.url===n.url&&e.name===n.name)}function e(){const n=favorites.length,e=d("favOnlyToggle")||"仅显示收藏";$("#favOnlyBtn").attr("title",n>0?e+" ("+n+")":e)}function a(){if(!currentStation)return void $("#miniFavBtn, #fullscreenFavBtn, #playerFavBtn").removeClass("fav-active");const e=n(currentStation);$("#miniFavBtn, #fullscreenFavBtn, #playerFavBtn").toggleClass("fav-active",e);const a=e?d("favRemove")||"取消收藏":d("favAdd")||"收藏";$("#miniFavBtn, #fullscreenFavBtn, #playerFavBtn").attr("title",a)}function t(n){const t=favorites.findIndex(e=>e.url===n.url&&e.name===n.name),l=0>t;l?favorites.push({name:n.name,url:n.url,logo:n.logo||null,region:n.region||"",country:n.country||""}):favorites.splice(t,1),localStorage.setItem("favorites",JSON.stringify(favorites)),e(),$(".station-fav-btn").each(function(){$(this).attr("data-url")===n.url&&$(this).attr("data-name")===n.name&&$(this).toggleClass("fav-active",l).attr("title",l?d("favRemove")||"取消收藏":d("favAdd")||"收藏")}),a(),showFavoritesOnly&&(filteredCache=null,i(),o())}function i(e=!1){if(!allStations.length&&!showFavoritesOnly)return void $("#resultCount").text(d(stationsFullyLoaded?"noStations":"loading"));e&&(filteredCache=null,visibleCount=100);const a=function(){if(filteredCache)return filteredCache;if(showFavoritesOnly){const n=[],e=new Set;return favorites.forEach(a=>{if(e.has(a.name))return;if(e.add(a.name),currentSearch&&!normalizeZh(a.name).toLowerCase().includes(currentSearch))return;const t=allStations.find(n=>n.url===a.url&&n.name===a.name);n.push(t||{name:a.name,url:a.url,logo:a.logo||null,region:a.region||"",country:a.country||"",_types:[],_nameLower:normalizeZh(a.name).toLowerCase(),_invalid:stationsFullyLoaded})}),filteredCache=n,n}const n=[],e=new Set;for(let a=0;allStations.length>a;a++){const t=allStations[a];e.has(t.name)||"all"!==currentRegion&&t.region!==currentRegion||currentSearch&&!t._nameLower.includes(currentSearch)||(""===currentType||t._types.includes(currentType))&&(e.add(t.name),n.push(t))}return filteredCache=n,n}(),t=a.slice(0,visibleCount);if(function(e){let a="";e.forEach(e=>{const t=n(e),i=!!e._invalid,o=e.logo&&"null"!==e.logo?`<img src="${e.logo}" class="station-logo" alt="" loading="lazy">`:`<div class="station-logo placeholder">${SVG_LOGO_SM}</div>`,l=(isSameStation(e,currentStation)&&(()=>{const n=document.getElementById("audioPlayer");return!!n.src&&n.src!==location.href&&!n.paused})()?" playing":"")+(i?" station-invalid":"");let r="";e.country&&(r=`<span class="region-tag"><img src="https://flagcdn.com/w20/${regionCodes[e.country]||"un"}.png" alt="" class="region-tag-flag"><span class="region-tag-name">${esc(m(e.country))}</span></span>`);const s=(e._types||[]).map(n=>`<span class="type-tag">${esc(m(n))}</span>`).join(""),u=i?`<span class="invalid-badge">⚠ ${d("invalidStation")||"失效"}</span>`:"";a+=`<div class="station-card${l}" data-url="${esc(e.url)}" data-name="${esc(e.name)}">\n                        ${o}\n                        <div class="station-content">\n                            <div class="station-name">${esc(e.name)}</div>\n                            <div class="station-meta">\n                                ${r}${s}${u}\n                            </div>\n                        </div>\n                        <div class="station-actions">\n                            <span class="playing-badge"><span class="playing-badge-dot"></span>${d("playerPlaying")||"正在播放"}</span>\n                            <button class="station-fav-btn${t?" fav-active":""}" data-url="${esc(e.url)}" data-name="${esc(e.name)}" title="${t?d("favRemove")||"取消收藏":d("favAdd")||"收藏"}">\n                                ${FAV_HEART_SVG}\n                            </button>\n                            <button class="station-play-btn" data-url="${esc(e.url)}" data-name="${esc(e.name)}" title="${d("playButton")||"播放"}">\n                                <svg viewBox="0 0 24 24"><polygon points="6,4 20,12 6,20"/></svg>\n                            </button>\n                            <button class="station-stop-btn" data-url="${esc(e.url)}" data-name="${esc(e.name)}" title="${d("pauseButton")||"暂停"}">\n                                <svg viewBox="0 0 24 24"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>\n                            </button>\n                        </div>\n                    </div>`}),$("#stationsGrid").html(a)}(t),showFavoritesOnly&&0===a.length){const n=0===favorites.length?d("noFavStations")||"没有收藏的电台":d("noStations")||"无匹配结果";return $("#stationsGrid").html(`<div class="no-results">${n}</div>`),$("#resultCount").text("0 / 0"),void $("#loadingMore").removeClass("show")}const i=stationsFullyLoaded?"":d("loadingSuffix");$("#resultCount").text(d("showingCount",{shown:t.length,total:a.length,suffix:i})),$("#loadingMore").toggleClass("show",a.length>visibleCount)}function o(){const n=function(){const n=[],e=new Set;if(showFavoritesOnly)return favorites.forEach(a=>{if(e.has(a.name))return;if(currentSearch&&!normalizeZh(a.name).toLowerCase().includes(currentSearch))return;e.add(a.name);const t=allStations.find(n=>n.url===a.url&&n.name===a.name);t&&n.push(t)}),n;for(let a=0;allStations.length>a;a++){const t=allStations[a];e.has(t.name)||"all"!==currentRegion&&t.region!==currentRegion||currentSearch&&!t._nameLower.includes(currentSearch)||(e.add(t.name),n.push(t))}return n}(),e={};n.forEach(n=>n._types.forEach(n=>{e[n]=(e[n]||0)+1}));const a=new Set(Object.keys(provincePatterns)),t=new Set(Object.keys(typePatterns)),i=["全国","央广","央视"];let o;if("中国"===currentRegion){const n=i.filter(n=>e[n]),l=Object.keys(e).filter(n=>a.has(n)&&!i.includes(n)&&"其他"!==n).sort((n,a)=>e[a]-e[n]),r=Object.keys(e).filter(n=>t.has(n)&&!i.includes(n)&&"其他"!==n).sort((n,a)=>e[a]-e[n]);o=[...n,...l,...r,...e["其他"]?["其他"]:[]]}else o=Object.keys(e).filter(n=>"其他"!==n&&!a.has(n)).sort((n,a)=>e[a]-e[n]),e["其他"]&&o.push("其他");let l=`<button class="type-btn ${""===currentType?"active":""}" data-type="">${d("all")}</button>`;o.forEach(n=>{l+=`<button class="type-btn ${currentType===n?"active":""}" data-type="${n}">${m(n)}(${e[n]})</button>`}),$("#typeBtns").html(l)}function l(){const n=new Set;let e=0;const a={},t={};allStations.forEach(i=>{n.has(i.name)||(n.add(i.name),e++),t[i.region]||(t[i.region]=new Set),t[i.region].has(i.name)||(t[i.region].add(i.name),a[i.region]=(a[i.region]||0)+1)}),$('.region-btn[data-region="all"]').data("count",e),Object.entries(a).forEach(([n,e])=>{$(`.region-btn[data-region="${n}"]`).data("count",e)}),cachedStationTotal=e,p(),$("#totalSubtitle").text(d("totalStations",{total:e}))}function r(n,e=!0){currentUrl=n.url,currentStation=n,retryCount=0,retryStartTime=0,clearTimeout(retryTimer),clearTimeout(stallTimer),localStorage.setItem("play_url",n.url),localStorage.setItem("play_name",n.name),a();const t=document.getElementById("audioPlayer");t.volume=parseFloat(localStorage.getItem("player_volume"))||.5,$("#playerTitle, #fullscreenTitle").text(n.name),function(){if(!currentStation)return;const n=currentStation.logo&&"null"!==currentStation.logo?currentStation.logo:null,e=$("#miniPlayerLogo");n?e.removeClass("placeholder").html('<img src="'+n+'" alt="">'):e.addClass("placeholder").html(SVG_LOGO_SM),$("#miniPlayerName").text(currentStation.name),W()}();const i=n.logo&&"null"!==n.logo?n.logo:null;$("#playerLogo").replaceWith(i?`<img src="${i}" class="player-logo" id="playerLogo" alt="">`:`<div class="player-logo placeholder" id="playerLogo">${SVG_LOGO_MD}</div>`),i?$("#fullscreenCover").replaceWith(`<img src="${i}" class="fullscreen-cover" id="fullscreenCover" alt="">`):$("#fullscreenCover").replaceWith(`<div class="fullscreen-cover placeholder" id="fullscreenCover">${SVG_LOGO_LG}</div>`),$(".station-card").removeClass("playing"),e&&$(".station-card").filter(function(){return $(this).attr("data-url")===n.url&&$(this).attr("data-name")===n.name}).addClass("playing"),e?($("#playerStatus, #fullscreenStatus").text(d("playerLoading")),$("#statusDot, #fullscreenDot").removeClass("playing paused"),t.src=n.url,t.play().catch(n=>{console.warn("自动播放被阻止，请点击播放条开始播放:",n),$("#playerStatus, #fullscreenStatus").text(d("playerClickToPlay")),$("#statusDot, #fullscreenDot").addClass("paused"),u(!0),P(!0)})):($("#playerStatus, #fullscreenStatus").text(d("playerClickToPlay")),$("#statusDot, #fullscreenDot").removeClass("playing").addClass("paused"),u(!0),P(!0)),h()}function s(){if(isManuallyStopped||!currentStation)return;clearTimeout(retryTimer),clearTimeout(stallTimer);const n=Date.now();if(0===retryCount&&(retryStartTime=n),retryCount>=30)return $("#playerStatus, #fullscreenStatus").text(d("playerError")),$("#miniPlayerStatus").text(d("playerError")),void console.warn("已达最大重试次数，停止重连");if(retryStartTime>0&&n-retryStartTime>=6e5)return $("#playerStatus, #fullscreenStatus").text(d("playerError")),$("#miniPlayerStatus").text(d("playerError")),void console.warn("超过最长重试时间 10 分钟，停止重连");retryCount++;const e=Math.min(3e4,3e3*Math.pow(2,Math.min(retryCount-1,3))),a=n-retryStartTime,t=Math.min(e,6e5-a),i=Math.round(t/1e3),o=`${d("playerRetrying")||"重连中"} (${retryCount}/30) … ${i}s`;$("#playerStatus, #fullscreenStatus").text(o),$("#miniPlayerStatus").text(o),console.warn(`音频重连 #${retryCount}，${i}s 后重试（已用 ${Math.round(a/1e3)}s）`),retryTimer=setTimeout(()=>{if(isManuallyStopped||!currentStation)return;if(Date.now()-retryStartTime>=6e5)return $("#playerStatus, #fullscreenStatus").text(d("playerError")),$("#miniPlayerStatus").text(d("playerError")),void console.warn("超过最长重试时间 10 分钟，停止重连");const n=document.getElementById("audioPlayer");n.src=currentStation.url,n.load(),n.play().catch(n=>console.warn("重试播放失败:",n))},t)}function u(n){const e=n?'<polygon points="6,4 20,12 6,20"/>':'<rect x="5" y="4" width="4" height="16"/><rect x="15" y="4" width="4" height="16"/>';$("#playIcon").html(e)}function c(){if(!currentStation)return;const n=document.getElementById("audioPlayer");n.volume=parseFloat(localStorage.getItem("player_volume"))||.5,n.src&&n.src!==window.location.href||(n.src=currentStation.url),n.paused?n.play().catch(n=>console.warn("播放失败:",n)):(isManuallyStopped=!0,retryCount=0,retryStartTime=0,clearTimeout(retryTimer),clearTimeout(stallTimer),n.pause(),n.src="",$("#statusDot, #fullscreenDot").removeClass("playing").addClass("paused"),$("#playerStatus, #fullscreenStatus").text(d("playerClickToPlay")),$("#soundWave").removeClass("playing"),$("#fullscreenCover").removeClass("playing"),u(!0),P(!0),$(".station-card").removeClass("playing"),A(!0),$("#miniPlayerStatus").text(d("playerPaused")||"已暂停"),document.title=d("appTitle"))}function g(){$("#regionFilterLabel").text("· "+("all"!==currentRegion?m(currentRegion):d("all"))),$("#typeFilterLabel").text("· "+(""!==currentType?m(currentType):d("all")))}function h(){const n=new URLSearchParams;"all"!==currentRegion&&n.set("region",currentRegion),""!==currentType&&n.set("type",currentType),showFavoritesOnly&&n.set("fav","1"),currentStation&&(n.set("play",currentStation.url),n.set("play_name",currentStation.name)),history.replaceState(null,"",n.toString()?"?"+n:location.pathname)}function d(n,e={}){return String(i18n[n]||n).replace(/\{\{(\w+)\}\}/g,(n,a)=>e[a]??"")}function m(n){const e=LABEL_KEYS[n]||n;return i18n.labels&&i18n.labels[e]?i18n.labels[e]:n}function p(){$(".region-btn").each(function(){const n=$(this).data("region"),e=$(this).data("count"),a=$(this).data("flag-code")||"un",t="all"===n?d("all"):m(n),i=void 0!==e?"("+e+")":"";$(this).html(`<img src="https://flagcdn.com/w20/${a}.png" alt="" class="region-flag"> ${t}${i}`)})}function y(n){return fetch(`lang/${n}.json`,{cache:"no-cache"}).then(n=>{if(!n.ok)throw new Error("Locale not found");return n.json()}).then(e=>{i18n=e,currentLang=n,function(){if($("[data-i18n]").each(function(){const n=$(this).data("i18n"),e=$(this),a=e.children().detach();e.text(d(n)),e.append(a)}),$("[data-i18n-placeholder]").each(function(){const n=$(this).data("i18n-placeholder");$(this).attr("placeholder",d(n))}),$("[data-i18n-title]").each(function(){const n=$(this).data("i18n-title");$(this).attr("title",d(n))}),stationsFullyLoaded?$("#totalSubtitle").text(d("totalStations",{total:cachedStationTotal})):$("#totalSubtitle").text(d("subtitleLoading")),function(){const n=THEME_KEYS.map(n=>{const e="bw"===n?"themeBW":"grayscale"===n?"themeGrayscale":"theme"+n.charAt(0).toUpperCase()+n.slice(1);return`<div class="theme-item${n===($("html").attr("data-theme")||"green")?" active":""}" data-theme="${n}"><span class="theme-item-dot" style="background:${THEME_COLORS[n]}"></span><span>${d(e)}</span></div>`}).join("");$("#themePanel").html(n)}(),f($("html").attr("data-theme")||"green"),S(currentLang),$("#themePickerBtn").attr("aria-label",d("theme")),$("#langPickerBtn").attr("aria-label",d("language")),g(),i(),p(),stationsFullyLoaded&&o(),currentStation){$("#playerTitle, #fullscreenTitle").text(currentStation.name),$("#miniPlayerName").text(currentStation.name),document.title=currentStation.name+" — "+d("appTitle");const n=document.getElementById("audioPlayer");let e,a;n.error?(e="playerError",a="playerError"):n.paused?(e="playerClickToPlay",a="playerPaused"):(e="playerPlaying",a="playerPlaying"),$("#playerStatus, #fullscreenStatus").text(d(e)),$("#miniPlayerStatus").text(d(a))}else document.title=d("appTitle"),$("#fullscreenStatus").text(d("playerWaiting"));a()}()}).catch(()=>{if("en"!==n)return y("en")})}function f(n){$("#themePickerDot").css("background",THEME_COLORS[n]||THEME_COLORS.green);const e="bw"===n?"themeBW":"grayscale"===n?"themeGrayscale":"theme"+n.charAt(0).toUpperCase()+n.slice(1);$("#themePickerLabel").text(d(e)),$("#themePanel .theme-item").removeClass("active").filter(`[data-theme="${n}"]`).addClass("active")}function S(n){const e=LANG_OPTIONS.find(e=>e.value===n)||LANG_OPTIONS[0];$("#langPickerFlag").attr("src",`https://flagcdn.com/w20/${e.flag}.png`),$("#langPickerLabel").text(e.label),$("#langPanel .lang-item").removeClass("active").filter(`[data-lang="${n}"]`).addClass("active")}function v(n){$("html").attr("data-theme",n),localStorage.setItem("theme",n),f(n)}let C;$("#stationsGrid").on("click",".station-card",function(){const n=$(this).attr("data-url"),e=$(this).attr("data-name"),a=allStations.find(e?a=>a.url===n&&a.name===e:e=>e.url===n);a&&r(a)}),$("#regionBtns").on("click",".region-btn",function(){var n;n=$(this).attr("data-region"),currentRegion=n,currentType="",localStorage.setItem("region",n),localStorage.removeItem("type"),$(".region-btn").removeClass("active"),$(`.region-btn[data-region="${n}"]`).addClass("active"),o(),i(!0),h(),g()}),$("#typeBtns").on("click",".type-btn",function(){var n;n=$(this).attr("data-type"),currentType=n,localStorage.setItem("type",n),o(),i(!0),h(),g()}),$("#favOnlyBtn").on("click",function(){var n;showFavoritesOnly=n=!showFavoritesOnly,localStorage.setItem("favOnly",n?"1":"0"),$("#favOnlyBtn").toggleClass("active",n),$("body").toggleClass("fav-only-mode",n),filteredCache=null,i(!0),o(),h()}),$("#stationsGrid").on("click",".station-fav-btn",function(n){n.stopPropagation();const e=$(this).attr("data-url"),a=$(this).attr("data-name");let i=allStations.find(a?n=>n.url===e&&n.name===a:n=>n.url===e);if(!i){const n=favorites.find(n=>n.url===e&&n.name===a);n&&(i={name:n.name,url:n.url,logo:n.logo||null,region:n.region||"",country:n.country||""})}i&&t(i)}),$("#miniFavBtn, #fullscreenFavBtn, #playerFavBtn").on("click",function(){currentStation&&t(currentStation)}),function(){const n=LANG_OPTIONS.map(n=>`<div class="lang-item${n.value===currentLang?" active":""}" data-lang="${n.value}"><img src="https://flagcdn.com/w20/${n.flag}.png" alt=""><span>${n.label}</span></div>`).join("");$("#langPanel").html(n)}(),S(currentLang),$("#themePickerBtn").on("click",function(n){n.stopPropagation();const e=$("#themePickerWrap"),a=e.hasClass("open");$(".picker-wrap").removeClass("open"),a||e.addClass("open")}),$("#langPickerBtn").on("click",function(n){n.stopPropagation();const e=$("#langPickerWrap"),a=e.hasClass("open");$(".picker-wrap").removeClass("open"),a||e.addClass("open")}),$(document).on("click",".theme-item",function(n){n.stopPropagation(),v($(this).data("theme")),$(".picker-wrap").removeClass("open")}),$(document).on("click",".lang-item",function(n){var e;n.stopPropagation(),e=$(this).data("lang"),SUPPORTED_LANGS.includes(e)&&(localStorage.setItem("language",e),y(e)),$(".picker-wrap").removeClass("open")}),$(document).on("click",".picker-panel",function(n){n.stopPropagation()}),$(document).on("click",function(){$(".picker-wrap").removeClass("open")}),$("#regionsToggle").on("click",function(){$("#regionsContent").toggleClass("collapsed"),$(this).find(".toggle-icon").toggleClass("collapsed")}),$("#typesToggle").on("click",function(){$("#typesContent").toggleClass("collapsed"),$(this).find(".toggle-icon").toggleClass("collapsed")}),$("#searchInput").on("input",function(){clearTimeout(C),C=setTimeout(()=>{currentSearch=normalizeZh($(this).val().toLowerCase()),i(!0),o()},300)}),function(){const n=document.getElementById("loadingMore");n&&new IntersectionObserver(function(n){n[0].isIntersecting&&(isLoading||(isLoading=!0,visibleCount+=100,i(),isLoading=!1))},{rootMargin:"200px"}).observe(n)}(),$("#audioPlayer").on("play",function(){retryCount=0,retryStartTime=0,clearTimeout(retryTimer),clearTimeout(stallTimer),$("#statusDot, #fullscreenDot").removeClass("paused").addClass("playing"),$("#playerStatus, #fullscreenStatus").text(d("playerPlaying")),A(!1),$("#miniPlayerStatus").text(d("playerPlaying")),$("#soundWave").addClass("playing"),$("#fullscreenCover").addClass("playing"),u(!1),P(!1),currentStation&&($(".station-card").removeClass("playing").filter(function(){return $(this).attr("data-url")===currentStation.url&&$(this).attr("data-name")===currentStation.name}).addClass("playing"),document.title=currentStation.name+" — "+d("appTitle"))}).on("pause",function(){$("#statusDot, #fullscreenDot").removeClass("playing").addClass("paused"),$("#playerStatus, #fullscreenStatus").text(d("playerClickToPlay")),$("#soundWave").removeClass("playing"),$("#fullscreenCover").removeClass("playing"),u(!0),P(!0),$(".station-card").removeClass("playing"),A(!0),$("#miniPlayerStatus").text(d("playerPaused")||"已暂停"),document.title=d("appTitle")}).on("volumechange",function(){localStorage.setItem("player_volume",this.volume),w(this.volume)}).on("stalled waiting",function(){isManuallyStopped||(clearTimeout(stallTimer),stallTimer=setTimeout(()=>{const n=document.getElementById("audioPlayer");!n.paused&&3>n.readyState&&s()},15e3))}).on("playing",function(){clearTimeout(stallTimer)}).on("error",function(){isManuallyStopped?isManuallyStopped=!1:($("#statusDot, #fullscreenDot").removeClass("playing").addClass("paused"),$("#soundWave").removeClass("playing"),$("#fullscreenCover").removeClass("playing"),u(!0),P(!0),$(".station-card").removeClass("playing"),W(),console.error("音频加载错误，准备重试"),s())}),$("#playerBar").on("click",function(n){$(n.target).closest("audio, .fullscreen-btn, .volume-control").length||c()});const b={mute:'<polygon points="11,5 6,9 2,9 2,15 6,15 11,19"/><line x1="23" y1="9" x2="17" y2="15" stroke-linecap="round"/><line x1="17" y1="9" x2="23" y2="15" stroke-linecap="round"/>',low:'<polygon points="11,5 6,9 2,9 2,15 6,15 11,19"/><path d="M15.54,8.46a5,5,0,0,1,0,7.07"/>',med:'<polygon points="11,5 6,9 2,9 2,15 6,15 11,19"/><path d="M15.54,8.46a5,5,0,0,1,0,7.07"/><path d="M19.07,4.93a10,10,0,0,1,0,14.14"/>',high:'<polygon points="11,5 6,9 2,9 2,15 6,15 11,19"/><path d="M15.54,8.46a5,5,0,0,1,0,7.07"/><path d="M19.07,4.93a10,10,0,0,1,0,14.14"/>'};function w(n){const e=Math.round(100*n),a=e+"%",t=function(n){return 0===n?b.mute:.35>n?b.low:.7>n?b.med:b.high}(n);$("#barVolumeIcon, #miniVolumeIcon, #fsVolumeIcon").html(t),$(".volume-slider-input").val(e).each(function(){this.style.setProperty("--vol",e+"%")}),$(".volume-label").text(a)}function k(n,e,a,t){a.stopPropagation();const i=$("#"+e),o=i.hasClass("show");if($(".volume-popup").removeClass("show"),o)return;i.parent().is("body")||$("body").append(i);const l=document.getElementById(n).getBoundingClientRect();let r=l.left+l.width/2-22,s=l.top-132-8-(t||0);r=Math.max(8,Math.min(r,window.innerWidth-44-8)),8>s&&(s=l.bottom+8),i.css({top:s+"px",left:r+"px"}),i.addClass("show")}$("#barVolumeBtn").on("click",function(n){k("barVolumeBtn","barVolumePopup",n)}),$("#miniVolumeBtn").on("click",function(n){k("miniVolumeBtn","miniVolumePopup",n,4)}),$("#fsVolumeBtn").on("click",function(n){k("fsVolumeBtn","fsVolumePopup",n)});let T=null;function P(n){const e=n?'<polygon points="8,5 19,12 8,19"/>':'<rect x="7" y="5" width="3" height="14"/><rect x="14" y="5" width="3" height="14"/>';$("#fullscreenPlayBtn svg").html(e)}$(document).on("mouseenter.volHide","#barVolumeBtn, #barVolumePopup, #miniVolumeBtn, #miniVolumePopup, #fsVolumeBtn, #fsVolumePopup",function(){clearTimeout(T)}),$(document).on("mouseleave.volHide","#barVolumeBtn, #barVolumePopup, #miniVolumeBtn, #miniVolumePopup, #fsVolumeBtn, #fsVolumePopup",function(){clearTimeout(T),T=setTimeout(function(){$(".volume-popup").removeClass("show")},1500)}),$("#barVolumeSlider, #miniVolumeSlider, #fsVolumeSlider").on("input",function(){var n;n=parseInt(this.value,10),document.getElementById("audioPlayer").volume=Math.max(0,Math.min(1,n/100))}),$(".volume-popup").on("click",function(n){n.stopPropagation()}),$(document).on("click.volumeClose",function(){clearTimeout(T),$(".volume-popup").removeClass("show")}),w(document.getElementById("audioPlayer").volume);let x=!1,L=null,z=!1,B=!1,E=!1,H=null;async function _(){if("wakeLock"in navigator)try{H=await navigator.wakeLock.request("screen"),H.addEventListener("release",()=>{H=null})}catch(n){}}function F(){H&&(H.release(),H=null)}function O(){$("#fullscreenPlayer").addClass("show"),function(){if(!currentStation)return;$("#fullscreenTitle").text(currentStation.name);const n=currentStation.logo&&"null"!==currentStation.logo?currentStation.logo:null;n?$("#fullscreenCover").replaceWith(`<img src="${n}" class="fullscreen-cover" id="fullscreenCover" alt="">`):$("#fullscreenCover").replaceWith(`<div class="fullscreen-cover placeholder" id="fullscreenCover">${SVG_LOGO_LG}</div>`),document.getElementById("audioPlayer").paused||($("#fullscreenCover").addClass("playing"),$("#soundWave").addClass("playing"))}();const n=window.scrollY;$("body").css({overflow:"hidden",position:"fixed",top:-n+"px",width:"100%"}),$("body").data("scroll-y",n),_(),R(),L||(L=setInterval(R,1e3))}function I(){$("#fullscreenPlayer").removeClass("show");const n=$("body").data("scroll-y")||0;$("body").css({overflow:"",position:"",top:"",width:""}),window.scrollTo(0,n),x=!1,E=!1,F(),L&&(clearInterval(L),L=null),(document.fullscreenElement||document.webkitFullscreenElement||document.mozFullScreenElement)&&(document.exitFullscreen||document.webkitExitFullscreen||document.mozCancelFullScreen||function(){}).call(document)}function M(){const n=document.documentElement,e=n.requestFullscreen||n.webkitRequestFullscreen||n.mozRequestFullScreen||n.msRequestFullscreen;e&&e.call(n).catch(()=>{})}function j(){const n=window.matchMedia("(max-width: 900px) and (orientation: landscape) and (max-height: 500px) and (hover: none) and (pointer: coarse)").matches;if(n&&!z)B=!1,$("#fullscreenPlayer").hasClass("show")||(x=!0,O()),E=!0,setTimeout(function(){!$("#fullscreenPlayer").hasClass("show")||document.fullscreenElement||document.webkitFullscreenElement||M()},300);else if(!n&&(B=!1,E=!1,$("#fullscreenPlayer").hasClass("show")&&x)){x=!1,$("#fullscreenPlayer").removeClass("show");const n=$("body").data("scroll-y")||0;$("body").css({overflow:"",position:"",top:"",width:""}),window.scrollTo(0,n),F(),(document.fullscreenElement||document.webkitFullscreenElement||document.mozFullScreenElement)&&(document.exitFullscreen||document.webkitExitFullscreen||document.mozCancelFullScreen||function(){}).call(document)}z=n}function R(){const n=new Date,e=String(n.getHours()).padStart(2,"0"),a=String(n.getMinutes()).padStart(2,"0"),t=String(n.getSeconds()).padStart(2,"0");$("#fullscreenClock").text(e+":"+a+":"+t)}function W(){currentStation?$("#miniPlayer").addClass("has-station"):$("#miniPlayer").removeClass("has-station")}function A(n){const e=n?'<polygon points="6,4 20,12 6,20"/>':'<rect x="5" y="4" width="4" height="16"/><rect x="15" y="4" width="4" height="16"/>';$("#miniPlayIcon").html(e)}document.addEventListener("visibilitychange",function(){"visible"===document.visibilityState&&$("#fullscreenPlayer").hasClass("show")&&_()}),$("#fullscreenPlayer").on("touchstart.fspend pointerdown.fspend",function(){E&&$("#fullscreenPlayer").hasClass("show")&&!document.fullscreenElement&&!document.webkitFullscreenElement&&(E=!1,M())}),$("#fullscreenBtn").on("click",function(n){n.stopPropagation(),x=!1,B=!1,O(),setTimeout(()=>M(),100)}),$("#fullscreenClose").on("click",function(){B=!0,I()}),$("#fullscreenPlayBtn").on("click",c),$(document).on("keydown",function(n){"Escape"===n.key&&$("#fullscreenPlayer").hasClass("show")&&(B=!0,I())}),$(window).on("orientationchange resize",j),$(document).on("fullscreenchange webkitfullscreenchange mozfullscreenchange",function(){if(!document.fullscreenElement&&!document.webkitFullscreenElement&&!document.mozFullScreenElement&&$("#fullscreenPlayer").hasClass("show")){B=!0,x=!1,$("#fullscreenPlayer").removeClass("show");const n=$("body").data("scroll-y")||0;$("body").css({overflow:"",position:"",top:"",width:""}),window.scrollTo(0,n),F()}}),j(),$(window).on("scroll.backtotop",function(){$(this).scrollTop()>300?($("#miniPlayer").addClass("show"),$("body").addClass("mini-player-visible")):($("#miniPlayer").removeClass("show"),$("body").removeClass("mini-player-visible"))}),$("#backToTop").on("click",function(){$("html, body").animate({scrollTop:0},300)}),$("#miniPlayBtn").on("click",function(){c()}),$("#miniFullscreenBtn").on("click",function(n){n.stopPropagation(),x=!1,B=!1,O(),setTimeout(()=>M(),100)}),$(document).on("click",".station-play-btn",function(n){n.stopPropagation();const e=$(this).attr("data-url"),a=$(this).attr("data-name"),t=allStations.find(a?n=>n.url===e&&n.name===a:n=>n.url===e);t&&r(t)}),$(document).on("click",".station-stop-btn",function(n){n.stopPropagation();const e=document.getElementById("audioPlayer");isManuallyStopped=!0,retryCount=0,retryStartTime=0,clearTimeout(retryTimer),clearTimeout(stallTimer),e.pause(),e.src="",currentUrl="",W(),a(),i(),currentStation&&($("#playerStatus").text(d("playerClickToPlay")||"点击播放"),$("#miniPlayerStatus").text(d("playerPaused")||"已暂停"),$("#fullscreenStatus").text(d("playerClickToPlay")||"点击播放")),$("#statusDot").removeClass("playing").addClass("paused"),$("#fullscreenDot").removeClass("playing").addClass("paused"),$("#soundWave").removeClass("playing"),$("#fullscreenCover").removeClass("playing"),u(!0),P(!0),A(!0)}),function(){const n=localStorage.getItem("language"),e=navigator.languages&&navigator.languages[0]||navigator.language||"en",a=e.startsWith("zh-TW")||e.startsWith("zh-HK")||e.startsWith("zh-MO")?"zh-HK":e.startsWith("zh")?"zh-CN":e.startsWith("es")?"es":e.startsWith("fr")?"fr":e.startsWith("de")?"de":e.startsWith("it")?"it":e.startsWith("ja")?"ja":e.startsWith("ko")?"ko":e.startsWith("ru")?"ru":"en",t=("zh-TW"===n?"zh-HK":n)||a,i=SUPPORTED_LANGS.includes(t)?t:"en";return n&&n===i||localStorage.setItem("language",i),y(i)}().then(()=>{v(localStorage.getItem("theme")||"green"),function(){const n=new URLSearchParams(location.search),a=n.get("region"),t=n.get("type"),s=localStorage.getItem("region")||"all",u=localStorage.getItem("type")||"";!function(){try{const n=localStorage.getItem("favorites");n&&(favorites=JSON.parse(n)),Array.isArray(favorites)||(favorites=[])}catch(n){favorites=[]}}(),e();const c=n.get("fav"),m=localStorage.getItem("favOnly");("1"===c||!c&&"1"===m)&&(showFavoritesOnly=!0,$("#favOnlyBtn").addClass("active"),$("body").addClass("fav-only-mode"),"1"===c&&localStorage.setItem("favOnly","1")),currentRegion=a||s,"all"!==currentRegion&&($(".region-btn").removeClass("active"),$(`.region-btn[data-region="${currentRegion}"]`).addClass("active")),currentType=t||(s===currentRegion?u:""),a&&(localStorage.setItem("region",currentRegion),t||localStorage.removeItem("type")),t&&localStorage.setItem("type",currentType),a||currentRegion===s||localStorage.setItem("region",currentRegion),t||currentType===u||""===currentType||localStorage.setItem("type",currentType),600>window.innerWidth&&($("#typesContent").addClass("collapsed"),$("#typesToggle").find(".toggle-icon").addClass("collapsed")),g(),$("#resultCount").text(d("loading")),(async()=>{try{const a=await fetch("?action=stations");if(!a.ok)throw new Error("HTTP "+a.status);const t=a.body.getReader(),s=new TextDecoder,u=1e3,c=<?php echo $totalCount; ?>;let g="",m=[],p=!1;const y=$("#topProgressFill"),f=n=>{const e=c>0?Math.min(98,Math.round(n/c*100)):0;y.css("width",e+"%"),$("#totalSubtitle").text(d("loadingStations",{loaded:n.toLocaleString()}))},S=()=>{p||(p=!0,requestAnimationFrame(()=>{p=!1,filteredCache=null,i()}))},v=()=>{m.length&&(preprocessNewStations(m),m.forEach(n=>allStations.push(n)),m=[],f(allStations.length),S())};for(;;){const{done:a,value:c}=await t.read();if(c){g+=s.decode(c,{stream:!0});const n=g.split("\n");g=n.pop();for(const e of n)if(e.trim())try{m.push(JSON.parse(e))}catch(n){}u>m.length||v()}if(a){if(g.trim())try{m.push(JSON.parse(g.trim()))}catch(n){}v(),stationsFullyLoaded=!0,filteredCache=null,i(!0),o(),l(),e(),y.addClass("done");const a=n.get("play")||localStorage.getItem("play_url");if(a){const e=n.get("play_name")||localStorage.getItem("play_name");let t=e?allStations.find(n=>n.url===a&&n.name===e):null;t||(t=allStations.find(n=>n.url===a)),t&&(r(t,!1),n.get("play")||h())}break}}}catch(n){$("#resultCount").text(d("loadFailedRefresh"))}})()}()})});</script>
</body>
</html>