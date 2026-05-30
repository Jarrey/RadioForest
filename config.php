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

// ---------------------------------------------------------------------------
// Region list  —  maps M3U filename suffix (radio_XX.m3u) to Chinese name.
// Key: lowercase ISO-2 code (or '' for the global playlist radio.m3u).
// Add a new entry here to expose a new country tab in the sidebar.
// ---------------------------------------------------------------------------
define('REGION_NAMES', [
    'cn' => '中国', 'jp' => '日本', 'kr' => '韩国', 'tw' => '台湾', 'hk' => '香港',
    'sg' => '新加坡', 'gb' => '英国', 'de' => '德国', 'fr' => '法国', 'it' => '意大利',
    'es' => '西班牙', 'ru' => '俄罗斯', 'us' => '美国', 'ca' => '加拿大', 'au' => '澳大利亚',
    'nz' => '新西兰', 'br' => '巴西', 'mx' => '墨西哥', 'ar' => '阿根廷', 'ch' => '瑞士',
    'za' => '南非', 'in' => '印度', 'th' => '泰国', 'vn' => '越南', 'my' => '马来西亚',
    'id' => '印尼', 'ph' => '菲律宾', 'tr' => '土耳其', 'nl' => '荷兰', 'be' => '比利时',
    'at' => '奥地利', 'pl' => '波兰', 'se' => '瑞典', 'no' => '挪威', 'dk' => '丹麦',
    'fi' => '芬兰', 'ie' => '爱尔兰', 'pt' => '葡萄牙', 'gr' => '希腊', 'cz' => '捷克',
    'hu' => '匈牙利', 'ro' => '罗马尼亚', 'eg' => '埃及', 'il' => '以色列', 'ae' => '阿联酋',
    'sa' => '沙特',
    'ua' => '乌克兰', 'by' => '白俄罗斯', 'kz' => '哈萨克斯坦',
    'cl' => '智利', 'co' => '哥伦比亚', 'pe' => '秘鲁', 've' => '委内瑞拉', 'ec' => '厄瓜多尔',
    'mo' => '澳门',
    'pk' => '巴基斯坦', 'bd' => '孟加拉', 'lk' => '斯里兰卡', 'np' => '尼泊尔',
    'mm' => '缅甸', 'kh' => '柬埔寨', 'la' => '老挝', 'bn' => '文莱',
    'qa' => '卡塔尔', 'kw' => '科威特', 'bh' => '巴林', 'om' => '阿曼',
    'jo' => '约旦', 'lb' => '黎巴嫩', 'sy' => '叙利亚', 'iq' => '伊拉克',
    'ir' => '伊朗', 'af' => '阿富汗',
    'ng' => '尼日利亚', 'ma' => '摩洛哥', 'ke' => '肯尼亚', 'gh' => '加纳',
    'tz' => '坦桑尼亚', 'et' => '埃塞俄比亚', 'dz' => '阿尔及利亚', 'tn' => '突尼斯',
    'sd' => '苏丹', 'ug' => '乌干达', 'zw' => '津巴布韦',
    'na' => '纳米比亚', 'bw' => '博茨瓦纳', 'zm' => '赞比亚', 'mg' => '马达加斯加',
    '' => '全球',
]);

