# RadioForest 代码审查报告

> 分析模型：**deepseek-v4-pro (deepseek/deepseek-v4-pro)**
>
> 分析日期：2026-04-30

---

## 项目概况

| 项目 | Radio Forest (电台森林) |
|------|------------------------|
| 类型 | PHP 在线电台 Web 播放器 |
| 技术栈 | PHP 5.6+, JavaScript (jQuery), Python 3, Docker, Nginx |
| 代码规模 | ~3400 行 (index.dev.php) + ~380 行 Python + ~200 行 Shell/Docker |

---

## 一、Bug（功能性缺陷）

### B-1: XSS 注入风险 — 电台名称未做 HTML 转义

**文件：** `index.dev.php:2451`
**严重程度：** 🔴 高

电台名称在渲染卡片时直接以 `${station.name}` 插入 HTML，未经过任何 HTML 实体转义：

```javascript
// line 2451 — 电台名称直接拼接，无 HTML 转义
html += `<div class="station-name">${station.name}</div>`;
```

现有的 `esc()` 函数（`index.dev.php:2400`）仅转义 `&` 和 `"`，不转义 `<`、`>`、`'`：

```javascript
function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
}
```

**影响：** 如果 M3U 播放列表文件中包含恶意构造的电台名称（如 `<img src=x onerror=alert(1)>`），该脚本将在用户浏览器中执行。

**修复建议：**
```javascript
function esc(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
// 在渲染电台名称时使用
html += `<div class="station-name">${esc(station.name)}</div>`;
```

---

### B-2: `setInterval` 时钟定时器永不释放

**文件：** `index.dev.php:3321`
**严重程度：** 🟡 中

```javascript
setInterval(updateClock, 1000);  // 始终运行，即使全屏播放器关闭
```

全屏时钟每秒更新一次，但定时器无论全屏是否可见都在运行，浪费 CPU 资源。如果在页面中存在多个实例（如多次打开/关闭），可能导致定时器堆积。

**修复建议：** 仅在 `showFullscreen()` 中启动时钟，在 `hideFullscreen()` 中停止：

```javascript
let clockInterval = null;

function showFullscreen() {
    // ... existing code ...
    updateClock();
    clockInterval = setInterval(updateClock, 1000);
}

function hideFullscreen() {
    // ... existing code ...
    if (clockInterval) { clearInterval(clockInterval); clockInterval = null; }
}
```

---

### B-3: CSS 主题 `"black"` 定义了但不可选

**文件：** `index.dev.php:463-480` (CSS), `index.dev.php:2384-2385` (JS)
**严重程度：** 🟡 中

CSS 中定义了 `[data-theme="black"]` 主题变量（黑白高对比度主题），但 JavaScript 的 `THEME_KEYS` 和 `THEME_COLORS` 中未包含 `'black'`，用户无法选择该主题。

```javascript
// 缺少 'black' 主题
const THEME_COLORS = {green:'#22c55e', teal:'#14b8a6', cyan:'#06b6d4', ...};
const THEME_KEYS   = ['green','teal','cyan','orange','amber','rose','red','pink','purple','indigo','grayscale','bw'];
```

**修复建议：** 将 `'black'` 加入 `THEME_COLORS` 和 `THEME_KEYS`，并为英文/中文语言包添加对应翻译键。

---

### B-4: 无限滚动使用无节流的 `scroll` 事件

**文件：** `index.dev.php:3080-3084`
**严重程度：** 🟡 中

```javascript
$(window).on('scroll', function () {
    if ($(window).scrollTop() + $(window).height() >= $(document).height() - 200) {
        loadMore();
    }
});
```

每次滚动都触发回调，调用 `loadMore()` 内的 `filterAndRender()`，其中包含 DOM 查询和重渲染。即使 `isLoading` 为 true 会跳过，条件判断中的 `$(window).scrollTop()` + `$(window).height()` 也每次执行。

**修复建议：** 使用 `IntersectionObserver` 监听 `#loadingMore` 元素，或者至少添加 `requestAnimationFrame` 节流：

