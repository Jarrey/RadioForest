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
                if (!empty($url) && strpos($url, '#') !== 0 && strpos($url, 'http') === 0) {
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
        $raw = [
            'China' => '中国', 'Japan' => '日本', 'South Korea' => '韩国', 'The Republic Of Korea' => '韩国', 'Korea' => '韩国', 'Taiwan' => '台湾', 'Taiwan, Republic Of China' => '台湾', 'Republic Of China' => '台湾',
            'Hong Kong' => '香港', 'Singapore' => '新加坡',
            'United Kingdom' => '英国', 'The United Kingdom of Great Britain and Northern Ireland' => '英国', 'The United Kingdom' => '英国', 'Great Britain' => '英国', 'Britain' => '英国', 'England' => '英国', 'Scotland' => '英国', 'Wales' => '英国', 'Northern Ireland' => '英国',
            'Germany' => '德国', 'France' => '法国', 'Italy' => '意大利', 'Spain' => '西班牙',
            'Russia' => '俄罗斯',
            'United States Of America' => '美国', 'The United States Of America' => '美国',
            'United States' => '美国', 'America' => '美国', 'USA' => '美国', 'US' => '美国', 'U.S.A.' => '美国', 'U.S.' => '美国',
            'Canada' => '加拿大',
            'Australia' => '澳大利亚', 'New Zealand' => '新西兰', 'Brazil' => '巴西',
            'Mexico' => '墨西哥', 'Argentina' => '阿根廷', 'India' => '印度', 'Thailand' => '泰国',
            'Vietnam' => '越南', 'Malaysia' => '马来西亚', 'Indonesia' => '印尼',
            'Philippines' => '菲律宾', 'Saudi Arabia' => '沙特', 'Turkey' => '土耳其',
            'Netherlands' => '荷兰', 'Belgium' => '比利时', 'Switzerland' => '瑞士',
            'Austria' => '奥地利', 'Poland' => '波兰', 'Sweden' => '瑞典', 'Norway' => '挪威',
            'Denmark' => '丹麦', 'Finland' => '芬兰', 'Ireland' => '爱尔兰', 'Portugal' => '葡萄牙',
            'Greece' => '希腊', 'Czech' => '捷克', 'Hungary' => '匈牙利', 'Romania' => '罗马尼亚',
            'South Africa' => '南非', 'Egypt' => '埃及', 'Israel' => '以色列', 'UAE' => '阿联酋',
            'UK' => '英国', 'GB' => '英国', 'CH' => '瑞士', 'NL' => '荷兰', 'SE' => '瑞典',
            'NO' => '挪威', 'DK' => '丹麦', 'FI' => '芬兰', 'PL' => '波兰',
            'AT' => '奥地利', 'BE' => '比利时', 'PT' => '葡萄牙', 'GR' => '希腊',
            'CZ' => '捷克', 'HU' => '匈牙利', 'RO' => '罗马尼亚', 'UA' => '乌克兰',
            'BY' => '白俄罗斯', 'KZ' => '哈萨克斯坦', 'CL' => '智利',
            'CO' => '哥伦比亚', 'PE' => '秘鲁', 'VE' => '委内瑞拉', 'EC' => '厄瓜多尔',
            'KR' => '韩国', 'JP' => '日本', 'IN' => '印度', 'PK' => '巴基斯坦',
            'BD' => '孟加拉', 'LK' => '斯里兰卡', 'NP' => '尼泊尔', 'MM' => '缅甸',
            'KH' => '柬埔寨', 'LA' => '老挝', 'BN' => '文莱', 'MY' => '马来西亚',
            'TW' => '台湾', 'HK' => '香港', 'MO' => '澳门', 'PH' => '菲律宾',
            'TH' => '泰国', 'ID' => '印尼', 'SG' => '新加坡', 'VN' => '越南',
            'SA' => '沙特', 'AE' => '阿联酋', 'QA' => '卡塔尔', 'KW' => '科威特',
            'BH' => '巴林', 'OM' => '阿曼', 'JO' => '约旦', 'LB' => '黎巴嫩',
            'SY' => '叙利亚', 'IQ' => '伊拉克', 'IR' => '伊朗', 'AF' => '阿富汗',
            'NG' => '尼日利亚', 'MA' => '摩洛哥', 'KE' => '肯尼亚',
            'GH' => '加纳', 'TZ' => '坦桑尼亚', 'ET' => '埃塞俄比亚', 'DZ' => '阿尔及利亚',
            'TN' => '突尼斯', 'SD' => '苏丹', 'UG' => '乌干达', 'ZW' => '津巴布韦',
            'NA' => '纳米比亚', 'BW' => '博茨瓦纳', 'ZM' => '赞比亚', 'MG' => '马达加斯加',
        ];
        // 预构建全小写键的映射，后续查找为 O(1)
        $lowerMap = [];
        foreach ($raw as $k => $v) $lowerMap[strtolower($k)] = $v;
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
    return rename($tmpFile, $cacheFile);
}

$dir = __DIR__;
$files = glob($dir . '/radio_*.m3u');
$cacheFile = $dir . '/stations.cache.json';
$allStations = [];
$countries = [];

$regionNames = [
    'cn' => '中国', 'jp' => '日本', 'kr' => '韩国', 'tw' => '台湾', 'hk' => '香港',
    'sg' => '新加坡', 'gb' => '英国', 'de' => '德国', 'fr' => '法国', 'it' => '意大利',
    'es' => '西班牙', 'ru' => '俄罗斯', 'us' => '美国', 'ca' => '加拿大', 'au' => '澳大利亚',
    'nz' => '新西兰', 'br' => '巴西', 'mx' => '墨西哥', 'ar' => '阿根廷', 'ch' => '瑞士',
    'za' => '南非', 'in' => '印度', 'th' => '泰国', 'vn' => '越南', 'my' => '马来西亚',
    'id' => '印尼', 'ph' => '菲律宾', 'tr' => '土耳其', 'nl' => '荷兰', 'be' => '比利时',
    'at' => '奥地利', 'pl' => '波兰', 'se' => '瑞典', 'no' => '挪威', 'dk' => '丹麦',
    'fi' => '芬兰', 'ie' => '爱尔兰', 'pt' => '葡萄牙', 'gr' => '希腊', 'cz' => '捷克',
    'hu' => '匈牙利', 'ro' => '罗马尼亚', 'eg' => '埃及', 'il' => '以色列', 'ae' => '阿联酋',
    'sa' => '沙特', '' => '全球'
];

