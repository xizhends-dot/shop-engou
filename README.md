# shop.engou.jp — 遠豪合同会社 オンラインショップ

`engou.jp`（主站，部署在 sakura FTP）之外，新建的**商品展示站**，运行在一台**全新的独立服务器**上，对外域名为 **`https://shop.engou.jp`**。两站通过导航栏互相关联。

**纯日文站**，带一个**文件型后台**（无需数据库）用于增删改商品、上传图片。

## 目录结构

```
shop/
├── config.php            # 站点/公司信息 + 互链 URL + 后台登录密码
├── products.php          # 商品数据加载器（实际数据在 data/products.json）
├── index.php             # 首页：BANNER 轮播 → おすすめ商品
├── list.php              # 商品一覧页（全部商品 + 分类筛选）
├── about.php             # 遠豪について（会社紹介 + 会社概要表）
├── product.php           # 商品详情页（?id=商品番号，多图画廊）
├── partials/product_card.php # 商品卡片（首页/一覧/关连 共用）
├── style.css             # 前台样式（白色主题，导航栏黑色）
├── partials/
│   ├── header.php        # 公共头部 + 导航栏（含「本社サイト」回链）
│   └── footer.php        # 公共底部 + 菜单脚本
├── data/
│   └── products.json     # 商品数据（storage=json 时使用）
├── db/
│   └── schema.sql        # MySQL 建表脚本 + 初始数据（storage=mysql 时用）
├── lib/
│   └── store.php         # 数据读写（JSON/MySQL 双驱动）+ 登录/CSRF/上传
├── admin/                # ★后台管理
│   ├── login.php         # 登录
│   ├── logout.php        # 退出
│   ├── index.php         # 商品列表
│   ├── edit.php          # 新增/编辑（含多图上传、删图）
│   ├── delete.php        # 删除
│   ├── featured.php      # おすすめ商品（首页推荐位）管理
│   ├── media.php         # 图片管理（文件夹式：新建文件夹/上传/复制路径/删除）
│   ├── banners.php       # 首页 BANNER 轮播图管理
│   ├── import.php        # CSV 导入/导出（批量增改）
│   ├── auth.php          # 公共引导（加载配置/会话）
│   ├── _layout.php       # 后台公共布局
│   └── admin.css         # 后台样式
├── images/products/      # 上传的商品图存放处
└── README.md
```

## 环境要求

- PHP 7.4+（已在 PHP 8.4 验证）
- 任意支持 PHP 的服务器：Nginx + PHP-FPM、Apache + mod_php、云主机/容器等
- 无需数据库。图片上传不依赖 `fileinfo` 扩展（用 `getimagesize` 兜底）

## 后台管理（如何编辑商品）

1. 访问 `https://shop.engou.jp/admin/`（或本地 `http://127.0.0.1:8765/shop/admin/`）。
2. 用 `config.php` 里的 `admin_password` 登录。**★上线前务必修改初始密码！★**
   - 可直接填明文，也可填更安全的 bcrypt 哈希（`password_hash()` 的输出，以 `$2y$` 开头）。
3. 登录后可以：
   - **商品一览**：查看/编辑/删除所有商品。
   - **新规追加 / 编辑**：填写商品番号、分类、商品名、标语、价格、徽章、商品详细，并**上传多张商品图**（第一张为主图，详情页可缩略图切换）。可勾选「削除」移除已上传的图。
   - 删除商品时，其上传的图片会一并从服务器清除。
4. 数据保存在 `data/products.json`，前台（首页/详情页）实时读取。备份只需复制该文件 + `images/products/` 目录。

### 商品字段说明
- `id`（商品番号）：**可自定义**，半角字母数字/连字符（如 `ENG-001`），不可重复；URL 和详情页都用它。编辑时不可更改。
- `category`：分类；`name`：商品名；`tag`：一览卡片短标语；`price`：价格（数字，税込）。
- `badge`：徽章（如 `人気No.1`/`NEW`，留空不显示）。
- `desc`：商品详细（详情页「商品詳細」，可换行）。
- `images`：图片路径数组，由后台上传维护；为空时显示「品牌色 + 图标」占位。
- `icon`/`accent`：占位图用图标与颜色（有真实图时忽略）。