```javascript
// 方案 A: IntersectionObserver（推荐）
const loadingMoreEl = document.getElementById('loadingMore');
const observer = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting) loadMore();
});
observer.observe(loadingMoreEl);

// 方案 B: requestAnimationFrame 节流
let ticking = false;
$(window).on('scroll', function () {
    if (!ticking) {
        requestAnimationFrame(() => {
            if ($(window).scrollTop() + $(window).height() >= $(document).height() - 200) {
                loadMore();
            }
            ticking = false;
        });
        ticking = true;
    }
});
```

---

### B-5: PHP `$regionCodes` 数组含重复键

**文件：** `index.dev.php:1999`, `index.php:345`
**严重程度：** 🟢 低

```php
$regionCodes = [
    '中国' => 'cn', ..., '澳大利亚' => 'au', ...  // line 1991
    '匈牙利' => 'hu', '罗马尼亚' => 'ro', '埃及' => 'eg', '以色列' => 'il',
    '阿联酋' => 'ae', '沙特' => 'sa', '澳大利亚' => 'au', '其他' => 'un',  // line 1999 - 重复!
];
```

`'澳大利亚' => 'au'` 出现了两次。PHP 会静默使用最后一个值，不会报错，但在 JS 侧也做了同样的重复（`index.dev.php:2164`）。这不造成功能问题，但表明代码有冗余。

**修复建议：** 删除重复的条目。

---

## 二、Defect（代码缺陷）

### D-1: `saveStationCache()` 返回值未检查

**文件：** `index.dev.php:206`
**严重程度：** 🟡 中

```php
saveStationCache($cacheFile, $files, $allStations);  // 未检查返回值
```

`saveStationCache()` 在写入失败时返回 `false`，但调用处忽略了这个返回值。如果目录权限不足导致写入失败，缓存不会更新，下次请求仍需重新解析所有 M3U 文件，但开发者不会得到任何错误反馈。

**修复建议：**
```php
if (!saveStationCache($cacheFile, $files, $allStations)) {
    error_log('Failed to save station cache to ' . $cacheFile);
}
```

---

### D-2: `saveStationCache()` 写入失败遗留 `.tmp` 文件

**文件：** `index.dev.php:153-157`
**严重程度：** 🟢 低

```php
$tmpFile = $cacheFile . '.tmp';
if (file_put_contents($tmpFile, json_encode($data, JSON_UNESCAPED_UNICODE)) === false) {
    return false;
}
return rename($tmpFile, $cacheFile);
```

如果 `file_put_contents` 成功但 `rename` 失败（例如跨文件系统、权限不足），`.tmp` 文件会残留在磁盘上。多次失败会累积 `.tmp` 文件。

**修复建议：** 在 `rename` 失败时清理 `.tmp` 文件。

---

### D-3: `parseM3U()` 对 `#EXTINF` 处理不完整

**文件：** `index.dev.php:19-50`
**严重程度：** 🟢 低

- 只支持了 `tvg-name`、`tvg-logo`、`group-title` 三个属性，但 M3U 扩展标签中常见的 `tvg-id`、`radio`、`url-tvg` 等未提取
- `#EXTINF` 行中逗号后面的描述文本（如 `,CNR Radio`）未被捕获，虽然 `tvg-name` 通常已包含名称
- `name` 字段未从 `#EXTINF` 行逗号后的描述提取作为回退

**修复建议：** 至少添加逗号后的名称回退：
```php
// 如果 tvg-name 未设置，从逗号后提取
if (empty($name) && preg_match('/#EXTINF:[^,]*,(.*)/', $line, $m)) {
    $name = trim($m[1]);
}
```

---

### D-4: URL 校验不够严谨

**文件：** `index.dev.php:41`
**严重程度：** 🟢 低

```php
if (!empty($url) && strpos($url, '#') !== 0 && strpos($url, 'http') === 0) {
```

