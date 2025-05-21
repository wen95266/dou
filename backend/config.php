<?php
// backend/config.php

// !! 重要: 请将这些值替换为你的实际数据库凭证 !!
// 建议: 不要直接将此文件提交到公共仓库。
// 可以创建一个 config.php.example 并将 config.php 加入 .gitignore，
// 然后在服务器上手动创建 config.php。
// 或者使用环境变量。

define('DB_HOST', 'your_serv00_mysql_host'); // 通常是 'localhost' 或 Serv00 提供的特定主机名
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'your_db_name');

// Cloudflare Pages 前端域名 (用于CORS)
// !! 重要: 替换为你的 Cloudflare Pages 域名 (例如: https://yourproject.pages.dev)
// 如果你还在本地开发前端并从 localhost 访问，可以临时用 http://localhost:port
define('ALLOWED_ORIGIN', 'https://YOUR_CLOUDFLARE_PAGES_DOMAIN.pages.dev');
// 对于本地开发，如果你的前端运行在比如 http://localhost:5500 (VSCode Live Server 默认)
// define('ALLOWED_ORIGIN', 'http://localhost:5500');
// 或者在开发时使用 '*' (不安全，仅供测试)
// define('ALLOWED_ORIGIN', '*');


// 游戏设置
define('MAX_PLAYERS', 3);
define('INITIAL_CARDS_COUNT', 17);
define('LANDLORD_EXTRA_CARDS', 3);

// 简单的 Session 或 游戏状态存储目录 (如果不用数据库存储活跃游戏)
//确保这个目录是可写的，并且在你的 web根目录之外（如果可能）以增强安全性
//对于Serv00，你可能需要用类似 __DIR__ . '/../game_data' 这样的相对路径，并确保权限正确
define('GAME_SESSIONS_DIR', __DIR__ . '/game_sessions');

if (!is_dir(GAME_SESSIONS_DIR)) {
    mkdir(GAME_SESSIONS_DIR, 0755, true); //尝试创建，确保权限安全
}

?>