> 详情页结构：商品名 → 商品番号 →（評価）→ 価格 →（属性）→ 問い合わせ/戻る → 商品詳細 →（多图时）画廊缩略图。

### 商品評価（星・レビュー件数）
- 编辑页「評価（0〜5・0.5刻み）」与「評価数（レビュー件数）」可设置展示用星级与评论数。
- 前台商品卡片与详情页会显示星标（支持半星）；评分为 0 且评论数为 0 时不显示。
- CSV 列名：`rating`（0〜5）、`reviews`（整数）。更新导入时空栏则保留原值。

### 商品图片管理（商品编辑页内）
- 编辑页的「商品画像」区是**可视化管理**：缩略图列表，**先头为主图（メイン）、2 张起为详情图（詳細1, 詳細2…）**，每张可「← →」调整顺序、「×」从该商品移除（移除不删除文件，文件仍保留在「画像管理」库中）。
- **批量添加**：下方拖放区支持拖拽多张图片 / 拖整个文件夹 / 多选 / 选文件夹上传；上传后立即加入图片列表。
- **按文件名排序**：上传的多张图会按文件名自然顺序排列（如 `1, 2, …, 10`，不会变成 `1, 10, 2`），所以详情图建议用 `1.jpg`、`2.jpg`… 命名。
- 也可展开「画像ライブラリから選択」从已上传的图勾选追加。

### 商品属性（カラー・サイズ 等）
- 编辑页底部「商品属性」可添加任意属性行：**属性名**（如 カラー）+ **选项**（逗号分隔，如 `レッド, ブラック, ホワイト`）。
- 前台商品详情页会在价格下方以标签（chips）形式展示这些属性。
- 数据存于商品的 `attributes` 字段；MySQL 模式存为 `products.attributes`（JSON 文本列，已含在 `db/schema.sql`；旧库需 `ALTER TABLE products ADD COLUMN attributes TEXT NULL`）。
- 注：属性为**展示用**（非购物车选项/库存）。

## 数据存储：JSON（默认）/ MySQL（可切换）

后端支持两种存储驱动，由 `config.php` 的 `'storage'` 决定，**前台和后台代码完全一致，无需改动**：

### A. JSON（默认，零数据库）
- `'storage' => 'json'`，数据存 `data/products.json`。
- 迁移/备份 = 复制 `data/products.json` + `images/products/`。最简单，推荐中小规模。

### B. MySQL
适合服务器已有 MySQL / 用 phpMyAdmin 管理的情况：

1. **建库建表**：在 phpMyAdmin 新建数据库（如 `engou_shop`，字符集 `utf8mb4`），选中它后导入 **`shop/db/schema.sql`**（含 3 个分类 + 8 个示例商品；也可只建表后用下面的迁移工具导入你现有数据）。
2. **填连接信息**：编辑 `config.php` 的 `'db'`（host / port / name / user / pass / charset）。
3. **（可选）迁移现有 JSON 数据**：如果你已经在 JSON 模式下编辑过商品，访问 **`/shop/admin/migrate.php`**（需登录）点「MySQL へ取り込む」，把 `products.json` 的全部商品写入 MySQL；也可命令行 `php shop/admin/migrate.php`。
4. **切换驱动**：把 `config.php` 的 `'storage'` 改为 `'mysql'`。完成，整站开始读写数据库。

> 需要 PHP 的 `pdo_mysql` 扩展（大多数主机默认开启）。未开启时后台会提示。
> 表结构：`categories`（分类）/ `products`（商品）/ `product_images`（商品图，按顺序，外键级联删除）。
> 图片文件本身仍存在 `images/products/` 目录（数据库只存路径），所以无论哪种驱动，`images/products/` 都要可写。

## 首页结构 / おすすめ商品（推荐位）

