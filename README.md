# 图片对决评分

本文档描述了项目的设计、部署与使用细节。系统使用 LAMP 环境，依赖原生 PHP、MySQL、HTML/CSS/JS，无第三方框架。

## 目录
1. 概述
2. 数据库结构
3. 后端逻辑 (api.php / thumb/php)
4. 前端结构 (index.php / style.css / script.js)
5. ELO 算法说明
6. IP 限流机制
7. 部署

---

## 1. 概述

用户在主页面看到两张随机选出的图片，点击其中一张完成投票。后台通过 ELO 等级算法调整两张图片的分数，并记录胜/参赛次数。侧边栏可查看排行榜。图片文件保存在磁盘，数据库仅存储相对路径。

## 2. 数据库结构

文件 `database.sql` 初始化两张表：

```sql
CREATE TABLE images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  path VARCHAR(255) NOT NULL,
  elo INT NOT NULL DEFAULT 1000,
  wins INT NOT NULL DEFAULT 0,
  plays INT NOT NULL DEFAULT 0
);

CREATE TABLE ip_votes (
  ip VARCHAR(45) PRIMARY KEY,
  last_vote TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

- **images**: 存储图片元信息和评分数据
- **ip_votes**: 存储最后投票时间用于限流

可手动向 `images` 插入图片路径，例如：

```sql
INSERT INTO images(path) VALUES('img/1.jpg'),('img/2.jpg');
```

## 3. 后端逻辑 (api.php / thumb/php)

`api.php` 根据 `action` 参数提供三种接口：

- `pair`：返回两条随机图片记录，用于前端展示
- `vote`：接收 POST 的 `winner` 和 `loser` ID，进行限流检查、ELO 计算与数据库更新
- `leaderboard`：返回按 ELO 降序的所有图片数据

所有数据库访问使用 PDO 并启用异常模式，避免 SQL 注入。

**限流**：`ip_votes` 表记录每个 IP 的最后一次投票时间，若 60 秒内再次投票则返回 429 错误。

**ELO 更新**：采用 20 的 K 值，默认初始分数 1000。胜者与败者的分数分别按照公式计算并写回，同时更新胜/参赛次数。

### 缩略图生成

`thumb.php`，接收 `src` 及 `w`/`h` 参数并返回等比例压缩后的图像。仅支持 `img/` 目录下文件，避免目录遍历。脚本在开始时提升了 PHP 内存限制，并在无法处理超大图片时回退到直接输出原图，以避免部分大文件无法显示的问题。

```php
// 示例请求: thumb.php?src=img/1.jpg&w=300
```

### 图片选择优先级

为了让参与次数较少的图片更快进入对决，`pair` 接口使用了如下 SQL：

```sql
SELECT id, path FROM images ORDER BY RAND()/(plays+1) DESC LIMIT 2;
```

这个公式会让 `plays` 少的行返回更高的排序权重，从而优先被选中。

## 4. 前端结构 (index.php / style.css / script.js)

### index.php

- 主页面包含侧边栏按钮和两个图像槽
- 使用 `<div>` + `<img>` 布局，外层 flexbox 自适应屏幕
- 模态组件用于放大查看图片（单击图片触发）

### style.css

- 深蓝背景 (`#0a0f2c`)，侧边栏更暗 (`#111539`)
- 霓虹绿 `#39ff14` 作为高亮色
- 图片入场淡入、悬停上浮和发光效果
- 使用 `hidden` 类控制显示/隐藏

### script.js

- 页面加载后调用 `api.php?action=pair` 获取两个随机图片
- 单击图片打开模态查看原始大图，点击图下方的“投票”按钮才会记录选择并切换下一组。按钮始终显示在图片上方，防止大型图片遮盖；在打开模态时，模态会覆盖按钮。
- 侧边栏始终显示排行榜缩略图，上方带有标题和刷新按钮，通过 `action=leaderboard` 获取数据并在页面加载时填充，点击缩略图可查看大图。

前后端通信均使用 Fetch API，未引入外部 JS 库。

为减少网络负荷和加快加载，前端投票和排行榜均使用由后端 `thumb.php` 动态生成的缩略图。原始大图仅在模态打开时加载。

## 5. ELO 算法说明

ELO 分数更新公式：

```php
$Ea = 1 / (1 + pow(10, ($Rb - $Ra) / 400));
$newA = $Ra + $k * (1 - $Ea);
```

其中 `Ra` 为当前选中图片分数，`Rb` 为对手分数，`k = 20`。同样公式应用于败者，只是期望值 `Ea` 取胜率，它将导致得分下降。

## 6. IP 限流机制

在 `vote` 操作中，接口先查询 `ip_votes` 表：

- 检索客户端 IP（支持 `X-Forwarded-For` 以兼容反向代理环境）。
- 若记录存在且距离上次投票在 0 到 60 秒之间，则返回 429 错误，并在前端显示提示。
  由于数据库和 PHP 时区可能不一致，时间差为负会被视为合法并会刷新 `last_vote` 时间。
- 否则更新或插入本次时间。此逻辑防止刷票攻击。

该机制通过 IP 地址简单实现，但在代理或 NAT 环境下可能不完全准确，可加入 cookie/会话校验。

## 7. 部署

1. 将所有文件上传到 PHP 支持的服务器根目录
2. 在 MySQL 中导入 `database.sql` 并添加图片路径
3. 将图片放置于 `img/` 目录或其他相对路径
4. 根据服务器环境调整 `api.php` 中的数据库连接参数