只检查 URL 是否以 `http` 开头，忽略了 `https` 也需要 `s`。虽然 `strpos($url, 'http') === 0` 能匹配 HTTPS（因为 HTTPS 也以 `http` 开头），但无法排除 `httpXYZ://` 这类无效协议。建议改为 `strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0`。

---

### D-5: Docker `start.sh` 缺少进程监控

**文件：** `docker/start.sh:34-48`
**严重程度：** 🟡 中

PHP-FPM 和 Nginx 以后台进程启动，如果任一进程崩溃退出，容器仍会运行但服务不可用。没有进程管理器（如 supervisord）或监控机制来重启崩溃的进程。

**修复建议：** 使用 `supervisord` 或添加健康检查循环：
```sh
# 方案：添加简单的进程监控
while true; do
    if ! kill -0 $PHP_FPM_PID 2>/dev/null; then
        php-fpm --nodaemonize 2>>/var/log/php/php-fpm.log &
        PHP_FPM_PID=$!
    fi
    if ! kill -0 $NGINX_PID 2>/dev/null; then
        nginx -g 'daemon off;' &
        NGINX_PID=$!
    fi
    sleep 5
done
```

---

### D-6: `.env` 文件解析存在缺陷

**文件：** `docker/start.sh:12-18`
**严重程度：** 🟢 低

```sh
while IFS='=' read -r key value; do
    case "$key" in
      ''|\#*) continue ;;
      export*) key=${key#export } ;;
    esac
    export "$key=$value"
done < /var/www/html/.env
```

这个简单的 `.env` 解析器存在以下问题：
- 不支持带等号的值（如 `KEY=value=with=equals`）
- 不支持带空格的值（如 `KEY=value with spaces`）
- 不支持引号包裹的值（如 `KEY="value"`）

---

### D-7: 缺少 `config.php` 敏感文件保护

**文件：** `nginx.conf:36-38`, `index.dev.php:161-163`

`config.php` 虽然定义了 `PLAYLIST_DIR` 和 `CACHE_FILE`，但没有被保护。如果有人上传了包含敏感信息的 `config.php`（如数据库密钥），虽然 `.php` 文件通过 PHP-FPM 无源码泄露风险，但 `.gitignore` 中未排除 `config.php`，可能导致意外提交到 Git。

**修复建议：** 将 `config.php` 添加到 `.gitignore`，或在 `nginx.conf` 中额外禁止访问。

---

## 三、优化建议（Optimization）

### O-1: 使用 `IntersectionObserver` 替代 `scroll` 事件实现无限滚动

**文件：** `index.dev.php:3080-3084`

如 B-4 所述，`scroll` 事件触发频繁。改用 `IntersectionObserver` 可显著降低 CPU 使用。

---

### O-2: 可为电台列表添加虚拟滚动

**文件：** `index.dev.php:2467`

当用户筛选后仍有数百个电台卡片时，`renderStations()` 一次性创建所有 DOM 节点。考虑使用虚拟滚动（Virtual Scrolling）只渲染可视区域内的卡片。对于本项目规模（千级电台），当前性能尚可，但这是未来扩展的方向。

---

### O-3: `provincePatterns` 正则对象预编译缓存

**文件：** `index.dev.php:2221-2301`

`provincePatterns` 是一个大型对象，其值都是内联正则表达式字面量。每次调用 `detectProvince()` 都会遍历所有键值并使用这些正则。这些内容约 80 行，在构建时无法被压缩（build.js 只处理 `<script>` 块内的代码到单行）。虽然 JS 引擎会缓存正则，但对该对象本身可以考虑放到 JSON 文件或使用更紧凑的格式。

---

### O-4: 为生产构建启用 `drop_console`

**文件：** `build.js:28`

```javascript
compress: { ... drop_console:false, ... }
```

生产环境中的 `console.log` 输出会增加不必要的日志。对于生产构建，可考虑加入 `drop_console:true` 或保留开发构建的 `drop_console:false`。

不过需要注意，代码中错误回调使用了 `console.warn`（如 `index.dev.php:2613`、`index.dev.php:2647`），如果保留这些日志有助于调试，可只移除 `console.log`：