// ---------------------------------------------------------------------------
// M3U group-title map  —  maps the group-title field inside M3U files to the
// canonical Chinese country name used by REGION_NAMES.
// Each entry groups: full English name(s) / common aliases / ISO-2 upper-case.
// Add aliases here when a new source uses non-standard country names.
// ---------------------------------------------------------------------------
define('GROUP_TITLE_MAP', [
    // ── East Asia ───────────────────────────────────────────────────────────
    'China' => '中国',                                                                      'CN' => '中国',
    'Japan' => '日本',                                                                      'JP' => '日本',
    'South Korea' => '韩国', 'The Republic Of Korea' => '韩国', 'Korea' => '韩国',          'KR' => '韩国',
    'Taiwan' => '台湾', 'Taiwan, Republic Of China' => '台湾', 'Republic Of China' => '台湾', 'TW' => '台湾',
    'Hong Kong' => '香港',                                                                  'HK' => '香港',
    'Singapore' => '新加坡',                                                                'SG' => '新加坡',
    'Macau' => '澳门', 'Macao' => '澳门',                                                   'MO' => '澳门',
    // ── Europe ──────────────────────────────────────────────────────────────
    'United Kingdom' => '英国', 'The United Kingdom of Great Britain and Northern Ireland' => '英国',
    'The United Kingdom' => '英国', 'Great Britain' => '英国', 'Britain' => '英国',
    'England' => '英国', 'Scotland' => '英国', 'Wales' => '英国', 'Northern Ireland' => '英国',
                                                                                            'UK' => '英国', 'GB' => '英国',
    'Germany' => '德国',                                                                    'DE' => '德国',
    'France' => '法国',                                                                     'FR' => '法国',
    'Italy' => '意大利',                                                                    'IT' => '意大利',
    'Spain' => '西班牙',                                                                    'ES' => '西班牙',
    'Russia' => '俄罗斯', 'The Russian Federation' => '俄罗斯',                             'RU' => '俄罗斯',
    'Netherlands' => '荷兰', 'The Netherlands' => '荷兰',                                   'NL' => '荷兰',
    'Belgium' => '比利时',                                                                  'BE' => '比利时',
    'Switzerland' => '瑞士',                                                                'CH' => '瑞士',
    'Austria' => '奥地利',                                                                  'AT' => '奥地利',
    'Poland' => '波兰',                                                                     'PL' => '波兰',
    'Sweden' => '瑞典',                                                                     'SE' => '瑞典',
    'Norway' => '挪威',                                                                     'NO' => '挪威',
    'Denmark' => '丹麦',                                                                    'DK' => '丹麦',
    'Finland' => '芬兰',                                                                    'FI' => '芬兰',
    'Ireland' => '爱尔兰',                                                                  'IE' => '爱尔兰',
    'Portugal' => '葡萄牙',                                                                 'PT' => '葡萄牙',
    'Greece' => '希腊',                                                                     'GR' => '希腊',
    'Czech' => '捷克', 'Czech Republic' => '捷克', 'Czechia' => '捷克',                     'CZ' => '捷克',
    'Hungary' => '匈牙利',                                                                  'HU' => '匈牙利',
    'Romania' => '罗马尼亚',                                                                'RO' => '罗马尼亚',
    'Ukraine' => '乌克兰',                                                                  'UA' => '乌克兰',
    'Belarus' => '白俄罗斯',                                                                'BY' => '白俄罗斯',
    'Kazakhstan' => '哈萨克斯坦',                                                           'KZ' => '哈萨克斯坦',
    // ── Americas ────────────────────────────────────────────────────────────
    'United States Of America' => '美国', 'The United States Of America' => '美国',
    'United States' => '美国', 'America' => '美国', 'USA' => '美国', 'U.S.A.' => '美国', 'U.S.' => '美国',
                                                                                            'US' => '美国',
    'Canada' => '加拿大',                                                                   'CA' => '加拿大',
    'Brazil' => '巴西',                                                                     'BR' => '巴西',
    'Mexico' => '墨西哥',                                                                   'MX' => '墨西哥',
    'Argentina' => '阿根廷',                                                                'AR' => '阿根廷',
    'Chile' => '智利',                                                                      'CL' => '智利',
    'Colombia' => '哥伦比亚',                                                               'CO' => '哥伦比亚',
    'Peru' => '秘鲁',                                                                       'PE' => '秘鲁',
    'Venezuela' => '委内瑞拉',                                                              'VE' => '委内瑞拉',
    'Ecuador' => '厄瓜多尔',                                                                'EC' => '厄瓜多尔',
    // ── Asia-Pacific ────────────────────────────────────────────────────────
    'Australia' => '澳大利亚',                                                              'AU' => '澳大利亚',
    'New Zealand' => '新西兰',                                                              'NZ' => '新西兰',
    'India' => '印度',                                                                      'IN' => '印度',
    'Thailand' => '泰国',                                                                   'TH' => '泰国',
    'Vietnam' => '越南', 'Viet Nam' => '越南',                                              'VN' => '越南',
    'Malaysia' => '马来西亚',                                                               'MY' => '马来西亚',
    'Indonesia' => '印尼',                                                                  'ID' => '印尼',
    'Philippines' => '菲律宾',                                                              'PH' => '菲律宾',
    'Pakistan' => '巴基斯坦',                                                               'PK' => '巴基斯坦',
    'Bangladesh' => '孟加拉',                                                               'BD' => '孟加拉',
    'Sri Lanka' => '斯里兰卡',                                                              'LK' => '斯里兰卡',
    'Nepal' => '尼泊尔',                                                                    'NP' => '尼泊尔',
    'Myanmar' => '缅甸', 'Burma' => '缅甸',                                                 'MM' => '缅甸',
    'Cambodia' => '柬埔寨',                                                                 'KH' => '柬埔寨',
    'Laos' => '老挝', "Lao People's Democratic Republic" => '老挝',                         'LA' => '老挝',
    'Brunei' => '文莱', 'Brunei Darussalam' => '文莱',                                      'BN' => '文莱',
    // ── Middle East ─────────────────────────────────────────────────────────
    'Turkey' => '土耳其',                                                                   'TR' => '土耳其',
    'Saudi Arabia' => '沙特',                                                               'SA' => '沙特',
    'UAE' => '阿联酋', 'United Arab Emirates' => '阿联酋',                                  'AE' => '阿联酋',
    'Israel' => '以色列',                                                                   'IL' => '以色列',
    'Egypt' => '埃及',                                                                      'EG' => '埃及',
    'Qatar' => '卡塔尔',                                                                    'QA' => '卡塔尔',
    'Kuwait' => '科威特',                                                                   'KW' => '科威特',
    'Bahrain' => '巴林',                                                                    'BH' => '巴林',
    'Oman' => '阿曼',                                                                       'OM' => '阿曼',
    'Jordan' => '约旦',                                                                     'JO' => '约旦',
    'Lebanon' => '黎巴嫩',                                                                  'LB' => '黎巴嫩',
    'Syria' => '叙利亚', 'Syrian Arab Republic' => '叙利亚',                                'SY' => '叙利亚',
    'Iraq' => '伊拉克',                                                                     'IQ' => '伊拉克',
    'Iran' => '伊朗', 'Islamic Republic Of Iran' => '伊朗',                                 'IR' => '伊朗',
    'Afghanistan' => '阿富汗',                                                              'AF' => '阿富汗',
    // ── Africa ──────────────────────────────────────────────────────────────
    'South Africa' => '南非',                                                               'ZA' => '南非',
    'Nigeria' => '尼日利亚',                                                                'NG' => '尼日利亚',
    'Morocco' => '摩洛哥',                                                                  'MA' => '摩洛哥',
    'Kenya' => '肯尼亚',                                                                    'KE' => '肯尼亚',
    'Ghana' => '加纳',                                                                      'GH' => '加纳',
    'Tanzania' => '坦桑尼亚', 'United Republic Of Tanzania' => '坦桑尼亚',                  'TZ' => '坦桑尼亚',
    'Ethiopia' => '埃塞俄比亚',                                                             'ET' => '埃塞俄比亚',
    'Algeria' => '阿尔及利亚',                                                              'DZ' => '阿尔及利亚',
    'Tunisia' => '突尼斯',                                                                  'TN' => '突尼斯',
    'Sudan' => '苏丹',                                                                      'SD' => '苏丹',
    'Uganda' => '乌干达',                                                                   'UG' => '乌干达',
    'Zimbabwe' => '津巴布韦',                                                               'ZW' => '津巴布韦',
    'Namibia' => '纳米比亚',                                                                'NA' => '纳米比亚',
    'Botswana' => '博茨瓦纳',                                                               'BW' => '博茨瓦纳',
    'Zambia' => '赞比亚',                                                                   'ZM' => '赞比亚',
    'Madagascar' => '马达加斯加',                                                           'MG' => '马达加斯加',
]);