$cached = loadStationCache($cacheFile, $files);
if ($cached !== null) {
    $allStations = $cached;
} else {
    foreach ($files as $file) {
        $stations = parseM3U($file);
        $basename = basename($file, '.m3u');
        $region = $basename === 'radio' ? '' : str_replace('radio_', '', $basename);
        $regionName = $regionNames[$region] ?? $region;

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
    saveStationCache($cacheFile, $files, $allStations);
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
    <title>电台森林</title>
    <!-- 尽早设置主题，避免闪烁 -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'green';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <link rel="icon" type="image/svg+xml" href="radio-icon.svg">
    <style>
        :root {
            --primary: #22c55e;
            --primary-dim: #166534;
            --bg: #121212;
            --bg-card: #1a1a1a;
            --bg-input: #1a1a1a;
            --border: #333;
            --border-light: #444;
            --text: #e8e8e8;
            --text-dim: #777;
            --text-dimmer: #555;
            --tag-bg: #252525;
            --type-bg: #1e3a5f;
            --type-text: #60a5fa;
            --player-bg: linear-gradient(135deg, #0f1a0f 0%, #0a150a 100%);
            --player-border: #22c55e;
            --player-shadow: rgba(34, 197, 94, 0.15);
        }
        
        [data-theme="orange"] {
            --primary: #f97316;
            --primary-dim: #9a3412;
            --bg: #121212;
            --bg-card: #1a1815;
            --bg-input: #1a1815;
            --border: #333;
            --border-light: #443830;
            --text: #e8e8e8;
            --text-dim: #777;
            --text-dimmer: #555;
            --tag-bg: #2a231a;
            --type-bg: #4a300a;
            --type-text: #fbbf24;
            --player-bg: linear-gradient(135deg, #1a1408 0%, #0f0a04 100%);
            --player-border: #f97316;
            --player-shadow: rgba(249, 115, 22, 0.15);
        }
        
        [data-theme="red"] {
            --primary: #dc2626;
            --primary-dim: #991b1b;
            --bg: #121212;
            --bg-card: #1a1515;
            --bg-input: #1a1515;
            --border: #333;
            --border-light: #442828;
            --text: #e8e8e8;
            --text-dim: #777;
            --text-dimmer: #555;
            --tag-bg: #2a1a1a;
            --type-bg: #4a0a0a;
            --type-text: #f87171;
            --player-bg: linear-gradient(135deg, #1a0808 0%, #0f0404 100%);
            --player-border: #dc2626;
            --player-shadow: rgba(220, 38, 38, 0.15);
        }
        
        [data-theme="blue"] {
            --primary: #3b82f6;
            --primary-dim: #1d4ed8;
            --bg: #121212;
            --bg-card: #151a1f;
            --bg-input: #151a1f;
            --border: #333;
            --border-light: #2a3544;
            --text: #e8e8e8;
            --text-dim: #777;
            --text-dimmer: #555;
            --tag-bg: #1a2530;
            --type-bg: #0a1a3a;
            --type-text: #60a5fa;
            --player-bg: linear-gradient(135deg, #0a1525 0%, #050a10 100%);
            --player-border: #3b82f6;
            --player-shadow: rgba(59, 130, 246, 0.15);
        }
        
        [data-theme="purple"] {
            --primary: #a855f7;
            --primary-dim: #7e22ce;
            --bg: #121212;
            --bg-card: #1a1520;
            --bg-input: #1a1520;
            --border: #333;
            --border-light: #3a2a44;
            --text: #e8e8e8;
            --text-dim: #777;
            --text-dimmer: #555;
            --tag-bg: #251a2a;
            --type-bg: #2a0a3a;
            --type-text: #c084fc;
            --player-bg: linear-gradient(135deg, #150a1a 0%, #0a0510 100%);
            --player-border: #a855f7;
            --player-shadow: rgba(168, 85, 247, 0.15);
        }
        
        [data-theme="teal"] {
            --primary: #14b8a6;
            --primary-dim: #0f766e;
            --bg: #121212;
            --bg-card: #152020;
            --bg-input: #152020;
            --border: #333;
            --border-light: #1a3030;
            --text: #e8e8e8;
            --text-dim: #777;
            --text-dimmer: #555;
            --tag-bg: #1a2828;
            --type-bg: #0a2020;
            --type-text: #2dd4bf;
            --player-bg: linear-gradient(135deg, #0a1515 0%, #050a0a 100%);
            --player-border: #14b8a6;
            --player-shadow: rgba(20, 184, 166, 0.15);
        }
        
        [data-theme="cyan"] {
            --primary: #06b6d4;
            --primary-dim: #0891b2;
            --bg: #121212;
            --bg-card: #151d21;
            --bg-input: #151d21;
            --border: #333;
            --border-light: #1a2a30;
            --text: #e8e8e8;
            --text-dim: #777;
            --text-dimmer: #555;
            --tag-bg: #1a252a;
            --type-bg: #0a1a20;
            --type-text: #22d3ee;
            --player-bg: linear-gradient(135deg, #081515 0%, #040a0a 100%);
            --player-border: #06b6d4;
            --player-shadow: rgba(6, 182, 212, 0.15);
        }
        
        [data-theme="amber"] {
            --primary: #f59e0b;
            --primary-dim: #d97706;
            --bg: #121212;
            --bg-card: #1f1a12;
            --bg-input: #1f1a12;
            --border: #333;
            --border-light: #3a3020;
            --text: #e8e8e8;
            --text-dim: #777;
            --text-dimmer: #555;
            --tag-bg: #2a2015;
            --type-bg: #201a08;
            --type-text: #fbbf24;
            --player-bg: linear-gradient(135deg, #150f08 0%, #0a0804 100%);
            --player-border: #f59e0b;
            --player-shadow: rgba(245, 158, 11, 0.15);
        }
        
        [data-theme="rose"] {
            --primary: #f43f5e;
            --primary-dim: #e11d48;
            --bg: #121212;
            --bg-card: #1f1518;
            --bg-input: #1f1518;
            --border: #333;
            --border-light: #3a2025;
            --text: #e8e8e8;
            --text-dim: #777;
            --text-dimmer: #555;
            --tag-bg: #2a181d;
            --type-bg: #200a10;
            --type-text: #fb7185;
            --player-bg: linear-gradient(135deg, #15080c 0%, #0a0406 100%);
            --player-border: #f43f5e;
            --player-shadow: rgba(244, 63, 94, 0.15);
        }
        
        [data-theme="pink"] {
            --primary: #ec4899;
            --primary-dim: #db2777;
            --bg: #121212;
            --bg-card: #1f151d;
            --bg-input: #1f151d;
            --border: #333;
            --border-light: #3a2030;
            --text: #e8e8e8;
            --text-dim: #777;
            --text-dimmer: #555;
            --tag-bg: #2a1822;
            --type-bg: #200a15;
            --type-text: #f472b6;
            --player-bg: linear-gradient(135deg, #150a10 0%, #0a0508 100%);
            --player-border: #ec4899;
            --player-shadow: rgba(236, 72, 153, 0.15);
        }
        
        [data-theme="indigo"] {
            --primary: #6366f1;
            --primary-dim: #4f46e5;
            --bg: #121212;
            --bg-card: #161822;
            --bg-input: #161822;
            --border: #333;
            --border-light: #202030;
            --text: #e8e8e8;
            --text-dim: #777;
            --text-dimmer: #555;
            --tag-bg: #1a1828;
            --type-bg: #0a0a20;
            --type-text: #818cf8;
            --player-bg: linear-gradient(135deg, #0a0a15 0%, #050508 100%);
            --player-border: #6366f1;
            --player-shadow: rgba(99, 102, 241, 0.15);
        }
        
        [data-theme="black"] {
            --primary: #ffffff;
            --primary-dim: #888888;
            --bg: #000000;
            --bg-card: #0a0a0a;
            --bg-input: #0a0a0a;
            --border: #222;
            --border-light: #333;
            --text: #ffffff;
            --text-dim: #888;
            --text-dimmer: #555;
            --tag-bg: #111;
            --type-bg: #222;
            --type-text: #ccc;
            --player-bg: linear-gradient(135deg, #111 0%, #000 100%);
            --player-border: #333;
            --player-shadow: rgba(255, 255, 255, 0.05);
        }

        [data-theme="bw"] {
            --primary: #ffffff;
            --primary-dim: #cccccc;
            --bg: #000000;
            --bg-card: #000000;
            --bg-input: #000000;
            --border: #ffffff;
            --border-light: #ffffff;
            --text: #ffffff;
            --text-dim: #cccccc;
            --text-dimmer: #999999;
            --tag-bg: #000000;
            --type-bg: #000000;
            --type-text: #ffffff;
            --player-bg: #000000;
            --player-border: #ffffff;
            --player-shadow: rgba(255, 255, 255, 0.2);
        }

        [data-theme="grayscale"] {
            --primary: #888888;
            --primary-dim: #666666;
            --bg: #1a1a1a;
            --bg-card: #222222;
            --bg-input: #222222;
            --border: #444;
            --border-light: #555;
            --text: #e0e0e0;
            --text-dim: #999;
            --text-dimmer: #666;
            --tag-bg: #2a2a2a;
            --type-bg: #333;
            --type-text: #aaa;
            --player-bg: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%);
            --player-border: #666;
            --player-shadow: rgba(136, 136, 136, 0.15);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            min-height: 100%;
            overflow-x: hidden;
            max-width: 100vw;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            transition: background 0.3s, color 0.3s;
        }
        
        .container {
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
            padding: 24px 16px;
            overflow-x: hidden;
        }
        
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 0 8px;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo {
            filter: drop-shadow(0 0 8px var(--player-shadow));
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ── 主题 / 语言选择器 ─────────────────────────────────── */
        .picker-wrap {
            position: relative;
        }
        .picker-btn {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 6px 10px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            color: var(--text);
            font-size: 12px;
            cursor: pointer;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
            white-space: nowrap;
            user-select: none;
            line-height: 1;
        }
        .picker-btn:hover {
            border-color: var(--primary);
            background: color-mix(in srgb, var(--primary) 8%, var(--bg-card));
        }
        .picker-wrap.open > .picker-btn {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px color-mix(in srgb, var(--primary) 22%, transparent);
        }
        .picker-dot {
            width: 11px;
            height: 11px;
            border-radius: 50%;
            flex-shrink: 0;
            border: 1.5px solid rgba(255,255,255,0.2);
        }
        .picker-flag {
            width: 18px;
            height: 13px;
            border-radius: 2px;
            object-fit: cover;
            flex-shrink: 0;
        }
        .picker-caret {
            width: 8px;
            height: 5px;
            flex-shrink: 0;
            fill: var(--text-dim);
            transition: transform 0.2s;
        }
        .picker-wrap.open .picker-caret {
            transform: rotate(180deg);
        }
        .picker-label {
            font-size: 12px;
        }
        /* Dropdown panel */
        .picker-panel {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            box-shadow: 0 8px 28px rgba(0,0,0,0.5);
            z-index: 2000;
            overflow: hidden;
            visibility: hidden;
            opacity: 0;
            pointer-events: none;
            transform: translateY(-6px);
            transition: opacity 0.15s, transform 0.15s, visibility 0s 0.15s;
        }
        .picker-wrap.open .picker-panel {
            visibility: visible;
            opacity: 1;
            pointer-events: all;
            transform: translateY(0);
            transition: opacity 0.15s, transform 0.15s;
        }
        /* Theme swatch grid */
        #themePanel {
            padding: 8px;
            width: 210px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3px;
        }
        .theme-item {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 6px 8px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.15s;
            font-size: 12px;
            color: var(--text);
            border: 1px solid transparent;
        }
        .theme-item:hover {
            background: color-mix(in srgb, var(--primary) 12%, transparent);
        }
        .theme-item.active {
            background: color-mix(in srgb, var(--primary) 18%, transparent);
            border-color: color-mix(in srgb, var(--primary) 55%, transparent);
        }
        .theme-item-dot {
            width: 13px;
            height: 13px;
            border-radius: 50%;
            flex-shrink: 0;
            border: 1.5px solid rgba(255,255,255,0.18);
        }
        /* Language list */
        #langPanel {
            padding: 6px;
            min-width: 152px;
        }
        .lang-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.15s;
            font-size: 13px;
            color: var(--text);
            border: 1px solid transparent;
            white-space: nowrap;
        }
        .lang-item:hover {
            background: color-mix(in srgb, var(--primary) 12%, transparent);
        }
        .lang-item.active {
            background: color-mix(in srgb, var(--primary) 18%, transparent);
            border-color: color-mix(in srgb, var(--primary) 55%, transparent);
        }
        .lang-item img {
            width: 20px;
            height: 14px;
            border-radius: 2px;
            object-fit: cover;
            flex-shrink: 0;
        }
        /* Mobile: hide text label, keep icon + caret */
        @media (max-width: 500px) {
            .picker-label { display: none; }
            .picker-btn { padding: 7px 9px; gap: 4px; }
        }
        
        h1 {
            font-size: 22px;
            font-weight: 200;
            color: #fff;
            letter-spacing: 3px;
            margin-bottom: 4px;
        }
        
        .subtitle { color: var(--text-dim); font-size: 12px; }
        #topProgressBar {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 3px;
            z-index: 9999;
            pointer-events: none;
        }
        #topProgressFill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--primary), color-mix(in srgb, var(--primary) 60%, #fff));
            border-radius: 0 2px 2px 0;
            transition: width 0.3s ease;
            box-shadow: 0 0 8px color-mix(in srgb, var(--primary) 70%, transparent);
        }
        #topProgressFill.done {
            width: 100% !important;
            transition: width 0.2s ease, opacity 0.5s ease 0.3s;
            opacity: 0;
        }
        
        .search-box {
            max-width: 400px;
            margin: 0 auto 16px;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 16px;
            border-radius: 24px;
            border: 1px solid var(--border);
            background: var(--bg-input);
            color: var(--text);
            font-size: 14px;
            outline: none;
            transition: all 0.2s;
        }
        
        .search-box input:focus {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .search-box input::placeholder { color: var(--text-dimmer); }
        
        .filter-section {
            margin-bottom: 16px;
        }
        
        .filter-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            padding: 6px 8px;
            cursor: pointer;
            user-select: none;
            border-radius: 8px;
            transition: background 0.2s;
        }
        
        .filter-header:hover {
            background: var(--bg-card);
        }
        
        .filter-header h3 {
            font-size: 14px;
            font-weight: 500;
            color: var(--text);
            margin: 0;
        }
        
        .toggle-icon {
            font-size: 10px;
            color: var(--primary);
            transition: transform 0.3s ease;
            display: inline-block;
            width: 12px;
            text-align: center;
        }
        
        .toggle-icon.collapsed {
            transform: rotate(-90deg);
        }
        
        .filter-content {
            overflow: hidden;
            transition: max-height 0.4s ease, opacity 0.3s ease;
            max-height: 2000px;
            opacity: 1;
        }
        
        .filter-content.collapsed {
            max-height: 0;
            opacity: 0;
        }
        
        .regions {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 6px;
        }
        
        .region-btn {
            padding: 5px 10px;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text);
            font-size: 13px;
            cursor: pointer;
            transition: all 0.15s;
            font-weight: 400;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .region-flag {
            width: 18px;
            height: 12px;
            object-fit: cover;
            border-radius: 2px;
            flex-shrink: 0;
        }
        
        .region-btn:hover { background: var(--border-light); color: var(--text); }
        
        .region-btn.active {
            background: var(--primary);
            color: #000;
            border-color: var(--primary);
            font-weight: 500;
        }
        
        .types {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 5px;
            padding: 10px 12px 6px;
            border: none;
            border-radius: 12px;
            background: color-mix(in srgb, var(--primary) 15%, transparent);
        }
        
        .type-btn {
            padding: 4px 10px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text);
            font-size: 11px;
            cursor: pointer;
            transition: all 0.15s;
            font-weight: 400;
        }
        
        .type-btn:hover { background: var(--border-light); color: var(--text); }
        
        .type-btn.active {
            background: var(--primary);
            color: #000;
            border-color: var(--primary);
            font-weight: 500;
        }
        
        .player-bar {
            background: var(--player-bg);
            border-radius: 20px;
            padding: 24px 32px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 24px;
            border: 1px solid var(--player-border);
            box-shadow: 0 0 30px var(--player-shadow), 0 4px 20px rgba(0,0,0,0.4);
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
            flex-wrap: wrap;
            cursor: pointer;
        }
        
        .player-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, var(--player-shadow), transparent);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .player-logo {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            object-fit: cover;
            background: var(--bg-card);
            flex-shrink: 0;
            border: 2px solid var(--primary);
            box-shadow: 0 0 12px var(--player-shadow);
        }
        
        .player-logo.placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-card);
            border: 2px solid var(--primary);
        }
        
        .player-info { flex: 1; min-width: 0; }
        
.player-title {
            font-size: 17px;
            font-weight: 500;
            color: var(--text);
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .player-status {
            font-size: 11px;
            color: var(--text-dim);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--text-dimmer);
        }
        
        .status-dot.playing {
            background: var(--primary);
            animation: pulse 1.5s infinite;
        }

        .status-dot.paused {
            background: var(--text-dim);
            animation: pulse-dim 1.5s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; box-shadow: 0 0 4px var(--primary); }
            50% { opacity: 0.6; box-shadow: 0 0 8px var(--primary); }
        }

        @keyframes pulse-dim {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        
        audio {
            height: 32px;
            flex-shrink: 0;
            filter: invert(0.8);
            /* 阻止点击 audio 冒泡到 player-bar 时不要触发 togglePlay */
            position: relative;
            z-index: 2;
        }
        
        .fullscreen-btn {
            width: 40px;
            height: 40px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            transition: all 0.2s;
            flex-shrink: 0;
            z-index: 2;
        }
        
        .fullscreen-btn:hover {
            background: var(--primary);
            color: var(--bg);
            transform: scale(1.05);
        }
        
        .fullscreen-btn svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        .result-count {
            text-align: center;
            color: var(--text-dim);
            font-size: 12px;
            margin: 8px 0;
            padding: 6px 0;
        }
        
        .stations-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 8px;
        }
        
        .station-card {
            background: var(--bg-card);
            border-radius: 8px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.15s ease;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
            max-width: 100%;
            overflow: hidden;
        }
        
        .station-card:hover {
            background: var(--border-light);
            border-color: var(--border-light);
        }
        
        .station-card.active {
            background: var(--bg-card);
            border-color: var(--primary);
        }
        
        .station-logo {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            object-fit: cover;
            background: var(--bg);
            flex-shrink: 0;
        }
        
        .station-logo.placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .station-content {
            flex: 1;
            min-width: 0;
        }
        
        .station-name {
            font-size: 13px;
            font-weight: 400;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .station-meta {
            font-size: 11px;
            color: var(--text-dim);
            margin-top: 2px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            overflow: hidden;
        }
        
        .region-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 1px 6px;
            border-radius: 4px;
            background: var(--tag-bg);
            font-size: 10px;
            flex-shrink: 0;
            white-space: nowrap;
        }
        
        .region-tag-flag {
            width: 14px;
            height: 10px;
            object-fit: cover;
            border-radius: 1px;
            flex-shrink: 0;
        }
        
        .region-tag-name {
            color: var(--primary-dim);
        }
        
        .type-tag {
            padding: 1px 6px;
            border-radius: 4px;
            background: var(--type-bg);
            font-size: 10px;
            color: var(--type-text);
            flex-shrink: 0;
            white-space: nowrap;
        }
        
        .loading-more {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 12px;
            display: none;
        }
        
        .loading-more.show { display: block; }
        
        .no-results {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        @media (max-width: 600px) {
            .container {
                padding: 16px 8px;
                width: 100%;
            }
            
            h1 {
                font-size: 18px;
                letter-spacing: 2px;
            }
            
            .subtitle {
                font-size: 11px;
            }
            
            .logo {
                width: 36px;
                height: 31px;
            }
            
            .stations-list { 
                grid-template-columns: 1fr;
                gap: 6px;
                width: 100%;
            }
            
            .station-card {
                padding: 8px;
                gap: 8px;
                width: 100%;
                box-sizing: border-box;
            }
            
            .station-logo {
                width: 36px;
                height: 36px;
                border-radius: 6px;
            }
            
            .station-logo.placeholder {
                font-size: 16px;
            }
            
            .station-content {
                min-width: 0;
                flex: 1;
                overflow: hidden;
            }
            
            .station-name {
                font-size: 12px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            
            .station-meta {
                font-size: 10px;
                gap: 4px;
                flex-wrap: wrap;
            }
            
            .region-tag {
                padding: 1px 4px;
                font-size: 9px;
                gap: 3px;
            }
            
            .region-tag-flag {
                width: 12px;
                height: 8px;
            }
            
            .type-tag {
                padding: 1px 4px;
                font-size: 9px;
            }
            
            .player-bar { 
                flex-wrap: wrap;
                padding: 10px;
            }
            
            .player-logo {
                width: 44px;
                height: 44px;
            }
            
            .player-title {
                font-size: 15px;
            }
            
            audio { 
                width: 100%; 
                order: 3; 
                margin-top: 8px; 
            }
            
            .fullscreen-btn {
                order: 2;
                width: 36px;
                height: 36px;
            }
            
            .fullscreen-btn svg {
                width: 18px;
                height: 18px;
            }
            
            .region-btn, .type-btn {
                font-size: 12px;
                padding: 4px 8px;
            }
            
            .filter-header h3 {
                font-size: 13px;
            }
        }
        
        @media (max-width: 400px) {
            .container {
                padding: 12px 6px;
                width: 100%;
            }
            
            .station-card {
                padding: 6px;
                gap: 6px;
                width: 100%;
            }
            
            .station-logo {
                width: 32px;
                height: 32px;
            }
            
            .station-name {
                font-size: 11px;
            }
            
            .station-meta {
                font-size: 9px;
                gap: 3px;
            }
            
            .region-tag, .type-tag {
                font-size: 8px;
                padding: 0 3px;
            }
            
            .region-tag {
                gap: 2px;
            }
            
            .region-tag-flag {
                width: 10px;
                height: 7px;
            }
        }

        /* mini 播放条显示时，给 container 增加底部间距，防止最后一张卡片被遮挡 */
        body.mini-player-visible .container {
            padding-bottom: 80px;
        }
        
        /* 全屏播放器 */
        .fullscreen-player {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: var(--bg);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            box-sizing: border-box;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            overflow: hidden;
        }
        
        .fullscreen-player.show {
            opacity: 1;
            visibility: visible;
        }
        
        /* 音波正弦波背景 */
        .wave-background {
            position: absolute;
            top: 10%;
            left: 0;
            width: 100%;
            height: 80%;
            overflow: hidden;
            opacity: 0.28;
            pointer-events: none;
        }
        
        .wave-line {
            position: absolute;
            width: 100%;
            height: 100%;
        }
        
        .wave-line svg {
            position: absolute;
            width: 200%;
            height: 100%;
        }
        
        .fullscreen-player.show .wave-line:nth-child(1) svg {
            animation: wave-move 12s linear infinite;
        }
        
        .fullscreen-player.show .wave-line:nth-child(2) svg {
            animation: wave-move 10s linear infinite;
            animation-delay: -3s;
        }
        
        .fullscreen-player.show .wave-line:nth-child(3) svg {
            animation: wave-move 13s linear infinite;
            animation-delay: -2s;
        }
        
        .fullscreen-player.show .wave-line:nth-child(4) svg {
            animation: wave-move 16s linear infinite;
            animation-delay: -9s;
        }
        
        .fullscreen-player.show .wave-line:nth-child(5) svg {
            animation: wave-move 11s linear infinite;
            animation-delay: -4s;
        }
        
        @keyframes wave-move {
            from { transform: translateX(-50%); }
            to { transform: translateX(0%); }
        }
        
        .fullscreen-top-bar {
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 10;
        }
        
        .fullscreen-clock {
            font-size: 15px;
            font-weight: 500;
            color: var(--primary);
            opacity: 0.7;
            letter-spacing: 1px;
            font-variant-numeric: tabular-nums;
        }
        
        .fullscreen-close {
            width: 44px;
            height: 44px;
            background: color-mix(in srgb, var(--primary) 15%, transparent);
            border: 1px solid color-mix(in srgb, var(--primary) 40%, transparent);
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            transition: all 0.2s;
        }
        
        .fullscreen-close svg {
            width: 20px;
            height: 20px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        
        .fullscreen-close:hover {
            background: var(--primary);
            color: var(--bg);
            transform: scale(1.1);
        }
        
        /* 悬浮小播放条 */
        .mini-player {
            position: fixed;
            bottom: 12px;
            left: 12px;
            right: 12px;
            transform: translateY(calc(100% + 12px));
            background: color-mix(in srgb, var(--bg) 55%, transparent);
            border: 1px solid var(--player-border);
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.35), 0 0 14px var(--player-shadow);
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px 8px 12px;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s, transform 0.3s;
            z-index: 998;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        .mini-player.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .mini-player-logo {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
            background: var(--bg-card);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .mini-player-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }
        .mini-player-logo.placeholder {
            background: color-mix(in srgb, var(--primary) 15%, var(--bg-card));
        }
        .mini-player-info {
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }
        .mini-player-name {
            font-size: 0.85em;
            font-weight: 600;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .mini-player-status {
            font-size: 0.72em;
            color: var(--primary);
            opacity: 0.8;
            margin-top: 1px;
        }
        .mini-player-btn {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: none;
            background: color-mix(in srgb, var(--primary) 18%, transparent);
            color: var(--primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: background 0.2s, transform 0.15s;
        }
        .mini-player-btn:hover {
            background: color-mix(in srgb, var(--primary) 30%, transparent);
            transform: scale(1.1);
        }
        .mini-player-btn svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }
        .mini-media {
            display: none;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }
        .mini-player.has-station .mini-media {
            display: flex;
        }
        .mini-player.has-station #backToTop {
            border-left: 1px solid color-mix(in srgb, var(--border) 50%, transparent);
            margin-left: 2px;
        }
        
        /* 正在播放的卡片标记 */
        .station-card.playing {
            border-color: var(--primary);
            background: color-mix(in srgb, var(--primary) 8%, var(--bg-card));
        }
        
        .station-card.playing .station-name {
            color: var(--primary);
            font-weight: 500;
        }
        
        .station-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        .playing-badge {
            display: none;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            color: var(--primary);
            white-space: nowrap;
        }
        
        .station-card.playing .playing-badge {
            display: inline-flex;
        }
        
        .playing-badge-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--primary);
            animation: pulse 1.5s infinite;
        }

        .station-play-btn,
        .station-stop-btn {
            width: 28px;
            height: 28px;
            background: color-mix(in srgb, var(--primary) 20%, transparent);
            border: 1px solid var(--primary);
            border-radius: 6px;
            cursor: pointer;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            flex-shrink: 0;
            transition: all 0.15s;
        }

        .station-play-btn {
            display: flex;
        }

        .station-stop-btn {
            display: none;
        }
        
        .station-card.playing .station-play-btn {
            display: none;
        }

        .station-card.playing .station-stop-btn {
            display: flex;
        }
        
        .station-play-btn:hover,
        .station-stop-btn:hover {
            background: var(--primary);
            color: #000;
        }
        
        .station-play-btn svg,
        .station-stop-btn svg {
            width: 14px;
            height: 14px;
            fill: currentColor;
        }
        
        .fullscreen-cover {
            width: 280px;
            height: 280px;
            border-radius: 20px;
            object-fit: cover;
            background: var(--bg-card);
            border: 4px solid var(--primary);
            box-shadow: 0 20px 60px rgba(0,0,0,0.4), 0 0 40px var(--player-shadow);
            margin-bottom: 40px;
            position: relative;
        }
        
        .fullscreen-cover.placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 120px;
            color: var(--primary);
        }
        
        .fullscreen-title {
            font-size: 32px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 12px;
            text-align: center;
            max-width: 80%;
        }
        
        .fullscreen-status {
            font-size: 16px;
            color: var(--text-dim);
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .fullscreen-controls {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .fullscreen-control-btn {
            width: 64px;
            height: 64px;
            background: var(--bg-card);
            border: 2px solid var(--primary);
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            transition: all 0.2s;
        }
        
        .fullscreen-control-btn:hover {
            background: var(--primary);
            color: var(--bg);
            transform: scale(1.1);
        }
        
        .fullscreen-control-btn.play-pause {
            width: 80px;
            height: 80px;
        }
        
        .fullscreen-control-btn svg {
            width: 32px;
            height: 32px;
            fill: currentColor;
        }
        
        .fullscreen-control-btn.play-pause svg {
            width: 40px;
            height: 40px;
        }
        
        /* 音波动画 */
        .sound-wave {
            display: flex;
            align-items: center;
            gap: 4px;
            height: 40px;
            position: absolute;
            bottom: -60px;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .sound-wave-bar {
            width: 4px;
            background: var(--primary);
            border-radius: 2px;
            opacity: 0.6;
        }
        
        .sound-wave.playing .sound-wave-bar {
            animation: wave 1.2s ease-in-out infinite;
        }
        
        .sound-wave-bar:nth-child(1) { animation-delay: 0s; }
        .sound-wave-bar:nth-child(2) { animation-delay: 0.1s; }
        .sound-wave-bar:nth-child(3) { animation-delay: 0.2s; }
        .sound-wave-bar:nth-child(4) { animation-delay: 0.3s; }
        .sound-wave-bar:nth-child(5) { animation-delay: 0.4s; }
        
        @keyframes wave {
            0%, 100% { height: 10px; }
            50% { height: 35px; }
        }
        
        .fullscreen-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        
        .fullscreen-info {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        /* 横屏模式优化 - 水平布局 */
        @media (max-width: 900px) and (orientation: landscape) {
            .fullscreen-player {
                padding: 15px 30px;
            }
            
            .fullscreen-content {
                flex-direction: row;
                gap: 30px;
                align-items: center;
                justify-content: center;
                width: 100%;
            }
            
            .fullscreen-cover {
                width: 140px;
                height: 140px;
                margin-bottom: 0;
                flex-shrink: 0;
            }
            
            .fullscreen-cover.placeholder {
                font-size: 50px;
            }
            
            .fullscreen-info {
                align-items: flex-start;
                flex: 1;
                max-width: 400px;
            }
            
            .fullscreen-title {
                font-size: 18px;
                margin-bottom: 6px;
                text-align: left;
                max-width: 100%;
            }
            
            .fullscreen-status {
                font-size: 12px;
                margin-bottom: 12px;
            }
            
            .fullscreen-controls {
                margin-top: 0;
            }
            
            .fullscreen-control-btn {
                width: 48px;
                height: 48px;
            }
            
            .fullscreen-control-btn.play-pause {
                width: 56px;
                height: 56px;
            }
            
            .fullscreen-control-btn svg {
                width: 20px;
                height: 20px;
            }
            
            .fullscreen-control-btn.play-pause svg {
                width: 24px;
                height: 24px;
            }
            
            .sound-wave {
                bottom: -30px;
                height: 25px;
            }
            
            .fullscreen-close {
                width: 36px;
                height: 36px;
                top: 15px;
                right: 15px;
            }
            
            .fullscreen-close svg {
                width: 16px;
                height: 16px;
            }
        }
        
        @media (max-width: 600px) {
            .fullscreen-player {
                padding: 20px;
            }
            
            .fullscreen-cover {
                width: 220px;
                height: 220px;
                margin-bottom: 30px;
            }
            
            .fullscreen-cover.placeholder {
                font-size: 80px;
            }
            
            .fullscreen-title {
                font-size: 24px;
                max-width: 90%;
            }
            
            .fullscreen-status {
                font-size: 14px;
                margin-bottom: 30px;
            }
            
            .fullscreen-control-btn {
                width: 52px;
                height: 52px;
            }
            
            .fullscreen-control-btn.play-pause {
                width: 68px;
                height: 68px;
            }
            
            .fullscreen-control-btn svg {
                width: 24px;
                height: 24px;
            }
            
            .fullscreen-control-btn.play-pause svg {
                width: 32px;
                height: 32px;
            }
        }
    </style>
</head>
<body>
    <!-- 顶部加载进度条 -->
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
                <!-- 主题选择器 -->
                <div class="picker-wrap" id="themePickerWrap">
                    <button class="picker-btn" id="themePickerBtn" type="button" aria-label="主题">
                        <span class="picker-dot" id="themePickerDot" style="background:#22c55e"></span>
                        <span class="picker-label" id="themePickerLabel">翠绿</span>
                        <svg class="picker-caret" viewBox="0 0 8 5" xmlns="http://www.w3.org/2000/svg"><path d="M0 0l4 5 4-5z"/></svg>
                    </button>
                    <div class="picker-panel" id="themePanel"></div>
                </div>
                <!-- 语言选择器 -->
                <div class="picker-wrap" id="langPickerWrap">
                    <button class="picker-btn" id="langPickerBtn" type="button" aria-label="语言">
                        <img class="picker-flag" id="langPickerFlag" src="https://flagcdn.com/w20/cn.png" alt="">
                        <span class="picker-label" id="langPickerLabel">简体中文</span>
                        <svg class="picker-caret" viewBox="0 0 8 5" xmlns="http://www.w3.org/2000/svg"><path d="M0 0l4 5 4-5z"/></svg>
                    </button>
                    <div class="picker-panel" id="langPanel"></div>
                </div>
            </div>
        </header>
        
        <div class="search-box">
            <input type="text" id="searchInput" data-i18n-placeholder="searchPlaceholder" placeholder="搜索电台...">
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
            $regionCodes = [
                '中国' => 'cn', '日本' => 'jp', '韩国' => 'kr', '台湾' => 'tw', '香港' => 'hk',
                '新加坡' => 'sg', '英国' => 'gb', '德国' => 'de', '法国' => 'fr', '意大利' => 'it',
                '西班牙' => 'es', '俄罗斯' => 'ru', '美国' => 'us', '加拿大' => 'ca', '澳大利亚' => 'au',
                '澳洲' => 'au', '新西兰' => 'nz', '巴西' => 'br', '墨西哥' => 'mx', '阿根廷' => 'ar',
                '瑞士' => 'ch', '南非' => 'za', '印度' => 'in', '泰国' => 'th', '越南' => 'vn',
                '马来西亚' => 'my', '印尼' => 'id', '菲律宾' => 'ph', '土耳其' => 'tr',
                '荷兰' => 'nl', '比利时' => 'be', '奥地利' => 'at', '波兰' => 'pl',
                '瑞典' => 'se', '挪威' => 'no', '丹麦' => 'dk', '芬兰' => 'fi',
                '爱尔兰' => 'ie', '葡萄牙' => 'pt', '希腊' => 'gr', '捷克' => 'cz',
                '匈牙利' => 'hu', '罗马尼亚' => 'ro', '埃及' => 'eg', '以色列' => 'il',
                '阿联酋' => 'ae', '沙特' => 'sa', '其他' => 'un',
            ];
            $regionOrder = ['中国', '日本', '韩国', '台湾', '香港', '新加坡', '美国', '加拿大', '墨西哥', '巴西', '阿根廷', '英国', '德国', '法国', '意大利', '西班牙', '瑞士', '俄罗斯', '澳洲', '新西兰', '南非', '其他'];
            foreach ($regionOrder as $r):
                if (isset($countries[$r])):
                    $code = $regionCodes[$r] ?? 'un';
                    $flagImg = '<img src="https://flagcdn.com/w20/' . $code . '.png" alt="" class="region-flag">';
            ?>
                <button class="region-btn" data-region="<?php echo $r; ?>" data-flag-code="<?php echo $code; ?>" data-count="<?php echo $countries[$r]; ?>"><?php echo $flagImg . ' ' . $r; ?>(<?php echo $countries[$r]; ?>)</button>
            <?php endif; endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 分类筛选（中国地区含省份/央广/央视） -->
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
            <div class="player-logo placeholder" id="playerLogo"><svg viewBox="0 0 60 52" width="36" height="31"><line x1="46" y1="3" x2="39" y2="13" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round"/><rect x="3" y="12" width="54" height="36" rx="7" fill="var(--primary)"/><rect x="7" y="17" width="25" height="26" rx="4" fill="var(--bg)" opacity="0.1"/><line x1="9" y1="22" x2="30" y2="22" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><line x1="9" y1="26" x2="30" y2="26" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><line x1="9" y1="30" x2="30" y2="30" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><line x1="9" y1="34" x2="30" y2="34" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><line x1="9" y1="38" x2="30" y2="38" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><rect x="36" y="16" width="17" height="8" rx="2" fill="var(--bg)" opacity="0.22"/><line x1="45" y1="17.5" x2="45" y2="23.5" stroke="var(--bg)" stroke-width="1.5" stroke-linecap="round" opacity="0.6"/><circle cx="41" cy="33" r="5" fill="var(--bg)" opacity="0.2"/><circle cx="41" cy="33" r="2.5" fill="var(--bg)" opacity="0.5"/><circle cx="53" cy="33" r="5" fill="var(--bg)" opacity="0.2"/><circle cx="53" cy="33" r="2.5" fill="var(--bg)" opacity="0.5"/></svg></div>
            <div class="player-info">
                <div class="player-title" id="playerTitle" data-i18n="selectStationToPlay">选择一个电台开始播放</div>
                <div class="player-status">
                    <span class="status-dot" id="statusDot"></span>
                    <span id="playerStatus" data-i18n="playerWaiting">等待中</span>
                </div>
            </div>
            <audio controls id="audioPlayer"></audio>
            <button class="fullscreen-btn" id="fullscreenBtn" data-i18n-title="fullscreen" title="全屏播放">
                <svg viewBox="0 0 24 24">
                    <path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>
                </svg>
            </button>
        </div>
        
        <p class="result-count" id="resultCount" data-i18n="loading">加载中...</p>

        <div class="stations-list" id="stationsGrid"></div>
        
        <div class="loading-more" id="loadingMore" data-i18n="loadMore">加载更多...</div>
    </div>
    
    <!-- 悬浮小播放条（含回到顶部） -->
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
            <button class="mini-player-btn" id="miniFullscreenBtn" data-i18n-title="fullscreen" title="全屏播放">
                <svg viewBox="0 0 24 24"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>
            </button>
        </div>
        <button class="mini-player-btn" id="backToTop" data-i18n-title="backToTop" title="回到顶部">
            <svg viewBox="0 0 24 24"><path d="M12 4l-8 8h5v8h6v-8h5z"/></svg>
        </button>
    </div>

    <!-- 全屏播放器 -->
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
            <button class="fullscreen-close" id="fullscreenClose">
                <svg viewBox="0 0 24 24">
                    <polyline points="4 14 10 14 10 20"/>
                    <polyline points="20 10 14 10 14 4"/>
                    <line x1="14" y1="10" x2="21" y2="3"/>
                    <line x1="3" y1="21" x2="10" y2="14"/>
                </svg>
            </button>
        </div>
        
        <div class="fullscreen-content">
            <div class="fullscreen-cover placeholder" id="fullscreenCover">
                <svg viewBox="0 0 60 52" width="100" height="87"><line x1="46" y1="3" x2="39" y2="13" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round"/><rect x="3" y="12" width="54" height="36" rx="7" fill="var(--primary)"/><rect x="7" y="17" width="25" height="26" rx="4" fill="var(--bg)" opacity="0.1"/><line x1="9" y1="22" x2="30" y2="22" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><line x1="9" y1="26" x2="30" y2="26" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><line x1="9" y1="30" x2="30" y2="30" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><line x1="9" y1="34" x2="30" y2="34" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><line x1="9" y1="38" x2="30" y2="38" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><rect x="36" y="16" width="17" height="8" rx="2" fill="var(--bg)" opacity="0.22"/><line x1="45" y1="17.5" x2="45" y2="23.5" stroke="var(--bg)" stroke-width="1.5" stroke-linecap="round" opacity="0.6"/><circle cx="41" cy="33" r="5" fill="var(--bg)" opacity="0.2"/><circle cx="41" cy="33" r="2.5" fill="var(--bg)" opacity="0.5"/><circle cx="53" cy="33" r="5" fill="var(--bg)" opacity="0.2"/><circle cx="53" cy="33" r="2.5" fill="var(--bg)" opacity="0.5"/></svg>
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
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // ─── 国家代码映射（用于国旗显示）───────────────────────────────────────
        const regionCodes = {
            '中国': 'cn', '日本': 'jp', '韩国': 'kr', '台湾': 'tw', '香港': 'hk',
            '新加坡': 'sg', '英国': 'gb', '德国': 'de', '法国': 'fr', '意大利': 'it',
            '西班牙': 'es', '俄罗斯': 'ru', '美国': 'us', '加拿大': 'ca', '澳大利亚': 'au',
            '澳洲': 'au', '新西兰': 'nz', '巴西': 'br', '墨西哥': 'mx', '阿根廷': 'ar',
            '瑞士': 'ch', '南非': 'za', '印度': 'in', '泰国': 'th', '越南': 'vn',
            '马来西亚': 'my', '印尼': 'id', '菲律宾': 'ph', '土耳其': 'tr',
            '荷兰': 'nl', '比利时': 'be', '奥地利': 'at', '波兰': 'pl',
            '瑞典': 'se', '挪威': 'no', '丹麦': 'dk', '芬兰': 'fi',
            '爱尔兰': 'ie', '葡萄牙': 'pt', '希腊': 'gr', '捷克': 'cz',
            '匈牙利': 'hu', '罗马尼亚': 'ro', '埃及': 'eg', '以色列': 'il',
            '阿联酋': 'ae', '沙特': 'sa', '其他': 'un'
        };

        // ─── 电台默认图标 SVG（适配主题色）──────────────────────────────────────
        const SVG_RADIO_SM = '<svg viewBox="0 0 60 52" width="26" height="22"><line x1="46" y1="3" x2="39" y2="13" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round"/><rect x="3" y="12" width="54" height="36" rx="7" fill="var(--primary)"/><rect x="7" y="17" width="25" height="26" rx="4" fill="var(--bg)" opacity="0.1"/><line x1="9" y1="22" x2="30" y2="22" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><line x1="9" y1="26" x2="30" y2="26" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><line x1="9" y1="30" x2="30" y2="30" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><line x1="9" y1="34" x2="30" y2="34" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><line x1="9" y1="38" x2="30" y2="38" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><rect x="36" y="16" width="17" height="8" rx="2" fill="var(--bg)" opacity="0.22"/><line x1="45" y1="17.5" x2="45" y2="23.5" stroke="var(--bg)" stroke-width="1.5" stroke-linecap="round" opacity="0.6"/><circle cx="41" cy="33" r="5" fill="var(--bg)" opacity="0.2"/><circle cx="41" cy="33" r="2.5" fill="var(--bg)" opacity="0.5"/><circle cx="53" cy="33" r="5" fill="var(--bg)" opacity="0.2"/><circle cx="53" cy="33" r="2.5" fill="var(--bg)" opacity="0.5"/></svg>';
        const SVG_RADIO_MD = '<svg viewBox="0 0 60 52" width="36" height="31"><line x1="46" y1="3" x2="39" y2="13" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round"/><rect x="3" y="12" width="54" height="36" rx="7" fill="var(--primary)"/><rect x="7" y="17" width="25" height="26" rx="4" fill="var(--bg)" opacity="0.1"/><line x1="9" y1="22" x2="30" y2="22" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><line x1="9" y1="26" x2="30" y2="26" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><line x1="9" y1="30" x2="30" y2="30" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><line x1="9" y1="34" x2="30" y2="34" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><line x1="9" y1="38" x2="30" y2="38" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><rect x="36" y="16" width="17" height="8" rx="2" fill="var(--bg)" opacity="0.22"/><line x1="45" y1="17.5" x2="45" y2="23.5" stroke="var(--bg)" stroke-width="1.5" stroke-linecap="round" opacity="0.6"/><circle cx="41" cy="33" r="5" fill="var(--bg)" opacity="0.2"/><circle cx="41" cy="33" r="2.5" fill="var(--bg)" opacity="0.5"/><circle cx="53" cy="33" r="5" fill="var(--bg)" opacity="0.2"/><circle cx="53" cy="33" r="2.5" fill="var(--bg)" opacity="0.5"/></svg>';
        const SVG_RADIO_LG = '<svg viewBox="0 0 60 52" width="100" height="87"><line x1="46" y1="3" x2="39" y2="13" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round"/><rect x="3" y="12" width="54" height="36" rx="7" fill="var(--primary)"/><rect x="7" y="17" width="25" height="26" rx="4" fill="var(--bg)" opacity="0.1"/><line x1="9" y1="22" x2="30" y2="22" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><line x1="9" y1="26" x2="30" y2="26" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><line x1="9" y1="30" x2="30" y2="30" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><line x1="9" y1="34" x2="30" y2="34" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><line x1="9" y1="38" x2="30" y2="38" stroke="var(--bg)" stroke-width="1.3" stroke-linecap="round" opacity="0.55"/><rect x="36" y="16" width="17" height="8" rx="2" fill="var(--bg)" opacity="0.22"/><line x1="45" y1="17.5" x2="45" y2="23.5" stroke="var(--bg)" stroke-width="1.5" stroke-linecap="round" opacity="0.6"/><circle cx="41" cy="33" r="5" fill="var(--bg)" opacity="0.2"/><circle cx="41" cy="33" r="2.5" fill="var(--bg)" opacity="0.5"/><circle cx="53" cy="33" r="5" fill="var(--bg)" opacity="0.2"/><circle cx="53" cy="33" r="2.5" fill="var(--bg)" opacity="0.5"/></svg>';

        // ─── 数据加载 ───────────────────────────────────────────────────────────
        var allStations = [];

        // ─── 分类正则（定义一次，全局复用）─────────────────────────────────────
        const typePatterns = {
            '音乐': /音乐|Music|pop|rock|hit|jazz|classical/i,
            '新闻': /新闻|News|资讯|information/i,
            '财经': /财经|金融|股票|经济|Finance|Financial|Business|Economy|Stock|Market|Investment|Banking/i,
            '综合': /广播|Radio|综合|general/i,
            '交通': /交通|Traffic|汽车|Auto/i,
            '体育': /体育|Sports/i,
            '文艺': /文艺|Culture/i,
            '经典': /经典|Classic|怀旧|Old/i,
            '儿童': /儿童|少儿|Kids|children/i,
            '宗教': /宗教|上帝|Islam|Christian|Quran|佛教/i,
            '古典': /古典|Symphony|交响/i,
            '方言': /方言|闽南|粤语|Cantonese|吴语|沪语/i,
            // ── 音乐细分风格（优先级低于通用分类，但早于央级）───────────────────
            // 注意：与上方「音乐」分类并存，具体风格会双重打标，方便按风格筛选
            '爵士':  /jazz|爵士/i,
            '流行':  /\bpop\b|流行|top\s*40|\bhits\b|k.?pop|j.?pop/i,
            '摇滚':  /\brock\b|摇滚|\bindie\b|alternative|punk|grunge/i,
            '嘻哈':  /hip.?hop|\brap\b|嘻哈|说唱/i,
            '电子':  /\bedm\b|\belectronic\b|\belectro\b|techno|trance|dance\s*(?:music|fm|radio|hits)|舞曲|电子音乐/i,
            'R&B':   /\br\s*&\s*b\b|rhythm.?blues|soul\s*(?:music|fm|radio)|灵魂乐|节奏蓝调/i,
            '乡村':  /\bcountry\b|乡村音乐/i,
            '民谣':  /\bfolk\b|民谣|\bacoustic\b/i,
            '蓝调':  /\bblues\b|蓝调/i,
            '雷鬼':  /reggae|雷鬼/i,
            '金属':  /\bmetal\b|金属乐|heavy\s*metal/i,
            '拉丁':  /\blatin\b|salsa|cumbia|merengue|bachata|拉丁/i,
            // 仅中国：央级媒体
            '央广': /央广|中央人民广播|中国之声|CNR\s*\d|CRI\b|中国国际广播|全国联播|中央广播电台/i,
            '央视': /央视|CCTV|中央电视台/i,
        };
        const typeKeys = Object.keys(typePatterns);

        // ─── 省份正则（按匹配优先级排列；覆盖全国所有地级行政区 + 拼音 + 邮政式拼音）────
        // 规则：全国/央级最先匹配；直辖市次之；各省含所有地级市/自治州/盟名称及拼音
        // 注意：detectProvince 取第一个命中省份，排列顺序即优先级
        // 冲突处理：Taizhou(苏/浙)、Yichun(赣/黑)、Yulin(桂/陕)、Fuzhou(闽/赣) 均由排序靠前的省份优先命中
        // 邮政式拼音（Wade-Giles 衍生）：Peking 北京、Tsingtao 青岛、Soochow 苏州、Foochow 福州 等历史英文名
        const provincePatterns = {
            // ── 全国/央级（优先级最高）──────────────────────────────────────
            '全国':   /央广|中央人民广播|中国之声|CNR\s*\d|CRI\b|中国国际广播|全国联播|中央广播电台/i,
            // ── 四大直辖市 ──────────────────────────────────────────────────
            // 邮政：Peking(北京) Peiping(1928-49年旧称) Tientsin(天津) Chungking(重庆)
            '北京':   /北京|Beijing|Peking|Peiping|Peiking/i,
            '天津':   /天津|Tianjin|Tientsin|Tientsien/i,
            '上海':   /上海|Shanghai/i,
            '重庆':   /重庆|Chongqing|Chungking|Chunking/i,
            // ── 华南 ────────────────────────────────────────────────────────
            // 邮政：Kwangtung(广东) Canton/Kwangchow(广州) Swatow(汕头) Fatshan(佛山)
            //       Shaokwan(韶关) Tsamkong/Chanchiang(湛江) Shiuhing(肇庆) Kongmoon(江门)
            //       Kaying(梅州/梅县) Chaochow(潮州)
            '广东':   /广东|珠江|Guangdong|Kwangtung|广州|深圳|珠海|汕头|佛山|韶关|湛江|肇庆|江门|茂名|惠州|梅州|汕尾|河源|阳江|清远|东莞|中山|潮州|揭阳|云浮|Guangzhou|Canton|Kwangchow|Shenzhen|Shumchun|Zhuhai|Shantou|Swatow|Foshan|Fatshan|Shaoguan|Shaokwan|Zhanjiang|Tsamkong|Chanchiang|Zhaoqing|Shiuhing|Jiangmen|Kongmoon|Maoming|Huizhou|Meizhou|Kaying|Shanwei|Heyuan|Yangjiang|Qingyuan|Dongguan|Zhongshan|Chaozhou|Chaochow|Jieyang|Yunfu/i,
            // 邮政：Kwangsi(广西) Liuchow(柳州) Kweilin(桂林)
            '广西':   /广西|Guangxi|Kwangsi|南宁|柳州|桂林|梧州|北海|防城港|钦州|贵港|玉林|百色|贺州|河池|来宾|崇左|Nanning|Liuzhou|Liuchow|Guilin|Kweilin|Wuzhou|Beihai|Fangchenggang|Qinzhou|Guigang|Yulin|Baise|Hezhou|Hechi|Laibin|Chongzuo/i,
            // 海南：(?!州) 防止匹配青海的"海南州"；不含"东方"（过于通用）
            // 邮政：Hoihow/Haikkow(海口)
            '海南':   /海南(?!州)|Hainan|海口|三亚|三沙|儋州|琼海|文昌|万宁|Haikou|Hoihow|Haikkow|Sanya|Danzhou|Qionghai|Wenchang|Wanning/i,
            // ── 华东 ────────────────────────────────────────────────────────
            // 邮政：Fukien(福建) Foochow(福州) Amoy(厦门) Hinghwa(莆田/兴化) Chinchew/Tsinkiang(泉州) Changchow/Cheangchew(漳州)
            '福建':   /福建|闽南|闽北|闽东|闽西|Fujian|Fukien|福州|厦门|莆田|三明|泉州|漳州|南平|龙岩|宁德|Fuzhou|Foochow|Xiamen|Amoy|Putian|Hinghwa|Sanming|Quanzhou|Chinchew|Tsinkiang|Zhangzhou|Changchow|Cheangchew|Nanping|Longyan|Ningde/i,
            // 邮政：Kiangsu(江苏) Nanking(南京) Wusih(无锡) Hsuchow/Suchow(徐州) Soochow(苏州) Nantung(南通) Lienyunkang(连云港) Yangchow(扬州) Chinkiang(镇江)
            '江苏':   /江苏|Jiangsu|Kiangsu|南京|无锡|徐州|常州|苏州|南通|连云港|淮安|盐城|扬州|镇江|泰州|宿迁|Nanjing|Nanking|Wuxi|Wusih|Xuzhou|Hsuchow|Suchow|Changzhou|Suzhou|Soochow|Nantong|Nantung|Lianyungang|Lienyunkang|Huaian|Yancheng|Yangzhou|Yangchow|Zhenjiang|Chinkiang|Taizhou|Suqian/i,
            // 邮政：Chekiang(浙江) Hangchow(杭州) Ningpo(宁波) Wenchow(温州) Kashing(嘉兴) Huchow(湖州) Shaohsing(绍兴)
            '浙江':   /浙江|Zhejiang|Chekiang|杭州|宁波|温州|嘉兴|湖州|绍兴|金华|衢州|舟山|台州|丽水|Hangzhou|Hangchow|Ningbo|Ningpo|Wenzhou|Wenchow|Jiaxing|Kashing|Huzhou|Huchow|Shaoxing|Shaohsing|Jinhua|Quzhou|Zhoushan|Lishui/i,
            // 邮政：Shantung(山东) Tsinan/Chinan(济南) Tsingtao/Chingtao(青岛) Chefoo/Yentai(烟台) Weihaiwei(威海)
            '山东':   /山东|Shandong|Shantung|济南|青岛|淄博|枣庄|东营|烟台|潍坊|济宁|泰安|威海|日照|临沂|德州|聊城|滨州|菏泽|Jinan|Tsinan|Chinan|Qingdao|Tsingtao|Chingtao|Zibo|Zaozhuang|Dongying|Yantai|Chefoo|Yentai|Weifang|Jining|Taian|Weihai|Weihaiwei|Rizhao|Linyi|Dezhou|Liaocheng|Binzhou|Heze/i,
            // 邮政：Anhwei(安徽) Hofei(合肥) Pengpu(蚌埠) Anking(安庆)
            '安徽':   /安徽|Anhui|Anhwei|Nganhwei|合肥|芜湖|蚌埠|淮南|马鞍山|淮北|铜陵|安庆|黄山|滁州|阜阳|宿州|六安|宣城|池州|亳州|Hefei|Hofei|Wuhu|Bengbu|Pengpu|Huainan|Maanshan|Huaibei|Tongling|Anqing|Anking|Huangshan|Chuzhou|Fuyang|Luan|Xuancheng|Chizhou|Bozhou/i,
            // 宜春(Yichun)与黑龙江伊春拼音相同，江西排前故优先命中
            // 邮政：Kiangsi(江西) Kiukiang(九江) Kingtehchen(景德镇)
            '江西':   /江西|Jiangxi|Kiangsi|南昌|景德镇|萍乡|九江|新余|鹰潭|赣州|吉安|宜春|抚州|上饶|Nanchang|Jingdezhen|Kingtehchen|Pingxiang|Jiujiang|Kiukiang|Xinyu|Yingtan|Ganzhou|Jian|Yichun|Fuzhou|Shangrao/i,
            // ── 华中 ────────────────────────────────────────────────────────
            // 邮政：Siangtan(湘潭) Yochow(岳阳)
            '湖南':   /湖南|Hunan|长沙|株洲|湘潭|衡阳|邵阳|岳阳|常德|张家界|益阳|郴州|永州|怀化|娄底|湘西|Changsha|Zhuzhou|Xiangtan|Siangtan|Hengyang|Shaoyang|Yueyang|Yochow|Changde|Zhangjiajie|Yiyang|Chenzhou|Yongzhou|Huaihua|Loudi|Xiangxi/i,
            // 邮政：Hupeh/Hupei(湖北) Wuchang/Hankow/Hanyang(武汉三镇旧名) Ichang(宜昌) Siangyang(襄阳) Shashi(荆州/沙市)
            '湖北':   /湖北|Hubei|Hupeh|Hupei|武汉|黄石|十堰|宜昌|襄阳|鄂州|荆门|孝感|荆州|黄冈|咸宁|随州|恩施|仙桃|潜江|天门|Wuhan|Wuchang|Hankow|Hankou|Hanyang|Huangshi|Shiyan|Yichang|Ichang|Xiangyang|Siangyang|Ezhou|Jingmen|Xiaogan|Jingzhou|Shashi|Huanggang|Xianning|Suizhou|Enshi|Xiantao|Qianjiang|Tianmen/i,
            // 邮政：Honan(河南) Chengchow(郑州) Loyang(洛阳)
            '河南':   /河南|Henan|Honan|郑州|开封|洛阳|平顶山|安阳|鹤壁|新乡|焦作|濮阳|许昌|漯河|三门峡|南阳|商丘|信阳|周口|驻马店|Zhengzhou|Chengchow|Kaifeng|Luoyang|Loyang|Pingdingshan|Anyang|Hebi|Xinxiang|Jiaozuo|Puyang|Xuchang|Luohe|Sanmenxia|Nanyang|Shangqiu|Xinyang|Zhoukou|Zhumadian/i,
            // ── 华北 ────────────────────────────────────────────────────────
            // 邮政：Hopeh/Hopei(河北) Shihkiachwang/Shichiachiang(石家庄) Paoting(保定) Kalgan/Changchiakow(张家口)
            '河北':   /河北|Hebei|Hopeh|Hopei|石家庄|唐山|秦皇岛|邯郸|邢台|保定|张家口|承德|沧州|廊坊|衡水|Shijiazhuang|Shihkiachwang|Shichiachiang|Tangshan|Qinhuangdao|Handan|Xingtai|Baoding|Paoting|Zhangjiakou|Kalgan|Changchiakow|Chengde|Cangzhou|Langfang|Hengshui/i,
            // 山西(Shanxi) vs 陕西(Shaanxi)：拼音有别，城市名无交叉
            // 邮政：Shansi(山西) Tatung(大同)
            '山西':   /山西|Shanxi|Shansi|太原|大同|阳泉|长治|晋城|朔州|晋中|运城|忻州|临汾|吕梁|Taiyuan|Datong|Tatung|Yangquan|Changzhi|Jincheng|Shuozhou|Jinzhong|Yuncheng|Xinzhou|Linfen|Luliang/i,
            // ── 东北 ────────────────────────────────────────────────────────
            // 邮政：Mukden/Fengtien(沈阳) Dairen/Talien(大连) Antung(丹东) Chinchow(锦州)
            '辽宁':   /辽宁|Liaoning|沈阳|大连|鞍山|抚顺|本溪|丹东|锦州|营口|阜新|辽阳|盘锦|铁岭|朝阳|葫芦岛|Shenyang|Mukden|Fengtien|Dalian|Dairen|Talien|Anshan|Fushun|Benxi|Dandong|Antung|Jinzhou|Chinchow|Yingkou|Fuxin|Liaoyang|Panjin|Tieling|Chaoyang|Huludao/i,
            // 邮政：Kirin/Chilin(吉林) Hsinking(长春，满洲国旧称新京)
            '吉林':   /吉林|Jilin|Kirin|Chilin|长春|四平|辽源|通化|白山|松原|白城|延边|Changchun|Hsinking|Siping|Liaoyuan|Tonghua|Baishan|Songyuan|Baicheng|Yanbian/i,
            // 伊春(Yichun)与江西宜春拼音相同，黑龙江排后，中文字符`伊春`可精确匹配
            // 邮政：Heilungkiang(黑龙江) Tsitsihar/Chichihaerh(齐齐哈尔) Chiamussu(佳木斯) Mutankiang(牡丹江)
            '黑龙江': /黑龙江|Heilongjiang|Heilungkiang|哈尔滨|齐齐哈尔|鸡西|鹤岗|双鸭山|大庆|伊春|佳木斯|七台河|牡丹江|黑河|绥化|大兴安岭|Harbin|Qiqihar|Tsitsihar|Chichihaerh|Jixi|Hegang|Shuangyashan|Daqing|Yichun|Jiamusi|Chiamussu|Qitaihe|Mudanjiang|Mutankiang|Heihe|Suihua|Daxinganling/i,
            // ── 西南 ────────────────────────────────────────────────────────
            // 邮政：Szechwan/Szechuan(四川) Chengtu(成都) Luchow(泸州) Mienchiang(绵阳) Kiating(乐山/嘉定府旧名) Nanchung(南充) Ipin(宜宾)
            '四川':   /四川|Sichuan|Szechwan|Szechuan|成都|自贡|攀枝花|泸州|德阳|绵阳|广元|遂宁|内江|乐山|南充|眉山|宜宾|广安|达州|雅安|巴中|资阳|阿坝|甘孜|凉山|Chengdu|Chengtu|Zigong|Panzhihua|Luzhou|Luchow|Deyang|Mianyang|Mienchiang|Guangyuan|Suining|Neijiang|Leshan|Kiating|Nanchong|Nanchung|Meishan|Yibin|Ipin|Guangan|Dazhou|Yaan|Bazhong|Ziyang|Aba|Ganzi|Liangshan/i,
            // 邮政：Kweichow(贵州) Kweiyang(贵阳) Tsunyi(遵义)
            '贵州':   /贵州|Guizhou|Kweichow|贵阳|六盘水|遵义|安顺|毕节|铜仁|黔西南|黔东南|黔南|Guiyang|Kweiyang|Liupanshui|Zunyi|Tsunyi|Anshun|Bijie|Tongren|Qianxinan|Qiandongnan|Qiannan/i,
            // 邮政：Yunnanfu(昆明历史名) Likiang(丽江) Tali(大理)
            '云南':   /云南|Yunnan|昆明|曲靖|玉溪|保山|昭通|丽江|普洱|临沧|楚雄|红河|文山|西双版纳|大理|德宏|怒江|迪庆|Kunming|Yunnanfu|Qujing|Yuxi|Baoshan|Zhaotong|Lijiang|Likiang|Puer|Lincang|Chuxiong|Honghe|Wenshan|Xishuangbanna|Dali|Tali|Dehong|Nujiang|Diqing/i,
            // 西藏：含拼音及常用英文名（Lhasa/Shigatse 等）
            '西藏':   /西藏|Tibet|藏语|拉萨|日喀则|昌都|林芝|山南|那曲|阿里|Lhasa|Lasa|Shigatse|Xigazê|Chamdo|Changdu|Nyingchi|Linzhi|Shannan|Nagqu|Naqu/i,
            // ── 西北 ────────────────────────────────────────────────────────
            // 榆林(Yulin)与广西玉林拼音相同，陕西排后，中文字符`榆林`可精确匹配
            // 邮政：Shensi(陕西) Sian/Hsian/Sianfu(西安) Paochi(宝鸡) Hsienyang(咸阳) Yenan(延安) Hanchung(汉中)
            '陕西':   /陕西|Shaanxi|Shensi|西安|铜川|宝鸡|咸阳|渭南|延安|汉中|榆林|安康|商洛|Xian|Xi.an|Sian|Hsian|Sianfu|Tongchuan|Baoji|Paochi|Xianyang|Hsienyang|Weinan|Yanan|Yenan|Hanzhong|Hanchung|Yulin|Ankang|Shangluo/i,
            // 邮政：Kansu(甘肃) Lanchow(兰州) Tienshui(天水) Liangchow(武威/凉州旧名) Changyeh(张掖) Chiuchuan(酒泉)
            '甘肃':   /甘肃|Gansu|Kansu|兰州|嘉峪关|金昌|白银|天水|武威|张掖|平凉|酒泉|庆阳|定西|陇南|临夏|甘南|Lanzhou|Lanchow|Jiayuguan|Jinchang|Baiyin|Tianshui|Tienshui|Wuwei|Liangchow|Zhangye|Changyeh|Pingliang|Jiuquan|Chiuchuan|Qingyang|Dingxi|Longnan|Linxia|Gannan/i,
            // 海南州加"州"以区分海南省；Hainanzhou 也精确无歧义
            // 邮政：Tsinghai/Chinghai(青海) Sining(西宁)
            '青海':   /青海|Qinghai|Tsinghai|Chinghai|西宁|海东|海北|黄南|海南州|果洛|玉树|Xining|Sining|Haidong|Haibei|Huangnan|Hainanzhou|Guoluo|Yushu/i,
            // 新疆：含地级市/自治州/地区的拼音及国际通用英文名
            // 邮政：Sinkiang(新疆) Urumchi(乌鲁木齐) Khotan(和田)
            '新疆':   /新疆|Xinjiang|Sinkiang|维吾尔|乌鲁木齐|克拉玛依|吐鲁番|哈密|昌吉|博尔塔拉|巴音郭楞|阿克苏|克孜勒苏|喀什|和田|伊犁|塔城|阿勒泰|Urumqi|Urumchi|Wulumuqi|Karamay|Turpan|Tulufan|Hami|Changji|Bortala|Boertala|Bayingolin|Bayinguoleng|Aksu|Akesu|Kizilsu|Kashgar|Kashi|Hotan|Hetian|Khotan|Ili|Yili|Tacheng|Altay|Aletai/i,
            // 邮政：Ningsia(宁夏)
            '宁夏':   /宁夏|Ningxia|Ningsia|银川|石嘴山|吴忠|固原|中卫|Yinchuan|Shizuishan|Wuzhong|Guyuan|Zhongwei/i,
            // 内蒙古：9地级市+3盟，含蒙古语及国际英文名
            // 邮政：Kweisui/Kweihua/Suiyuan(呼和浩特历史名) Paotow(包头)
            '内蒙古': /内蒙古|内蒙|呼和浩特|包头|乌海|赤峰|通辽|鄂尔多斯|呼伦贝尔|巴彦淖尔|乌兰察布|兴安盟|锡林郭勒|阿拉善|蒙古语|Hohhot|Huhehaote|Kweisui|Kweihua|Suiyuan|Baotou|Paotow|Wuhai|Chifeng|Tongliao|Ordos|Eerduosi|Hulunbuir|Hulunbeier|Bayannur|Ulanqab|Wulanchabu|Hinggan|Xingan|Xilingol|Xilinguole|Alxa|Alasha/i,
        };

        /**
         * 根据电台名推断所属省份，按 provincePatterns 顺序匹配，未命中返回 '其他'
         */
        function detectProvince(name) {
            for (const [province, regex] of Object.entries(provincePatterns)) {
                if (regex.test(name)) return province;
            }
            return '其他';
        }

        // ─── 繁简字符映射（支持繁体搜索命中简体，以及繁体台名正确分类）────────
        // 每两个字符为一对：奇数位=繁体，偶数位=简体
        const T2S_PAIRS = '電电廣广臺台灣湾國国語语樂乐聽听聞闻節节體体聯联頻频粵粤鳳凤閩闽衛卫娛娱兒儿時时藝艺經经綜综總总發发傳传興兴會会來来為为當当進进長长開开關关東东風风愛爱聲声話话見见號号陽阳業业際际與与個个們们無无網网訊讯線线頭头動动車车萬万問问題题員员點点書书學学習习導导親亲實实歡欢雲云務务優优麗丽農农濟济環环夢梦島岛億亿說说讀读寫写還还兩两橋桥飛飞遠远遊游運运龍龙馬马魚鱼鳥鸟畫画歷历參参義义專专權权產产術术設设備备試试驗验應应響响壓压質质標标達达請请項项紅红綠绿藍蓝純纯維维積积編编繼继給给結结絕绝視视報报後后廳厅銀银鐘钟論论錄录類类齊齐鬥斗頂顶';
        const T2S_MAP = {};
        for (let i = 0; i < T2S_PAIRS.length; i += 2) T2S_MAP[T2S_PAIRS[i]] = T2S_PAIRS[i + 1];
        /** 将字符串中的繁体字统一替换为对应简体字 */
        function normalizeZh(s) { return s.replace(/[\u4e00-\u9fff]/g, c => T2S_MAP[c] || c); }

        // ─── 预计算每个电台的衍生字段（只在数据加载时执行一次）──────────────
        // _nameLower:   规范化（繁→简）+ 小写，用于搜索匹配
        // _types:       匹配到的分类数组（中国电台额外含省份），避免过滤/渲染时重跑正则
        // _typeTagHtml: 预渲染好的分类标签 HTML 片段
        /** 对一组新电台对象进行预计算（增量，支持流式分批调用）*/
        function preprocessNewStations(stations) {
            stations.forEach(s => {
                const nameNorm = normalizeZh(s.name);  // 繁体名称统一为简体
                s._nameLower = nameNorm.toLowerCase();
                s._types = typeKeys.filter(t => typePatterns[t].test(nameNorm));
                // 中国电台：将省份作为一种分类加入 _types（仅命中省份时，排在最前）
                if (s.region === '中国') {
                    const prov = detectProvince(nameNorm);
                    if (prov !== '其他') s._types.unshift(prov);
                }
                if (s._types.length === 0) s._types = ['其他'];
            });
        }
        function preprocessStations(data) {
            allStations = data;
            preprocessNewStations(allStations);
        }

        // ─── 状态变量 ────────────────────────────────────────────────────────────
        const BATCH_SIZE = 100;
        let currentRegion = 'all';
        let currentType   = '';
        let currentSearch = '';
        let visibleCount    = BATCH_SIZE;
        let isLoading       = false;
        let currentUrl      = '';
        let currentStation  = null;
        let stationsFullyLoaded = false; // 全部数据流式加载完毕标志
        let cachedStationTotal = 0;           // 加载完成后缓存去重总数
        let currentLang    = 'en';
        let i18n           = {};
        const SUPPORTED_LANGS = ['zh-CN', 'zh-TW', 'en', 'es', 'fr', 'de', 'it', 'ja', 'ko'];
        // Maps Chinese internal values → English label keys used in lang/*.json "labels"
        const LABEL_KEYS = {
            '中国':'china','日本':'japan','韩国':'korea','台湾':'taiwan','香港':'hongkong',
            '新加坡':'singapore','英国':'uk','德国':'germany','法国':'france','意大利':'italy',
            '西班牙':'spain','俄罗斯':'russia','美国':'usa','加拿大':'canada',
            '澳大利亚':'australia','澳洲':'australia','新西兰':'newzealand',
            '巴西':'brazil','墨西哥':'mexico','阿根廷':'argentina',
            '瑞士':'switzerland','南非':'southafrica','其他':'other','全球':'global',
            '音乐':'music','新闻':'news','综合':'general','交通':'traffic',
            '体育':'sports','文艺':'arts','经典':'classic','儿童':'kids',
            '宗教':'religion','古典':'classical','方言':'dialect',
            '爵士':'jazz','流行':'pop','摇滚':'rock','嘻哈':'hiphop',
            '电子':'electronic','R&B':'rnb','乡村':'country','民谣':'folk',
            '蓝调':'blues','雷鬼':'reggae','金属':'metal','拉丁':'latin',
            '财经':'finance',
            '央广':'cnr','央视':'cctv',
            '全国':'national','北京':'beijing','天津':'tianjin','上海':'shanghai','重庆':'chongqing',
            '广东':'guangdong','广西':'guangxi','海南':'hainan','福建':'fujian',
            '江苏':'jiangsu','浙江':'zhejiang','山东':'shandong','安徽':'anhui',
            '江西':'jiangxi','湖南':'hunan','湖北':'hubei','河南':'henan',
            '河北':'hebei','山西':'shanxi','辽宁':'liaoning','吉林':'jilin',
            '黑龙江':'heilongjiang','四川':'sichuan','贵州':'guizhou',
            '云南':'yunnan','西藏':'tibet','陕西':'shaanxi',
            '甘肃':'gansu','青海':'qinghai','新疆':'xinjiang',
            '宁夏':'ningxia','内蒙古':'innermongolia'
        };
        const THEME_COLORS = {green:'#22c55e',teal:'#14b8a6',cyan:'#06b6d4',orange:'#f97316',amber:'#f59e0b',rose:'#f43f5e',red:'#dc2626',pink:'#ec4899',purple:'#a855f7',indigo:'#6366f1',grayscale:'#888',bw:'#ddd'};
        const THEME_KEYS   = ['green','teal','cyan','orange','amber','rose','red','pink','purple','indigo','grayscale','bw'];
        const LANG_OPTIONS = [
            {value:'zh-CN',flag:'cn',label:'简体中文'},
            {value:'zh-TW',flag:'tw',label:'繁體中文'},
            {value:'en',   flag:'gb',label:'English'},
            {value:'es',   flag:'es',label:'Español'},
            {value:'fr',   flag:'fr',label:'Français'},
            {value:'de',   flag:'de',label:'Deutsch'},
            {value:'it',   flag:'it',label:'Italiano'},
            {value:'ja',   flag:'jp',label:'日本語'},
            {value:'ko',   flag:'kr',label:'한국어'},
        ];

        // HTML 属性安全转义（防止电台名内的引号等破坏HTML属性）
        function esc(s) {
            return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
        }
        // 复合唯一键判断当前播放
        function isSameStation(a, b) {
            return a && b && a.url === b.url && a.name === b.name;
        }
        /** 过滤结果缓存，reset 时置 null 强制重新计算 */
        let filteredCache = null;

        // ─── jQuery 主逻辑（DOM 操作、事件绑定、渲染）──────────────────────────
        $(function () {

            // ── 计算过滤结果（有缓存则复用）──────────────────────────────────────
            function getFiltered() {
                if (filteredCache) return filteredCache;
                const result = [];
                const seenNames = new Set();
                for (let i = 0; i < allStations.length; i++) {
                    const s = allStations[i];
                    if (seenNames.has(s.name)) continue;
                    if (currentRegion !== 'all' && s.region !== currentRegion) continue;
                    if (currentSearch && !s._nameLower.includes(currentSearch)) continue;
                    if (currentType !== '' && !s._types.includes(currentType)) continue;
                    seenNames.add(s.name);
                    result.push(s);
                }
                filteredCache = result;
                return result;
            }

            // ── 渲染电台卡片（拼接 HTML 字符串，$.html() 一次写入 DOM）────────────
            function renderStations(stations) {
                let html = '';
                stations.forEach(station => {
                    const logoHtml = station.logo && station.logo !== 'null'
                        ? `<img src="${station.logo}" class="station-logo" alt="" loading="lazy">`
                        : `<div class="station-logo placeholder">${SVG_RADIO_SM}</div>`;
                    const isPlaying = isSameStation(station, currentStation) &&
                        (() => { const a = document.getElementById('audioPlayer'); return !!a.src && a.src !== location.href && !a.paused; })();
                    const activeClass = isPlaying ? ' playing' : '';
                    
                    // 生成带国旗的国家标签
                    const countryCode = regionCodes[station.country] || 'un';
                    const flagUrl = `https://flagcdn.com/w20/${countryCode}.png`;
                    const regionTagHtml = `<span class="region-tag"><img src="${flagUrl}" alt="" class="region-tag-flag"><span class="region-tag-name">${tLabel(station.country)}</span></span>`;
                    const typeTagHtml = station._types.map(tp => `<span class="type-tag">${tLabel(tp)}</span>`).join('');
                    
                    html += `<div class="station-card${activeClass}" data-url="${esc(station.url)}" data-name="${esc(station.name)}">
                        ${logoHtml}
                        <div class="station-content">
                            <div class="station-name">${station.name}</div>
                            <div class="station-meta">
                                ${regionTagHtml}${typeTagHtml}
                            </div>
                        </div>
                        <div class="station-actions">
                            <span class="playing-badge"><span class="playing-badge-dot"></span>${t('playerPlaying') || '正在播放'}</span>
                            <button class="station-play-btn" data-url="${esc(station.url)}" data-name="${esc(station.name)}" title="${t('playButton') || '播放'}">
                                <svg viewBox="0 0 24 24"><polygon points="6,4 20,12 6,20"/></svg>
                            </button>
                            <button class="station-stop-btn" data-url="${esc(station.url)}" data-name="${esc(station.name)}" title="${t('pauseButton') || '暂停'}">
                                <svg viewBox="0 0 24 24"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
                            </button>
                        </div>
                    </div>`;
                });
                $('#stationsGrid').html(html);
            }

            // ── 过滤 + 渲染主入口 ─────────────────────────────────────────────────
            function filterAndRender(reset = false) {
                if (!allStations.length) {
                    $('#resultCount').text(stationsFullyLoaded ? t('noStations') : t('loading'));
                    return;
                }
                if (reset) { filteredCache = null; visibleCount = BATCH_SIZE; }
                const filtered = getFiltered();
                const toShow   = filtered.slice(0, visibleCount);
                renderStations(toShow);
                const suffix = stationsFullyLoaded ? '' : t('loadingSuffix');
                $('#resultCount').text(t('showingCount', {
                    shown: toShow.length,
                    total: filtered.length,
                    suffix,
                }));
                $('#loadingMore').toggleClass('show', visibleCount < filtered.length);
            }

            // 去重后的电台范围（用于分类按钮计数），不应用 currentType 过滤
            function getDeduplicatedScope() {
                const result = [];
                const seenNames = new Set();
                for (let i = 0; i < allStations.length; i++) {
                    const s = allStations[i];
                    if (seenNames.has(s.name)) continue;
                    if (currentRegion !== 'all' && s.region !== currentRegion) continue;
                    if (currentSearch && !s._nameLower.includes(currentSearch)) continue;
                    seenNames.add(s.name);
                    result.push(s);
                }
                return result;
            }

            // ── 渲染分类按钮（含中国省份，基于预计算 _types）─────────────────────
            // 中国：全国 → 央广 → 央视 → 省份(按数量) → 风格(按数量) → 其他
            // 其他地区：按数量降序，其他 固定最后
            function renderTypeButtons() {
                const scope = getDeduplicatedScope();
                const typeCounts = {};
                scope.forEach(s => s._types.forEach(t => { typeCounts[t] = (typeCounts[t] || 0) + 1; }));

                const provinceSet = new Set(Object.keys(provincePatterns)); // 全国 + 32省份
                const styleSet    = new Set(Object.keys(typePatterns));     // 音乐/新闻/央广/央视…
                const FIXED_CN    = ['全国', '央广', '央视'];               // 中国固定前三位

                let sorted;
                if (currentRegion === '中国') {
                    // 固定头部（仅出现在 typeCounts 中的才显示）
                    const fixed  = FIXED_CN.filter(t => typeCounts[t]);
                    // 省份：在 provincePatterns 中、非固定头部、非 其他
                    const provs  = Object.keys(typeCounts)
                        .filter(t => provinceSet.has(t) && !FIXED_CN.includes(t) && t !== '其他')
                        .sort((a, b) => typeCounts[b] - typeCounts[a]);
                    // 风格：在 typePatterns 中、非固定头部
                    const styles = Object.keys(typeCounts)
                        .filter(t => styleSet.has(t) && !FIXED_CN.includes(t) && t !== '其他')
                        .sort((a, b) => typeCounts[b] - typeCounts[a]);
                    // 其他 始终垫底
                    const tail   = typeCounts['其他'] ? ['其他'] : [];
                    sorted = [...fixed, ...provs, ...styles, ...tail];
                } else {
                    // 非中国：按数量降序，省份标签不显示，其他 垫底
                    sorted = Object.keys(typeCounts)
                        .filter(t => t !== '其他' && !provinceSet.has(t))
                        .sort((a, b) => typeCounts[b] - typeCounts[a]);
                    if (typeCounts['其他']) sorted.push('其他');
                }

                let html = `<button class="type-btn ${currentType === '' ? 'active' : ''}" data-type="">${t('all')}</button>`;
                sorted.forEach(type => {
                    html += `<button class="type-btn ${currentType === type ? 'active' : ''}" data-type="${type}">${tLabel(type)}(${typeCounts[type]})</button>`;
                });
                $('#typeBtns').html(html);
            }

            // ── 加载完成后按去重数量更新地区按钮计数 ─────────────────────────────
            function updateRegionCounts() {
                // 全局去重：用于"全部"按钮计数
                const globalSeen = new Set();
                let total = 0;
                // 各地区独立去重：与点击地区按钮后 getFiltered 的结果保持一致
                const regionDeduped = {};
                const regionSeen = {};
                allStations.forEach(s => {
                    if (!globalSeen.has(s.name)) { globalSeen.add(s.name); total++; }
                    if (!regionSeen[s.region]) regionSeen[s.region] = new Set();
                    if (!regionSeen[s.region].has(s.name)) {
                        regionSeen[s.region].add(s.name);
                        regionDeduped[s.region] = (regionDeduped[s.region] || 0) + 1;
                    }
                });
                $('.region-btn[data-region="all"]').data('count', total);
                Object.entries(regionDeduped).forEach(([region, count]) => {
                    $(`.region-btn[data-region="${region}"]`).data('count', count);
                });
                cachedStationTotal = total;
                translateRegionBtns();
                $('#totalSubtitle').text(t('totalStations', { total }));
            }

            // ── 播放 ──────────────────────────────────────────────────────────────
            // autoPlay=false 用于页面刷新恢复显示状态，不触发音频播放（避免自动播放拦截）
            function playStation(station, autoPlay = true) {
                currentUrl     = station.url;
                currentStation = station;
                localStorage.setItem('play_url', station.url);
                localStorage.setItem('play_name', station.name);
                const audio = document.getElementById('audioPlayer');
                
                // 更新标题
                $('#playerTitle, #fullscreenTitle').text(station.name);
                syncMiniPlayer();
                
                // 更新 Logo
                const imgSrc = station.logo && station.logo !== 'null' ? station.logo : null;
                $('#playerLogo').replaceWith(imgSrc
                    ? `<img src="${imgSrc}" class="player-logo" id="playerLogo" alt="">`
                    : `<div class="player-logo placeholder" id="playerLogo">${SVG_RADIO_MD}</div>`);
                
                // 更新全屏封面
                if (imgSrc) {
                    $('#fullscreenCover').replaceWith(`<img src="${imgSrc}" class="fullscreen-cover" id="fullscreenCover" alt="">`);
                } else {
                    $('#fullscreenCover').replaceWith(`<div class="fullscreen-cover placeholder" id="fullscreenCover">${SVG_RADIO_LG}</div>`);
                }
                
                // 更新卡片播放状态（名称+URL 双重匹配）
                // autoPlay=false 时不标记为 playing，卡片与实际音频状态保持一致
                $('.station-card').removeClass('playing');
                if (autoPlay) {
                    $('.station-card').filter(function () {
                        return $(this).attr('data-url') === station.url &&
                               $(this).attr('data-name') === station.name;
                    }).addClass('playing');
                }
                
                if (autoPlay) {
                    // 设置加载状态并播放
                    $('#playerStatus, #fullscreenStatus').text(t('playerLoading'));
                    $('#statusDot, #fullscreenDot').removeClass('playing paused');
                    audio.src = station.url;
                    audio.play().catch(e => {
                        console.warn('自动播放被阻止，请点击播放条开始播放:', e);
                        $('#playerStatus, #fullscreenStatus').text(t('playerClickToPlay'));
                        $('#statusDot, #fullscreenDot').addClass('paused');
                        updatePlayIcon(true);
                        updateFullscreenPlayIcon(true);
                    });
                } else {
                    // 仅恢复显示，等待用户点击播放
                    $('#playerStatus, #fullscreenStatus').text(t('playerClickToPlay'));
                    $('#statusDot, #fullscreenDot').removeClass('playing').addClass('paused');
                    updatePlayIcon(true);
                    updateFullscreenPlayIcon(true);
                }

                updateURL();
            }

            /** 根据暂停状态切换播放图标（三角=播放 / 方块=停止）*/
            function updatePlayIcon(isPaused) {
                const icon = isPaused
                    ? '<polygon points="6,4 20,12 6,20"/>'
                    : '<rect x="5" y="4" width="4" height="16"/><rect x="15" y="4" width="4" height="16"/>';
                $('#playIcon').html(icon);
            }

            /** 切换当前电台的播放 / 暂停；若尚未加载则无操作 */
            function togglePlay() {
                if (!currentStation) return;
                const audio = document.getElementById('audioPlayer');
                // 刷新后 src 为空（仅恢复了显示），需要先赋 src 再播放
                if (!audio.src || audio.src === window.location.href) {
                    audio.src = currentStation.url;
                }
                if (audio.paused) {
                    audio.play().catch(e => console.warn('播放失败:', e));
                } else {
                    audio.pause();
                }
            }

            // ── 地区 / 分类切换 ───────────────────────────────────────────────────
            function setRegion(region) {
                currentRegion = region;
                currentType   = '';
                localStorage.setItem('region', region);
                localStorage.removeItem('type');
                $('.region-btn').removeClass('active');
                $(`.region-btn[data-region="${region}"]`).addClass('active');
                renderTypeButtons();
                filterAndRender(true);
                updateURL();
                updateFilterLabels();
            }

            function setType(type) {
                currentType = type;
                localStorage.setItem('type', type);
                renderTypeButtons();
                filterAndRender(true);
                updateURL();
                updateFilterLabels();
            }

            // 更新筛选标题提示
            function updateFilterLabels() {
                $('#regionFilterLabel').text('· ' + (currentRegion !== 'all' ? tLabel(currentRegion) : t('all')));
                $('#typeFilterLabel').text('· ' + (currentType !== '' ? tLabel(currentType) : t('all')));
            }

            // ── URL 状态同步 ──────────────────────────────────────────────────────
            function updateURL() {
                const params = new URLSearchParams();
                if (currentRegion !== 'all') params.set('region', currentRegion);
                if (currentType !== '')       params.set('type',   currentType);
                if (currentStation) {
                    params.set('play',      currentStation.url);
                    params.set('play_name', currentStation.name);
                }
                history.replaceState(null, '', params.toString() ? '?' + params : location.pathname);
            }

            function t(key, vars = {}) {
                let text = i18n[key] || key;
                return String(text).replace(/\{\{(\w+)\}\}/g, (_, name) => vars[name] ?? '');
            }

            function tLabel(key) {
                const k = LABEL_KEYS[key] || key;
                return (i18n.labels && i18n.labels[k]) ? i18n.labels[k] : key;
            }

            function translateRegionBtns() {
                $('.region-btn').each(function () {
                    const region = $(this).data('region');
                    const count  = $(this).data('count');
                    const code   = $(this).data('flag-code') || 'un';
                    const label  = region === 'all' ? t('all') : tLabel(region);
                    const suffix = count !== undefined ? '(' + count + ')' : '';
                    $(this).html(`<img src="https://flagcdn.com/w20/${code}.png" alt="" class="region-flag"> ${label}${suffix}`);
                });
            }

            function translatePage() {
                $('[data-i18n]').each(function () {
                    const key = $(this).data('i18n');
                    const $el = $(this);
                    const $children = $el.children().detach();
                    $el.text(t(key));
                    $el.append($children);
                });
                $('[data-i18n-placeholder]').each(function () {
                    const key = $(this).data('i18n-placeholder');
                    $(this).attr('placeholder', t(key));
                });
                $('[data-i18n-title]').each(function () {
                    const key = $(this).data('i18n-title');
                    $(this).attr('title', t(key));
                });
                // 恢复 totalSubtitle（不受 data-i18n 循环控制）
                if (stationsFullyLoaded) {
                    $('#totalSubtitle').text(t('totalStations', { total: cachedStationTotal }));
                } else {
                    $('#totalSubtitle').text(t('subtitleLoading'));
                }
                buildThemePanel();
                updateThemePicker($('html').attr('data-theme') || 'green');
                updateLangPicker(currentLang);
                $('#themePickerBtn').attr('aria-label', t('theme'));
                $('#langPickerBtn').attr('aria-label', t('language'));
                updateFilterLabels();
                filterAndRender();
                translateRegionBtns();
                if (stationsFullyLoaded) renderTypeButtons();
                if (currentStation) {
                    $('#playerTitle, #fullscreenTitle').text(currentStation.name);
                    $('#miniPlayerName').text(currentStation.name);
                    document.title = currentStation.name + ' — ' + t('appTitle');
                    // 根据音频实际状态重新翻译状态文字
                    const audio = document.getElementById('audioPlayer');
                    let playerKey, miniKey;
                    if (audio.error) {
                        playerKey = 'playerError';
                        miniKey   = 'playerError';
                    } else if (!audio.paused) {
                        playerKey = 'playerPlaying';
                        miniKey   = 'playerPlaying';
                    } else {
                        playerKey = 'playerClickToPlay';
                        miniKey   = 'playerPaused';
                    }
                    $('#playerStatus, #fullscreenStatus').text(t(playerKey));
                    $('#miniPlayerStatus').text(t(miniKey));
                } else {
                    document.title = t('appTitle');
                    $('#fullscreenStatus').text(t('playerWaiting'));
                }
            }

            function loadLocale(lang) {
                return fetch(`lang/${lang}.json`, { cache: 'no-cache' })
                    .then(response => {
                        if (!response.ok) throw new Error('Locale not found');
                        return response.json();
                    })
                    .then(data => {
                        i18n = data;
                        currentLang = lang;
                        translatePage();
                    })
                    .catch(() => {
                        if (lang !== 'en') return loadLocale('en');
                    });
            }

            function buildThemePanel() {
                const html = THEME_KEYS.map(k => {
                    const labelKey = k === 'bw' ? 'themeBW' : k === 'grayscale' ? 'themeGrayscale' : 'theme' + k.charAt(0).toUpperCase() + k.slice(1);
                    const active = k === ($('html').attr('data-theme') || 'green') ? ' active' : '';
                    return `<div class="theme-item${active}" data-theme="${k}"><span class="theme-item-dot" style="background:${THEME_COLORS[k]}"></span><span>${t(labelKey)}</span></div>`;
                }).join('');
                $('#themePanel').html(html);
            }

            function updateThemePicker(theme) {
                $('#themePickerDot').css('background', THEME_COLORS[theme] || THEME_COLORS.green);
                const labelKey = theme === 'bw' ? 'themeBW' : theme === 'grayscale' ? 'themeGrayscale' : 'theme' + theme.charAt(0).toUpperCase() + theme.slice(1);
                $('#themePickerLabel').text(t(labelKey));
                $('#themePanel .theme-item').removeClass('active').filter(`[data-theme="${theme}"]`).addClass('active');
            }

            function buildLangPanel() {
                const html = LANG_OPTIONS.map(o => {
                    const active = o.value === currentLang ? ' active' : '';
                    return `<div class="lang-item${active}" data-lang="${o.value}"><img src="https://flagcdn.com/w20/${o.flag}.png" alt=""><span>${o.label}</span></div>`;
                }).join('');
                $('#langPanel').html(html);
            }

            function updateLangPicker(lang) {
                const opt = LANG_OPTIONS.find(o => o.value === lang) || LANG_OPTIONS[0];
                $('#langPickerFlag').attr('src', `https://flagcdn.com/w20/${opt.flag}.png`);
                $('#langPickerLabel').text(opt.label);
                $('#langPanel .lang-item').removeClass('active').filter(`[data-lang="${lang}"]`).addClass('active');
            }

            function initPickers() {
                buildLangPanel();
                updateLangPicker(currentLang);

                $('#themePickerBtn').on('click', function (e) {
                    e.stopPropagation();
                    const $w = $('#themePickerWrap');
                    const wasOpen = $w.hasClass('open');
                    $('.picker-wrap').removeClass('open');
                    if (!wasOpen) $w.addClass('open');
                });
                $('#langPickerBtn').on('click', function (e) {
                    e.stopPropagation();
                    const $w = $('#langPickerWrap');
                    const wasOpen = $w.hasClass('open');
                    $('.picker-wrap').removeClass('open');
                    if (!wasOpen) $w.addClass('open');
                });
                $(document).on('click', '.theme-item', function (e) {
                    e.stopPropagation();
                    setTheme($(this).data('theme'));
                    $('.picker-wrap').removeClass('open');
                });
                $(document).on('click', '.lang-item', function (e) {
                    e.stopPropagation();
                    setLanguage($(this).data('lang'));
                    $('.picker-wrap').removeClass('open');
                });
                $(document).on('click', '.picker-panel', function (e) {
                    e.stopPropagation();
                });
                $(document).on('click', function () {
                    $('.picker-wrap').removeClass('open');
                });
            }

            function initLanguage() {
                const saved = localStorage.getItem('language');
                const browserLang = (navigator.languages && navigator.languages[0]) || navigator.language || 'en';
                const normalized = (browserLang.startsWith('zh-TW') || browserLang.startsWith('zh-HK') || browserLang.startsWith('zh-MO')) ? 'zh-TW'
                    : browserLang.startsWith('zh') ? 'zh-CN'
                    : browserLang.startsWith('es') ? 'es'
                    : browserLang.startsWith('fr') ? 'fr'
                    : browserLang.startsWith('de') ? 'de'
                    : browserLang.startsWith('it') ? 'it'
                    : browserLang.startsWith('ja') ? 'ja'
                    : browserLang.startsWith('ko') ? 'ko'
                    : 'en';
                const lang = saved || normalized;
                const resolvedLang = SUPPORTED_LANGS.includes(lang) ? lang : 'en';
                if (!saved || saved !== resolvedLang) {
                    localStorage.setItem('language', resolvedLang);
                }
                return loadLocale(resolvedLang);
            }

            function setLanguage(lang) {
                if (!SUPPORTED_LANGS.includes(lang)) return;
                localStorage.setItem('language', lang);
                loadLocale(lang);
            }

            // ── 无限滚动 ──────────────────────────────────────────────────────────
            function loadMore() {
                if (isLoading) return;
                isLoading = true;
                visibleCount += BATCH_SIZE;
                filterAndRender();
                isLoading = false;
            }

            // ── 主题切换 ──────────────────────────────────────────────────────────
            function setTheme(theme) {
                $('html').attr('data-theme', theme);
                localStorage.setItem('theme', theme);
                updateThemePicker(theme);
            }

            // ── 初始化：从 URL 参数恢复 UI 状态，然后异步加载电台数据 ────────────
            function init() {
                const params      = new URLSearchParams(location.search);
                const regionParam = params.get('region');
                const typeParam   = params.get('type');
                const savedRegion = localStorage.getItem('region') || 'all';
                const savedType   = localStorage.getItem('type') || '';

                if (regionParam) {
                    currentRegion = regionParam;
                } else {
                    currentRegion = savedRegion;
                }
                if (currentRegion !== 'all') {
                    $('.region-btn').removeClass('active');
                    $(`.region-btn[data-region="${currentRegion}"]`).addClass('active');
                }

                if (typeParam) {
                    currentType = typeParam;
                } else if (savedRegion === currentRegion) {
                    currentType = savedType;
                } else {
                    currentType = '';
                }

                if (regionParam) {
                    localStorage.setItem('region', currentRegion);
                    if (!typeParam) localStorage.removeItem('type');
                }
                if (typeParam) {
                    localStorage.setItem('type', currentType);
                }

                // when URL doesn't provide filters, keep saved values
                if (!regionParam && currentRegion !== savedRegion) {
                    localStorage.setItem('region', currentRegion);
                }
                if (!typeParam && currentType !== savedType && currentType !== '') {
                    localStorage.setItem('type', currentType);
                }

                // 窄屏（手机）默认收起分类筛选，宽屏保持展开
                if (window.innerWidth < 600) {
                    $('#typesContent').addClass('collapsed');
                    $('#typesToggle').find('.toggle-icon').addClass('collapsed');
                }

                updateFilterLabels();
                $('#resultCount').text(t('loading'));

                // ── 流式加载电台数据（NDJSON），分批渐进渲染 ─────────────────
                (async () => {
                    try {
                        const r = await fetch('?action=stations');
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        const reader  = r.body.getReader();
                        const decoder = new TextDecoder();
                        const STREAM_BATCH = 1000; // 每积攒 1000 条刷新一次界面（减少 DOM 操作频率）
                        const STREAM_TOTAL = <?php echo $totalCount; ?>; // PHP 已知总数
                        let lineBuffer  = '';
                        let pendingBatch = [];
                        let renderPending = false;
                        const $progressFill = $('#topProgressFill');

                        const setProgress = (loaded) => {
                            const pct = STREAM_TOTAL > 0 ? Math.min(98, Math.round(loaded / STREAM_TOTAL * 100)) : 0;
                            $progressFill.css('width', pct + '%');
                            $('#totalSubtitle').text(t('loadingStations', { loaded: loaded.toLocaleString() }));
                        };

                        const scheduleRender = () => {
                            if (renderPending) return;
                            renderPending = true;
                            requestAnimationFrame(() => {
                                renderPending = false;
                                filteredCache = null;
                                filterAndRender(); // 加载中不重建分类按钮，减少开销
                            });
                        };

                        const flushBatch = () => {
                            if (!pendingBatch.length) return;
                            preprocessNewStations(pendingBatch);
                            pendingBatch.forEach(s => allStations.push(s));
                            pendingBatch = [];
                            setProgress(allStations.length);
                            scheduleRender();
                        };

                        while (true) {
                            const { done, value } = await reader.read();
                            if (value) {
                                lineBuffer += decoder.decode(value, { stream: true });
                                const lines = lineBuffer.split('\n');
                                lineBuffer = lines.pop(); // 保留未完成的行
                                for (const line of lines) {
                                    if (!line.trim()) continue;
                                    try { pendingBatch.push(JSON.parse(line)); } catch (e) {}
                                }
                                if (pendingBatch.length >= STREAM_BATCH) flushBatch();
                            }
                            if (done) {
                                if (lineBuffer.trim()) {
                                    try { pendingBatch.push(JSON.parse(lineBuffer.trim())); } catch (e) {}
                                }
                                flushBatch();
                                stationsFullyLoaded = true;
                                filteredCache = null;
                                filterAndRender(true);    // 加载完成：重置 visibleCount
                                renderTypeButtons();       // 仅在加载完成后构建分类按钮
                                updateRegionCounts();      // 按去重后数量更新地区按钮计数
                                $progressFill.addClass('done'); // 进度条完成动画
                                // 恢复 URL 中记录的播放状态
                                const playUrl = params.get('play') || localStorage.getItem('play_url');
                                if (playUrl) {
                                    const playName = params.get('play_name') || localStorage.getItem('play_name');
                                    let station = playName
                                        ? allStations.find(s => s.url === playUrl && s.name === playName)
                                        : null;
                                    if (!station) station = allStations.find(s => s.url === playUrl);
                                    if (station) {
                                        playStation(station, false);
                                        if (!params.get('play')) {
                                            updateURL();
                                        }
                                    }
                                }
                                break;
                            }
                        }
                    } catch (e) {
                        $('#resultCount').text(t('loadFailedRefresh'));
                    }
                })();
            }

            // ── 事件绑定 ──────────────────────────────────────────────────────────
            // 电台卡片（事件委托，无需为每张卡单独绑定监听器）
            $('#stationsGrid').on('click', '.station-card', function () {
                const url  = $(this).attr('data-url');
                const name = $(this).attr('data-name');
                const station = name
                    ? allStations.find(s => s.url === url && s.name === name)
                    : allStations.find(s => s.url === url);
                if (station) playStation(station);
            });

            // 地区按钮
            $('#regionBtns').on('click', '.region-btn', function () {
                setRegion($(this).attr('data-region'));
            });

            // 分类按钮（委托，按钮由 renderTypeButtons 动态生成）
            $('#typeBtns').on('click', '.type-btn', function () {
                setType($(this).attr('data-type'));
            });

            // 主题选择下拉框（由自定义 picker 接管，旧 select 已移除）
            initPickers();
            
            // 折叠/展开功能
            $('#regionsToggle').on('click', function () {
                $('#regionsContent').toggleClass('collapsed');
                $(this).find('.toggle-icon').toggleClass('collapsed');
            });
            
            $('#typesToggle').on('click', function () {
                $('#typesContent').toggleClass('collapsed');
                $(this).find('.toggle-icon').toggleClass('collapsed');
            });

            // 搜索防抖 300ms
            let searchTimeout;
            $('#searchInput').on('input', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    currentSearch = normalizeZh($(this).val().toLowerCase());
                    filterAndRender(true);
                }, 300);
            });

            // 无限滚动（监听 window 滚动）
            $(window).on('scroll', function () {
                if ($(window).scrollTop() + $(window).height() >= $(document).height() - 200) {
                    loadMore();
                }
            });

            // 播放器状态事件
            $('#audioPlayer')
                .on('play',  function () {
                    $('#statusDot, #fullscreenDot').removeClass('paused').addClass('playing');
                    $('#playerStatus, #fullscreenStatus').text(t('playerPlaying'));
                    updateMiniPlayIcon(false);
                    $('#miniPlayerStatus').text(t('playerPlaying'));
                    $('#soundWave').addClass('playing');
                    $('#fullscreenCover').addClass('playing');
                    updatePlayIcon(false);
                    updateFullscreenPlayIcon(false);
                    // 同步卡片播放状态
                    if (currentStation) {
                        $('.station-card').removeClass('playing')
                            .filter(function () {
                                return $(this).attr('data-url') === currentStation.url &&
                                       $(this).attr('data-name') === currentStation.name;
                            }).addClass('playing');
                        document.title = currentStation.name + ' — ' + t('appTitle');
                    }
                })
                .on('pause', function () {
                    $('#statusDot, #fullscreenDot').removeClass('playing').addClass('paused');
                    $('#playerStatus, #fullscreenStatus').text(t('playerClickToPlay'));
                    $('#soundWave').removeClass('playing');
                    $('#fullscreenCover').removeClass('playing');
                    updatePlayIcon(true);
                    updateFullscreenPlayIcon(true);
                    // 移除所有卡片播放高亮
                    $('.station-card').removeClass('playing');
                    updateMiniPlayIcon(true);
                    $('#miniPlayerStatus').text(t('playerPaused') || '已暂停');
                    document.title = t('appTitle');
                })
                .on('error', function () {
                    $('#statusDot, #fullscreenDot').removeClass('playing').addClass('paused');
                    $('#playerStatus, #fullscreenStatus').text(t('playerError'));
                    $('#soundWave').removeClass('playing');
                    $('#fullscreenCover').removeClass('playing');
                    updatePlayIcon(true);
                    updateFullscreenPlayIcon(true);
                    $('.station-card').removeClass('playing');
                    updateMiniPlayer();
                    $('#miniPlayerStatus').text(t('playerError'));
                    console.error('音频加载错误');
                });

            // 播放条整体点击切换（忽略 audio 原生控件和全屏按钮）
            $('#playerBar').on('click', function (e) {
                if ($(e.target).closest('audio, .fullscreen-btn').length) return;
                togglePlay();
            });
            
            // 全屏播放器功能
            function updateFullscreenUI() {
                if (!currentStation) return;
                
                $('#fullscreenTitle').text(currentStation.name);
                
                const imgSrc = currentStation.logo && currentStation.logo !== 'null' ? currentStation.logo : null;
                if (imgSrc) {
                    $('#fullscreenCover').replaceWith(`<img src="${imgSrc}" class="fullscreen-cover" id="fullscreenCover" alt="">`);
                } else {
                    $('#fullscreenCover').replaceWith(`<div class="fullscreen-cover placeholder" id="fullscreenCover">${SVG_RADIO_LG}</div>`);
                }
                
                const audio = document.getElementById('audioPlayer');
                if (!audio.paused) {
                    $('#fullscreenCover').addClass('playing');
                    $('#soundWave').addClass('playing');
                }
            }
            
            function updateFullscreenPlayIcon(isPaused) {
                const icon = isPaused
                    ? '<polygon points="8,5 19,12 8,19"/>'
                    : '<rect x="7" y="5" width="3" height="14"/><rect x="14" y="5" width="3" height="14"/>';
                $('#fullscreenPlayBtn svg').html(icon);
            }
            
            // 是否是自动触发的全屏（用于区分手动点击和横屏自动）
            let isAutoFullscreen = false;
            // 记录上一次是否为横屏，用于检测“进入横屏”这个瞬间
            let lastIsLandscape  = false;
            let userManuallyHid  = false;
            let pendingBrowserFS = false;

            let wakeLock = null;
            async function requestWakeLock() {
                if ('wakeLock' in navigator) {
                    try {
                        wakeLock = await navigator.wakeLock.request('screen');
                        wakeLock.addEventListener('release', () => { wakeLock = null; });
                    } catch(e) {}
                }
            }
            function releaseWakeLock() {
                if (wakeLock) { wakeLock.release(); wakeLock = null; }
            }
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible' && $('#fullscreenPlayer').hasClass('show')) {
                    requestWakeLock();
                }
            });

            function showFullscreen() {
                $('#fullscreenPlayer').addClass('show');
                updateFullscreenUI();
                const scrollY = window.scrollY;
                $('body').css({ 'overflow': 'hidden', 'position': 'fixed', 'top': -scrollY + 'px', 'width': '100%' });
                $('body').data('scroll-y', scrollY);
                requestWakeLock();
            }
            
            function hideFullscreen() {
                $('#fullscreenPlayer').removeClass('show');
                const scrollY = $('body').data('scroll-y') || 0;
                $('body').css({ 'overflow': '', 'position': '', 'top': '', 'width': '' });
                window.scrollTo(0, scrollY);
                isAutoFullscreen = false;
                pendingBrowserFS = false;
                releaseWakeLock();
                const fsEl = document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement;
                if (fsEl) {
                    (document.exitFullscreen || document.webkitExitFullscreen || document.mozCancelFullScreen || function(){}).call(document);
                }
            }
            
            function requestBrowserFullscreen() {
                const el  = document.documentElement;
                const req = el.requestFullscreen || el.webkitRequestFullscreen
                          || el.mozRequestFullScreen || el.msRequestFullscreen;
                if (req) req.call(el).catch(() => {});
            }

            $('#fullscreenPlayer').on('touchstart.fspend pointerdown.fspend', function() {
                if (pendingBrowserFS && $('#fullscreenPlayer').hasClass('show') &&
                    !document.fullscreenElement && !document.webkitFullscreenElement) {
                    pendingBrowserFS = false;
                    requestBrowserFullscreen();
                }
            });
            
            // 全屏按钮点击
            $('#fullscreenBtn').on('click', function(e) {
                e.stopPropagation();
                isAutoFullscreen = false;
                userManuallyHid  = false;
                showFullscreen();
                setTimeout(() => requestBrowserFullscreen(), 100);
            });

            $('#fullscreenClose').on('click', function() {
                userManuallyHid = true;
                hideFullscreen();
            });
            
            // 全屏播放/暂停按钮
            $('#fullscreenPlayBtn').on('click', togglePlay);
            
            // ESC键关闭全屏
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#fullscreenPlayer').hasClass('show')) {
                    userManuallyHid = true;
                    hideFullscreen();
                }
            });
            
            // 横竖屏检测（手机横屏自动进入全屏播放器和浏览器全屏，竖屏退出）
            function checkOrientation() { // orientation-check
                const isLandscape = window.matchMedia('(max-width: 900px) and (orientation: landscape)').matches;
                const enteredLandscape = isLandscape && !lastIsLandscape;

                // 每次“进入横屏”都强制进入播放器视图并请求浏览器全屏
                if (enteredLandscape) {
                    userManuallyHid = false;
                    if (!$('#fullscreenPlayer').hasClass('show')) {
                        isAutoFullscreen = true;
                        showFullscreen();
                    }
                    pendingBrowserFS = true;
                    setTimeout(function() {
                        if ($('#fullscreenPlayer').hasClass('show') &&
                            !document.fullscreenElement && !document.webkitFullscreenElement) {
                            requestBrowserFullscreen();
                        }
                    }, 300);
                } else if (!isLandscape) {
                    userManuallyHid  = false;
                    pendingBrowserFS = false;
                    if ($('#fullscreenPlayer').hasClass('show') && isAutoFullscreen) {
                        isAutoFullscreen = false;
                        $('#fullscreenPlayer').removeClass('show');
                        const scrollY = $('body').data('scroll-y') || 0;
                        $('body').css({ 'overflow': '', 'position': '', 'top': '', 'width': '' });
                        window.scrollTo(0, scrollY);
                        releaseWakeLock();
                        const fsEl = document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement;
                        if (fsEl) {
                            (document.exitFullscreen || document.webkitExitFullscreen || document.mozCancelFullScreen || function(){}).call(document);
                        }
                    }
                }

                lastIsLandscape = isLandscape;
            }
            
            $(window).on('orientationchange resize', checkOrientation);
            
            // 监听浏览器全屏状态变化
            $(document).on('fullscreenchange webkitfullscreenchange mozfullscreenchange', function() {
                if (!document.fullscreenElement && !document.webkitFullscreenElement && !document.mozFullScreenElement) {
                    if ($('#fullscreenPlayer').hasClass('show')) {
                        userManuallyHid  = true;
                        isAutoFullscreen = false;
                        $('#fullscreenPlayer').removeClass('show');
                        const scrollY = $('body').data('scroll-y') || 0;
                        $('body').css({ 'overflow': '', 'position': '', 'top': '', 'width': '' });
                        window.scrollTo(0, scrollY);
                        releaseWakeLock();
                    }
                }
            });
            
            checkOrientation();
            
            // 全屏时钟
            function updateClock() {
                const now = new Date();
                const h = String(now.getHours()).padStart(2, '0');
                const m = String(now.getMinutes()).padStart(2, '0');
                const s = String(now.getSeconds()).padStart(2, '0');
                $('#fullscreenClock').text(h + ':' + m + ':' + s);
            }
            updateClock();
            setInterval(updateClock, 1000);
            
            // 悬浮小播放条 & 回到顶部
            $(window).on('scroll.backtotop', function() {
                if ($(this).scrollTop() > 300) {
                    $('#miniPlayer').addClass('show');
                    $('body').addClass('mini-player-visible');
                } else {
                    $('#miniPlayer').removeClass('show');
                    $('body').removeClass('mini-player-visible');
                }
            });

            $('#backToTop').on('click', function() {
                $('html, body').animate({ scrollTop: 0 }, 300);
            });

            // 小播放条：根据 currentStation 显隐媒体控件
            function updateMiniPlayer() {
                if (currentStation) {
                    $('#miniPlayer').addClass('has-station');
                } else {
                    $('#miniPlayer').removeClass('has-station');
                }
            }

            // 同步小播放条电台信息（logo + 名称）
            function syncMiniPlayer() {
                if (!currentStation) return;
                const imgSrc = currentStation.logo && currentStation.logo !== 'null' ? currentStation.logo : null;
                const $logo = $('#miniPlayerLogo');
                if (imgSrc) {
                    $logo.removeClass('placeholder').html('<img src="' + imgSrc + '" alt="">');
                } else {
                    $logo.addClass('placeholder').html(SVG_RADIO_SM);
                }
                $('#miniPlayerName').text(currentStation.name);
                updateMiniPlayer();
            }

            // 切换小播放条播放/暂停图标
            function updateMiniPlayIcon(isPaused) {
                const icon = isPaused
                    ? '<polygon points="6,4 20,12 6,20"/>'
                    : '<rect x="5" y="4" width="4" height="16"/><rect x="15" y="4" width="4" height="16"/>';
                $('#miniPlayIcon').html(icon);
            }

            $('#miniPlayBtn').on('click', function() { togglePlay(); });
            $('#miniFullscreenBtn').on('click', function(e) {
                e.stopPropagation();
                isAutoFullscreen = false;
                userManuallyHid  = false;
                showFullscreen();
                setTimeout(() => requestBrowserFullscreen(), 100);
            });
            
            // 播放按钮
            $(document).on('click', '.station-play-btn', function(e) {
                e.stopPropagation();
                const url  = $(this).attr('data-url');
                const name = $(this).attr('data-name');
                const station = name
                    ? allStations.find(s => s.url === url && s.name === name)
                    : allStations.find(s => s.url === url);
                if (station) playStation(station);
            });

            // 停止播放按钮
            $(document).on('click', '.station-stop-btn', function(e) {
                e.stopPropagation();
                const audio = document.getElementById('audioPlayer');
                audio.pause();
                audio.src = '';
                currentUrl = '';
                currentStation = null;
                updateMiniPlayer();
                filterAndRender();
                $('#playerTitle').text(t('selectStationToPlay'));
                $('#playerStatus').text(t('playerWaiting'));
                $('#statusDot').removeClass('playing paused');
                $('#fullscreenTitle').text(t('selectStationToPlay'));
                $('#fullscreenStatus').text(t('playerWaiting'));
                $('#fullscreenDot').removeClass('playing paused');
                $('#soundWave').removeClass('playing');
                document.title = t('appTitle');
            });

            // 主题初始化 + 页面初始化
            initLanguage().then(() => {
                setTheme(localStorage.getItem('theme') || 'green');
                init();
            });
        });
    </script>
</body>
</html>