- **首页 `index.php`**：BANNER 轮播 → **おすすめ商品（推荐位，最多 12 个）**。推荐区底部有「すべての商品を見る」按钮跳转到商品一覧页。
- **商品一覧页 `list.php`**：展示全部商品 + 分类筛选（导航「商品一覧」指向这里）。
- **遠豪について页 `about.php`**：会社紹介 + 会社概要表（导航「遠豪について」指向这里）。
- **设置推荐**：后台「おすすめ」页勾选要在首页展示的商品（最多 12 个，超出会自动禁止勾选）；显示顺序＝勾选时的商品排列顺序。**未设置任何推荐时，首页自动显示前 8 个商品**作为兜底。
- 推荐设置存 `data/featured.json`（仅存商品番号列表），删除商品时会自动从推荐中移除。

## 首页 BANNER（后台「バナー管理」全部可视化设置）

banner 的**图片和文字全部在后台「バナー管理」设置**，无需编辑任何文件：

- **共通设置**（页面顶部）：小标题（eyebrow）、两个按钮的文字/链接、以及「既定タイトル／サブテキスト」（兜底文案）。存 `data/banner_settings.json`。
- **每张 banner**：上传图片后，可逐张填写 **タイトル / サブテキスト /「商品を見る」按钮链接**，并可上下调整顺序、删除。存 `data/banners.json`。
- **轮播**：2 张以上自动轮播（淡入淡出，每 5 秒），切到哪张就显示哪张的标题/内容；底部圆点可点击。
- **兜底**：某张 banner 没填标题/副标题 → 用共通设置里的「既定文案」；完全没上传 banner 图 → 显示品牌渐变背景 + 既定文案。
- 标题/文案支持 HTML：`<br>` 换行、`<span class='accent'>…</span>` 品牌绿强调。

首页顶部大横幅支持多图自动轮播：

1. 后台点「バナー管理」，上传一张或多张横幅图（推荐横长图，约 1600×700px）。
2. **2 张以上自动轮播**（淡入淡出，每 5 秒切换，底部有圆点可点击切换）。
3. 可给每张图设「リンク先URL」（点击跳转，如 `product.php?id=xxx`，留空则不可点）。
4. 可用「上/下」按钮调整顺序，点「並び順・リンクを保存」生效；删除会连同图片文件一起清除。
5. **未上传任何 banner 时**，自动回退到原来的品牌渐变背景（标语 + CTA 始终叠加在最上层）。

> banner 图存 `images/banners/`，列表存 `data/banners.json`（独立于商品数据，JSON/MySQL 两种模式都适用）。

## 图片管理（后台「画像管理」· 文件夹式）

像文件管理器一样按文件夹管理商品图：**左侧文件夹树形结构**（点击切换文件夹，悬停可删空文件夹），**右侧当前文件夹的图片（每页最多 40 张，可翻页）**。

1. **新建文件夹**：建议用商品番号命名（如 `aaa`），把该商品的图都放进去。
2. **进入文件夹**：点文件夹卡片进入，顶部有面包屑（products / aaa）可返回。
3. **上传到当前文件夹**：在哪个文件夹页上传，图片就存到该文件夹（如 `images/products/aaa/xxx.jpg`）。
4. **复制路径**：每张图旁「パスをコピー」复制相对路径，粘到 CSV 的 `images` 列或商品编辑使用。
5. **删除**：删图（会自动从引用它的商品移除）；删文件夹（仅当文件夹为空时可删，避免误删）。
6. 商品编辑页的「画像ライブラリから選択」会**递归显示所有文件夹**里的图片，方便跨文件夹选用。

> 文件夹名只允许半角英数・连字符・下划线。图片实际存放在 `images/products/<文件夹>/`。每张图还会显示「使用中：N件 / 未使用」，方便清理。

## CSV 批量导入 / 导出（后台「CSV取込」）

适合一次性录入/修改大量商品。在后台点「CSV取込」：

1. **下载 sample.csv**：带表头和**记入例**（含「多图用 `|` 分隔」的示例行），照着填即可。
2. **导出当前商品**：把现有商品导出成 CSV（UTF-8 带 BOM，Excel 双击不乱码），改完再导入。
3. **上传导入**：选 CSV 点「取り込む」。
   - 按 **`id`（商品番号）upsert**：id 已存在→更新该商品；不存在→新增。
   - 第一行必须是表头，列：`id,category,name,tag,price,badge,icon,accent,desc,images`
     （列顺序可不同，但必须含 `id,category,name,price` 这几列）。
   - `category` 填分类**键**（`beauty`/`home`/`kitchen`）或分类**名称**均可。
   - `images` 用 `|` 分隔多张图片路径（如 `images/products/a.jpg|images/products/b.jpg`）；**留空则保留该商品已有图片**。路径从后台「画像管理」上传后复制即可；图片文件本身在「画像管理」或「商品编辑」里上传。
   - 编码 UTF-8 或 Shift_JIS（Excel 导出）都可识别。
   - 导入后会报告「新規 X 件 / 更新 Y 件」，并列出被跳过的行及原因（如 id 非法、分类不存在、价格非数值等）。