// ---------------------------------------------------------------------------
// Label key map  —  maps Chinese internal values → English label keys used in
// lang/*.json under the "labels" section (for i18n display of regions/types).
// ---------------------------------------------------------------------------
define('LABEL_KEYS', [
    // ── Countries / Regions ─────────────────────────────────────────────────
    '中国' => 'china',     '日本' => 'japan',   '韩国'     => 'korea',     '台湾'   => 'taiwan',  '香港'     => 'hongkong',
    '新加坡' => 'singapore', '英国' => 'uk',     '德国'     => 'germany',   '法国'   => 'france',  '意大利'   => 'italy',
    '西班牙' => 'spain',    '俄罗斯' => 'russia', '美国'    => 'usa',       '加拿大' => 'canada',
    '澳大利亚' => 'australia', '澳洲' => 'australia', '新西兰' => 'newzealand',
    '巴西' => 'brazil',    '墨西哥' => 'mexico', '阿根廷'   => 'argentina',
    '瑞士' => 'switzerland', '南非' => 'southafrica', '葡萄牙' => 'portugal', '马来西亚' => 'malaysia', '奥地利' => 'austria',
    '印度' => 'india',     '泰国' => 'thailand', '越南'     => 'vietnam',   '印尼'   => 'indonesia',
    '菲律宾' => 'philippines', '土耳其' => 'turkey', '荷兰'  => 'netherlands', '比利时' => 'belgium',
    '波兰' => 'poland',    '瑞典' => 'sweden',   '挪威'     => 'norway',    '丹麦'   => 'denmark',
    '芬兰' => 'finland',   '爱尔兰' => 'ireland', '希腊'    => 'greece',    '捷克'   => 'czech',
    '匈牙利' => 'hungary', '罗马尼亚' => 'romania', '埃及'  => 'egypt',     '以色列' => 'israel',
    '阿联酋' => 'uae',     '沙特' => 'saudi',    '其他'     => 'other',     '全球'   => 'global',
    '乌克兰' => 'ukraine', '白俄罗斯' => 'belarus', '哈萨克斯坦' => 'kazakhstan',
    '智利' => 'chile',     '哥伦比亚' => 'colombia', '秘鲁' => 'peru',     '委内瑞拉' => 'venezuela', '厄瓜多尔' => 'ecuador',
    '澳门' => 'macau',     '巴基斯坦' => 'pakistan', '孟加拉' => 'bangladesh', '斯里兰卡' => 'srilanka', '尼泊尔' => 'nepal',
    '缅甸' => 'myanmar',   '柬埔寨' => 'cambodia', '老挝'   => 'laos',     '文莱'   => 'brunei',
    '卡塔尔' => 'qatar',   '科威特' => 'kuwait',  '巴林'    => 'bahrain',   '阿曼'   => 'oman',
    '约旦' => 'jordan',    '黎巴嫩' => 'lebanon', '叙利亚'  => 'syria',    '伊拉克' => 'iraq',    '伊朗' => 'iran', '阿富汗' => 'afghanistan',
    '尼日利亚' => 'nigeria', '摩洛哥' => 'morocco', '肯尼亚' => 'kenya',   '加纳'   => 'ghana',
    '坦桑尼亚' => 'tanzania', '埃塞俄比亚' => 'ethiopia', '阿尔及利亚' => 'algeria', '突尼斯' => 'tunisia',
    '苏丹' => 'sudan',     '乌干达' => 'uganda',  '津巴布韦' => 'zimbabwe',
    '纳米比亚' => 'namibia', '博茨瓦纳' => 'botswana', '赞比亚' => 'zambia', '马达加斯加' => 'madagascar',
    // ── Genres / Types ───────────────────────────────────────────────────────
    '音乐' => 'music',     '新闻' => 'news',     '综合'     => 'general',   '交通'   => 'traffic',
    '体育' => 'sports',    '文艺' => 'arts',     '经典'     => 'classic',   '儿童'   => 'kids',
    '宗教' => 'religion',  '古典' => 'classical', '方言'    => 'dialect',
    '爵士' => 'jazz',      '流行' => 'pop',      '摇滚'     => 'rock',      '嘻哈'   => 'hiphop',
    '电子' => 'electronic', 'R&B' => 'rnb',      '乡村'     => 'country',   '民谣'   => 'folk',
    '蓝调' => 'blues',     '雷鬼' => 'reggae',   '金属'     => 'metal',     '拉丁'   => 'latin',
    '财经' => 'finance',
    // ── Chinese networks / provinces ────────────────────────────────────────
    '央广' => 'cnr',       '央视' => 'cctv',
    '全国' => 'national',  '北京' => 'beijing',  '天津'     => 'tianjin',   '上海'   => 'shanghai', '重庆' => 'chongqing',
    '广东' => 'guangdong', '广西' => 'guangxi',  '海南'     => 'hainan',    '福建'   => 'fujian',
    '江苏' => 'jiangsu',   '浙江' => 'zhejiang', '山东'     => 'shandong',  '安徽'   => 'anhui',
    '江西' => 'jiangxi',   '湖南' => 'hunan',    '湖北'     => 'hubei',     '河南'   => 'henan',
    '河北' => 'hebei',     '山西' => 'shanxi',   '辽宁'     => 'liaoning',  '吉林'   => 'jilin',
    '黑龙江' => 'heilongjiang', '四川' => 'sichuan', '贵州' => 'guizhou',
    '云南' => 'yunnan',    '西藏' => 'tibet',    '陕西'     => 'shaanxi',
    '甘肃' => 'gansu',     '青海' => 'qinghai',  '新疆'     => 'xinjiang',
    '宁夏' => 'ningxia',   '内蒙古' => 'innermongolia',
]);
