# 电台森林 📻

基于 PHP 的在线网络电台播放器。解析本地 M3U 播放列表，在浏览器中提供电台检索、分类筛选与在线收听功能。

## 目录结构

```
radioweb/
├── index.dev.php    # 源文件（开发时编辑此文件）
├── index.php        # 构建产物（部署到服务器的文件）
├── build.js         # 构建脚本（压缩 CSS/JS 生成 index.php）
├── package.json     # 构建依赖声明
└── radio_*.m3u      # 播放列表文件（需自行提供，见下方说明）
```

## 使用前提

> **必须**在项目根目录放置至少一个符合命名规则的 M3U 播放列表文件，否则页面将显示空列表。

播放列表文件名格式：

```
radio_<地区代码>.m3u
```

示例：

| 文件名         | 含义       |
| -------------- | ---------- |
| `radio_cn.m3u` | 中国电台   |
| `radio_us.m3u` | 美国电台   |
| `radio_jp.m3u` | 日本电台   |
| `radio_.m3u`   | 全球电台   |

地区代码遵循 ISO 3166-1 alpha-2 标准（`cn`、`us`、`gb`、`jp`、`kr` 等）。可同时放置多个文件，系统会合并全部列表。

### M3U 文件格式

```m3u
#EXTM3U
#EXTINF:-1 tvg-name="中央人民广播电台" tvg-logo="https://example.com/logo.png" group-title="China",中央人民广播电台
http://lhttp.cnr.cn/live/zgzs/64k.mp3
```

支持的标签字段：

- `tvg-name` — 电台名称
- `tvg-logo` — 台标图片 URL
- `group-title` — 国家/分组（用于筛选面板）

## 部署

### 方式一：直接使用构建产物（推荐）

将 `index.php` 及 `radio_*.m3u` 文件上传至支持 PHP 的 Web 服务器同一目录，即可访问。

### 方式二：从源码构建

修改 `index.dev.php` 后，运行构建命令生成压缩版 `index.php`：

```bash
# 安装构建依赖（仅首次）
npm install

# 执行构建
node build.js
```

构建完成后将 `index.php` 部署到服务器。`node_modules/` 和 `package-lock.json` 无需上传。

## 功能特性

- **多列表合并** — 自动读取目录下所有 `radio_*.m3u` 文件并合并
- **国家/地区筛选** — 支持 40+ 个国家，带国旗图标
- **分类筛选** — 音乐、新闻、交通、体育、文艺、儿童等分类，中国地区额外支持省份与央广/央视细分
- **关键词搜索** — 实时过滤电台名称
- **多主题配色** — 翠绿、青绿、天蓝、橙色、琥珀、玫瑰、深红、粉红、紫色、靛蓝、灰度、黑白共 12 套
- **全屏播放器** — 带音波动画与实时时钟显示
- **响应式设计** — 适配手机、平板及桌面，支持横屏模式

## 运行环境

| 组件       | 要求                       |
| ---------- | -------------------------- |
| PHP        | 5.6+（服务端解析 M3U 文件）|
| Web 服务器 | Apache / Nginx / 其他      |
| 浏览器     | 支持 HTML5 `<audio>` 的现代浏览器 |
| Node.js    | 仅构建时需要（≥ 14）       |

## 开发说明

- 编辑 `index.dev.php`，其中包含未压缩的 PHP / HTML / CSS / JavaScript
- 运行 `node build.js` 后，CSS 与 JS 会被 [Terser](https://terser.org/) 压缩并写入 `index.php`
- 构建脚本会保留 PHP 代码块（`<?php ... ?>`），不会破坏服务端逻辑