## 两站导航如何关联

| 位置 | 链接 | 配置项 |
|------|------|--------|
| 主站 `engou.jp` 导航栏 | 「ショップ / 商城」→ `https://shop.engou.jp` | 主站 `config.php` 的 `shop_url` |
| 商城 `shop.engou.jp` 导航栏/页脚 | 「本社サイト」→ `https://engou.jp` | 本目录 `config.php` 的 `main_site_url` |

> 域名变化时，只需改这两个配置项。

## 本地预览

在仓库根目录执行：

```bash
php -S 127.0.0.1:8765
```

- 商品一览：`http://127.0.0.1:8765/shop/index.php`
- 商品详情：`http://127.0.0.1:8765/shop/product.php?id=nano-shower`
- 后台：`http://127.0.0.1:8765/shop/admin/`（密码见 `config.php`）

> 本地预览时，如想让「本社サイト」按钮指向本地主站，可临时把 `config.php` 的 `main_site_url` 改为 `../index.php`；上线前改回 `https://engou.jp`。

## 部署到新服务器

1. 将 `shop/` 目录内容上传到新服务器的站点根目录（让 `shop.engou.jp` 文档根指向这些文件）。
2. 修改 `config.php`：`main_site_url`、`shop_site_url`，以及 **`admin_password`（务必改）**。
3. **目录权限**：确保 PHP 进程对以下目录有写权限：
   - `data/`（保存 products.json）
   - `images/products/`（保存上传的图片）
4. DNS：为子域名 `shop` 添加记录指向新服务器：
   - 独立 IP → **A 记录**：`shop` → `新服务器IP`
   - 服务域名/CDN → **CNAME 记录**：`shop` → `新服务器域名`
5. 申请并配置 SSL 证书（推荐 Let's Encrypt），启用 `https://shop.engou.jp`。
6. 入口为 `index.php`（Nginx `index index.php;` / Apache `DirectoryIndex index.php`）。

### Nginx 参考配置

```nginx
server {
    listen 443 ssl;
    server_name shop.engou.jp;
    root /var/www/shop;          # 上传 shop/ 内容的目录
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/shop.engou.jp/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/shop.engou.jp/privkey.pem;

    client_max_body_size 10m;    # 允许图片上传（与8MB上限匹配）

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    # 数据文件可选：禁止直接访问（products.json 本身是公开数据，按需开启）
    # location = /data/products.json { deny all; }
}
```

## 功能说明

- **页面结构**：首页（BANNER + おすすめ商品）/ 商品一覧 `list.php` / 遠豪について `about.php` / 商品详情 `product.php`；联系方式在各页页脚（`#contact`）。
- **商品详情**：面包屑、多图画廊、商品番号、价格、邮件咨询、关联商品。
- **后台**：登录鉴权 + CSRF 防护，商品 CRUD + 多图上传 + **图片管理（媒体库）** + **CSV 批量导入/导出**（文件型 JSON 存储，零数据库）。
- **纯日文**：不含中文切换，维护成本低。
- **存储可选**：JSON（默认，零数据库）或 MySQL（带 `db/schema.sql` + 迁移工具），一个开关切换。
- **展示型**：不含购物车/结算，咨询通过邮件（`mailto:`，自动带商品名与商品番号）。后续如需电商功能可再扩展。

## 安全建议

- 上线后**第一时间修改 `admin_password`**，建议用 bcrypt 哈希。
- 后台 `admin/` 建议配合 HTTPS 使用；如需更强防护，可在服务器层为 `/admin/` 再加一层 Basic Auth 或 IP 白名单。