```javascript
compress: { ... drop_console:true, pure_funcs: ['console.log'] }
```

---

### O-5: 添加 Service Worker 实现离线缓存

作为 PWA 增强，可添加 Service Worker 来缓存静态资源和播放列表数据，使得用户在离线或网络不稳定时仍能浏览已加载的电台列表。`radio-icon.svg` 已有，添加 `manifest.json` 即可。

---

### O-6: 搜索防抖时间可配置

**文件：** `index.dev.php:3071-3077`

搜索防抖固定为 300ms。在低端移动设备上，`filterAndRender(true)` 计算可能较重，可增大到 400-500ms 以减少重渲染次数。

---

### O-7: 语言包可懒加载

**文件：** `index.dev.php:2772-2785`

当前的 `loadLocale()` 每次都通过 `fetch('lang/...')` 拉取 JSON 文件（带 `cache: 'no-cache'`）。实际上多语言内容可随 `index.php` 一并内联输出（通过 PHP 判断用户语言），或至少使用 `localStorage` 缓存已加载的语言包，减少网络请求。

---

### O-8: Python `radioBrowserService.py` - `get_radiobrowser_base_urls()` 无缓存

**文件：** `scripts/radioBrowserService.py:25-31`

每次调用都会执行 DNS 查询。如果脚本要下载多个国家（如 `.env.sample` 中的 21 个国家），会重复调用 DNS 21 次。建议在模块加载时缓存一次：

```python
_cached_servers = None

def get_radiobrowser_base_urls():
    global _cached_servers
    if _cached_servers is None:
        # ... existing DNS logic ...
        _cached_servers = ["https://" + h for h in hosts]
    return _cached_servers
```

---

### O-9: `syncInternetRatio.py` - `backup()` 函数路径不安全

**文件：** `scripts/syncInternetRatio.py:28-40`

备份文件使用日期戳命名 `radio_station_YYYY-MM-DD.bak.zip`，同一天多次运行会覆盖之前的备份。可加入时间戳或仅当文件变化时才备份。

---

### O-10: M3U 文件结构优化

当前 `syncInternetRatio.py` 使用 `'\r\n'` 作为行分隔符，但也用 `'\n'` 在 `EXTHTTP` 前加了换行。混合的换行风格可能导致某些播放器解析异常。建议统一使用 `'\r\n'` 或 `'\n'`。

---

## 四、安全性

### S-1: 外部资源无完整性校验

**文件：** `index.dev.php:251`

```html
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
```

jQuery 加载使用了 `integrity` 校验，这是好的。但国旗图片来自 `https://flagcdn.com/w20/`，无 SRI 校验。如果该 CDN 被劫持，页面可能嵌入恶意内容。

**修复建议：** 在 CSP header 中限制图片来源，或下载国旗到本地提供。

### S-2: 缓存文件缺乏访问控制

**文件：** `config.php:14`

`stations.cache.json` 是纯 JSON 文件，如果位于 web 可访问目录，攻击者可以直接读取电台列表数据。虽然此项目的数据不是高度敏感的，但建议将缓存文件放在 webroot 之外的目录。

**修复建议：** 在 `nginx.conf` 中禁止 `.cache.json` 文件的直接访问：

```nginx
location ~ /\.|\.cache\.json$ {
    deny all;
}
```

---

## 五、总结

| 类别 | 数量 | 说明 |
|------|------|------|
| Bug | 5 | 含 1 个高危 XSS、4 个中低危 |
| Defect | 7 | 代码质量/健壮性问题 |
| 优化 | 10 | 性能/可维护性改进 |
| 安全 | 2 | 资源完整性 & 访问控制 |

**最优先修复项：**
1. **B-1** XSS 注入风险（电台名称 HTML 转义）
2. **B-2** 时钟定时器内存泄漏
3. **S-1** 外部资源完整性校验
4. **B-4** 无限滚动性能优化

---

*本报告由 deepseek-v4-pro (deepseek/deepseek-v4-pro) 模型自动生成，建议结合实际业务场景评估修复优先级。*
