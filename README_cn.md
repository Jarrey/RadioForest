# 电台森林 📻

基于 PHP 的在线网络电台播放器。它会自动扫描项目根目录下的 M3U 播放列表文件，解析电台信息并在浏览器中提供搜索、区域筛选、主题切换和实时播放功能。

## 目录

- [目录结构](#目录结构)
- [说明](#说明)
- [多语言界面](#多语言界面)
- [播放列表生成脚本](#播放列表生成脚本)
- [播放列表格式](#播放列表格式)
- [部署方式](#部署方式)
  - [方式一：直接部署 `index.php`](#方式一直接部署-indexphp)
  - [方式二：从源代码构建](#方式二从源代码构建)
  - [方式三：Docker 部署](#方式三docker部署)
- [功能亮点](#功能亮点)
- [运行环境](#运行环境)
- [开发说明](#开发说明)
- [额外提示](#额外提示)

## 目录结构

```text
radioweb/
├── index.dev.php          # 开发源文件，包含可编辑的 PHP/HTML/CSS/JS
├── index.php              # 构建产物，部署到服务器的文件
├── build.js               # 构建脚本，压缩 CSS/JS 并生成 index.php
├── radioBrowserService.py # radio-browser.info 请求辅助模块
├── syncInternetRatio.py   # 从电台数据生成 M3U 播放列表
├── lang/                  # UI 翻译字典
└── package.json           # 构建依赖声明
```

## 说明

- 项目会读取根目录下所有符合 `radio_*.m3u` 的文件。
- 使用 `radio_<地区代码>.m3u` 作为播放列表文件命名方式。
- 如果没有找到任何播放列表文件，页面会显示空列表。
- UI 支持多语言，默认会根据浏览器本地语言自动切换。

## 多语言界面

- 翻译词典存放在 `lang/` 目录。
- 支持语言：简体中文、英文、西班牙语、法语、德语、意大利语、日语、韩语。
- 用户可以点击右上角语言选择框切换语言，并显示对应国旗。
- 分类筛选、国家/地区等 UI 文本也已支持翻译。

## 播放列表生成脚本

仓库包含两个辅助 Python 脚本，用于从 radio-browser.info 获取电台数据并生成用于页面的播放列表文件：

- `radioBrowserService.py` — 提供 radio-browser API 请求功能。
- `syncInternetRatio.py` — 下载指定国家/地区的电台并写入 `radio_<code>.m3u` 和 `radio.m3u`。

使用示例：

```bash
python syncInternetRatio.py CN,US --target-dir . --backup-dir ./backup
```

默认禁用代理；如果需要启用代理，请传入 `--proxy`，脚本将使用标准的 `HTTP_PROXY` / `HTTPS_PROXY` 环境变量。

可以将该脚本配置为计划任务，定期自动刷新播放列表。例如，在 Linux/macOS 上使用 `cron`，或在 Windows 上使用任务计划程序，每天固定时间运行一次。

### syncInternetRatio.py 参数说明

- `countries`（必选）
  - 逗号分隔的 ISO 3166-1 alpha-2 国家/地区代码，例如 `CN,US,GB`。
- `--target-dir DIR`
  - 生成 M3U 文件的目录。默认值：`.`
- `--backup-dir DIR`
  - 备份现有播放列表文件的 ZIP 保存目录。默认值：`./backup`
- `--no-backup`
  - 跳过对现有 `radio_<code>.m3u` 和 `radio.m3u` 文件的备份。
- `--show-broken`
  - 包含上次检测失败的电台。默认情况下会过滤掉损坏电台。
- `--page-size N`
  - 每次 API 请求获取的电台数量。默认值：`500`。
- `--timeout SEC`
  - 每次 API 请求的 socket 超时时间，单位为秒。默认值：`120`。
- `--proxy`
  - 启用 HTTP 代理，使用标准 `HTTP_PROXY` / `HTTPS_PROXY` 环境变量。

## 播放列表格式

示例 M3U 文件内容：

```m3u
#EXTM3U
#EXTINF:-1 tvg-name="中央人民广播电台" tvg-logo="https://example.com/logo.png" group-title="China",中央人民广播电台
http://lhttp.cnr.cn/live/zgzs/64k.mp3
```

推荐文件名示例：

| 文件名             | 含义       |
| ------------------ | ---------- |
| `radio_cn.m3u`     | 中国电台   |
| `radio_us.m3u`     | 美国电台   |
| `radio_jp.m3u`     | 日本电台   |
| `radio_<地区>.m3u` | 区域电台   |

支持的标签字段：

- `tvg-name` — 电台名称
- `tvg-logo` — 台标图片 URL
- `group-title` — 国家/分组（用于筛选面板）

## 部署方式

### 方式一：直接部署 `index.php`

将 `index.php` 和 `radio_*.m3u` 文件上传到支持 PHP 的 Web 服务器即可。无需上传 `node_modules/` 和 `package-lock.json`。

### 方式二：从源代码构建

在 `index.dev.php` 修改后，通过构建脚本生成压缩部署文件：

```bash
npm install
node build.js
```

构建后，`build.js` 会将页面内的 CSS 和 JavaScript 压缩后写入 `index.php`，并保留原始 PHP 逻辑。

### 方式三：Docker 部署

项目已提供 Docker 部署配置，将 Web 应用、Nginx、PHP-FPM 和 Python 同步脚本打包到一个镜像中。

#### Docker 镜像包含内容

- `index.php` 作为 Web 应用入口
- `lang/` 翻译字典
- `radioBrowserService.py` 请求辅助模块
- `syncInternetRatio.py` 同步脚本
- `Dockerfile`, `docker-compose.yml`, `start.sh`, `sync.sh`
- `nginx.conf` 用于 PHP-FPM 转发

#### 主机目录挂载说明

Docker Compose 会将以下主机目录挂载到容器：

- `./backup` → `/var/www/html/backup`
- `./logs` → `/var/www/html/logs`
- 当前仓库根目录 → `/var/www/html`

> `.dockerignore` 已排除 `radio_*.m3u` 和 `radio.m3u`，播放列表文件不会打包进镜像，使用宿主机挂载保持数据持久化。

#### 准备 `.env`

复制样例配置文件并根据实际需求修改：

```powershell
cd docker
copy .env.sample .env
```

在 `docker/.env` 中设置：

- `HTTP_PORT` - 容器对外暴露端口
- `SYNC_COUNTRIES` - 同步的国家/地区代码
- `SYNC_TARGET_DIR` - 生成播放列表的目标目录
- `SYNC_BACKUP_DIR` - 备份目录
- `SYNC_CRON` - 定时任务表达式（可选）

若不希望定时同步，可将 `SYNC_CRON` 留空。

#### 构建镜像

使用提供的 PowerShell 脚本构建镜像。该脚本会在 `index.dev.php` 比 `index.php` 新，或 `index.php` 不存在时，自动先执行 `node build.js` 生成最新的 `index.php`。

```powershell
cd docker
.\build-docker.ps1
```

如果要自定义镜像标签：

```powershell
cd docker
.\build-docker.ps1 -Tag "radioforest:1.0"
```

#### 使用 Docker Compose 运行

启动容器：

```bash
cd docker
docker compose up --build -d
```

或者在仓库根目录运行：

```bash
docker compose -f docker/docker-compose.yml up --build -d
```

#### 使用 GHCR 预构建镜像运行
预构建镜像地址：`ghcr.io/jarrey/radioforest:latest`。
使用下面的示例 compose 文件即可直接部署，无需在本地构建：

```bash
cd docker
copy .env.sample .env
# 或在 Linux/macOS 上：
# cp .env.sample .env

docker compose -f docker/docker-compose-ghcr.yml up -d
```

然后访问：

```text
http://localhost:18882
```

停止服务：

```bash
cd docker
docker compose down
```

或者在仓库根目录运行：

```bash
docker compose -f docker/docker-compose.yml down
```

#### 手动同步与定时任务

手动运行同步命令：

```bash
cd docker
docker compose exec app sh -c "./sync.sh"
```

或者在仓库根目录运行：

```bash
docker compose -f docker/docker-compose.yml exec app sh -c "./sync.sh"
```

若已配置 `SYNC_CRON`，容器会启动 `crond` 并按计划执行同步任务，例如：

```text
SYNC_CRON=30 1 * * *
```

定时任务日志写入：

- `./logs/cron.log`

手动同步日志写入：

- `./logs/sync.log`

#### 其他说明

- 镜像不会包含本地 `radio_*.m3u` 文件。
- 请使用主机挂载方式提供播放列表、备份和日志目录。
- 更改 Docker 配置后，可重新执行：

```bash
cd docker
docker compose up --build -d
```

或者在仓库根目录运行：

```bash
docker compose -f docker/docker-compose.yml up --build -d
```

## GitHub Actions 自动构建

此仓库已经包含 GitHub Actions 工作流，在每次推送到 `main` 或手动触发时，自动构建并发布 Docker 镜像。

工作流会先执行 `node build.js` 生成最新的 `index.php`，然后将镜像推送到 GitHub Container Registry：

- `ghcr.io/${{ github.repository_owner }}/radioforest:latest`
- `ghcr.io/${{ github.repository_owner }}/radioforest:${{ github.sha }}`

工作流文件位置：`.github/workflows/docker-build.yml`

## 功能亮点

- 多列表合并：读取所有 `radio_*.m3u` 文件并展示电台
- 国家/地区筛选：支持多地区筛选，并显示国旗图标
- 多语言界面：默认使用浏览器语言，并支持点击切换语言和国旗显示
- 关键词搜索：快速过滤电台名称
- 主题切换：提供 12 种配色主题
- 全屏播放器：带音波动画、播放状态和时钟显示
- 响应式设计：支持手机、平板和桌面浏览器

## 运行环境

| 组件       | 要求                                  |
| ---------- | ------------------------------------- |
| PHP        | 5.6+                                  |
| Python     | 3.x                                   |
| requests   | 用于 HTTP API 请求的 Python 包         |
| Web 服务器 | Apache / Nginx / 其他支持 PHP 的服务器 |
| 浏览器     | 支持 HTML5 `<audio>` 的现代浏览器      |
| Node.js    | 仅构建时需要（建议 ≥ 14）             |

## 开发说明

- `index.dev.php` 是可编辑源文件，包含 HTML、CSS、JavaScript 和 PHP 解析逻辑。
- `build.js` 会压缩内联 CSS 以及 `<script>` 中的 JavaScript，并输出到 `index.php`。
- 当前构建依赖为 `terser`，已在 `package.json` 中声明。

## 额外提示

- 建议将播放列表文件和 `index.php` 放在同一目录下。
- `group-title` 字段会被映射为中文国家名，提升筛选体验。
- 如果需要新增主题或界面样式，可直接编辑 `index.dev.php` 并重新运行 `node build.js`。
