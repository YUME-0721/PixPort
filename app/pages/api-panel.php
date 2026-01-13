<?php
// API 配置和控制中心
session_start();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: /index.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /index.php');
    exit;
}

// 获取 API 配置文件路径
$apiConfigFile = dirname(__DIR__, 2) . '/config/api-config.json';
if (!is_dir(dirname($apiConfigFile))) {
    mkdir(dirname($apiConfigFile), 0755, true);
}

// 加载系统配置
$systemConfigFile = dirname(__DIR__, 2) . '/config/system-config.json';
$systemConfig = [
    'background_url' => '/public/assets/images/home-backend.jpg'
];
if (file_exists($systemConfigFile)) {
    $loadedConfig = json_decode(file_get_contents($systemConfigFile), true);
    if (is_array($loadedConfig)) {
        $systemConfig = array_merge($systemConfig, $loadedConfig);
    }
}
$currentBg = $systemConfig['background_url'];

// 默认 API 配置
$defaultConfig = [
    'api_enabled' => true,
    'max_images_per_request' => 50,
    'default_image_count' => 1,
    'allowed_formats' => ['json', 'text', 'url'],
    'allowed_types' => ['pc', 'pe'],
    'allowed_return_types' => ['json', 'redirect'],
    'allow_external_mode' => true,
    'rate_limit_enabled' => false,
    'rate_limit_requests' => 100,
    'rate_limit_window' => 3600,
    'cache_enabled' => true,
    'cache_ttl' => 3600,
    'cors_enabled' => true,
    'cors_origins' => ['*'],
];

// 读取配置
$config = $defaultConfig;
if (file_exists($apiConfigFile)) {
    $loadedConfig = json_decode(file_get_contents($apiConfigFile), true);
    $config = array_merge($config, $loadedConfig);
}

// 处理配置更新
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_config') {
        $config['api_enabled'] = isset($_POST['api_enabled']) ? true : false;
        $config['max_images_per_request'] = intval($_POST['max_images_per_request']) ?: 50;
        $config['default_image_count'] = intval($_POST['default_image_count']) ?: 1;
        $config['allow_external_mode'] = isset($_POST['allow_external_mode']) ? true : false;
        $config['rate_limit_enabled'] = isset($_POST['rate_limit_enabled']) ? true : false;
        $config['rate_limit_requests'] = intval($_POST['rate_limit_requests']) ?: 100;
        $config['rate_limit_window'] = intval($_POST['rate_limit_window']) ?: 3600;
        $config['cache_enabled'] = isset($_POST['cache_enabled']) ? true : false;
        $config['cache_ttl'] = intval($_POST['cache_ttl']) ?: 3600;
        $config['cors_enabled'] = isset($_POST['cors_enabled']) ? true : false;
        
        file_put_contents($apiConfigFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $message = '✅ 配置已保存';
    }
}

// 获取 API 状态
$apiStatus = $config['api_enabled'] ? '✅ 已启用' : '❌ 已禁用';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/public/assets/svg/favicon.svg">
    <title>API管理 - PixPort</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: url('<?php echo $currentBg; ?>') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            padding: 20px;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.2);
            z-index: 0;
        }
        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 12px;
            padding: 12px 30px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 10px;
            z-index: 1000;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .header h1 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            color: white;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            margin: 0;
            white-space: nowrap;
        }
        .header .logo-img {
            height: 36px;
            width: auto;
        }
        
        /* 顶部导航菜单 */
        .nav-menu {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 14px;
            white-space: nowrap;
        }
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateY(-2px);
        }
        .nav-item.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }
        .nav-item .btn-icon {
            font-size: 16px;
        }
        
        /* 悬浮退出按钮 */
        .floating-logout {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 54px;
            height: 54px;
            background: rgba(220, 53, 69, 0.2);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            z-index: 9999;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        .floating-logout:hover {
            background: rgba(220, 53, 69, 0.5);
            transform: scale(1.1) rotate(-10deg);
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow: 0 12px 40px rgba(220, 53, 69, 0.4);
        }
        .floating-logout svg {
            width: 26px;
            height: 26px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 1;
        }
        section {
            margin-bottom: 30px;
        }
        h2 {
            color: white;
            margin-bottom: 20px;
            font-size: 20px;
            border-bottom: 2px solid rgba(102, 126, 234, 0.3);
            padding-bottom: 10px;
        }
        h3 {
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 15px;
            font-size: 16px;
        }
        h4 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        label {
            color: white;
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        input[type="text"],
        input[type="number"],
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            background: rgba(0, 0, 0, 0.3);
            color: white;
            font-size: 14px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
        }
        input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        input[type="text"]::placeholder,
        input[type="number"]::placeholder,
        textarea::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        button {
            padding: 12px 40px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }
        button:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        .alert {
            padding: 15px;
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.5);
            border-radius: 8px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            display: inline-block;
            margin-right: 15px;
            margin-bottom: 15px;
        }
        .stat-label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
            margin-bottom: 5px;
        }
        .stat-value {
            color: white;
            font-size: 20px;
            font-weight: bold;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .grid-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 8px;
        }
        .code-block {
            background: rgba(0, 0, 0, 0.3);
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            color: #90ee90;
            font-size: 13px;
            white-space: pre-wrap;
            overflow-x: auto;
            margin-bottom: 15px;
        }
        .param-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <a href="/upload.php" style="text-decoration: none;">
                <h1 style="cursor: pointer;">
                    <img src="/public/assets/images/logo-white.png" alt="PixPort" class="logo-img">
                    <span>- API管理</span>
                </h1>
            </a>
        </div>
        
        <nav class="nav-menu">
            <a href="/upload.php" class="nav-item">
                <span class="btn-icon">📤</span>
                <span class="btn-text">上传</span>
            </a>
            <a href="/gallery.php" class="nav-item">
                <span class="btn-icon">🎨</span>
                <span class="btn-text">画廊</span>
            </a>
            <a href="/panel.php" class="nav-item">
                <span class="btn-icon">📊</span>
                <span class="btn-text">监控</span>
            </a>
            <div class="nav-item active">
                <span class="btn-icon">🔧</span>
                <span class="btn-text">API</span>
            </div>
            <a href="/system-panel.php" class="nav-item">
                <span class="btn-icon">⚙️</span>
                <span class="btn-text">设置</span>
            </a>
            <a href="?logout=1" class="nav-item" style="background: rgba(220, 53, 69, 0.15); border-color: rgba(220, 53, 69, 0.2); color: #ffb3b3;">
                <span class="btn-icon">🚪</span>
                <span class="btn-text">退出</span>
            </a>
        </nav>
    </div>

    <div class="container">
        <!-- 消息提示 -->
        <?php if ($message): ?>
            <div class="alert"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- API 配置表单 -->
        <section>
            <h2>⚙️ 随机图API 配置</h2>
            <form method="POST" style="background: rgba(255, 255, 255, 0.05); padding: 30px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.1);">
                <input type="hidden" name="action" value="update_config">
                
                <h3 style="margin-bottom: 20px;">✅ 基础配置</h3>
                
                <div class="grid">
                    <!-- API 启用开关 -->
                    <div class="grid-item">
                        <div class="param-info">
                            <input type="checkbox" id="api_enabled" name="api_enabled" value="1" <?php echo $config['api_enabled'] ? 'checked' : ''; ?>>
                            <label for="api_enabled" style="margin: 0;">启用 API 服务</label>
                        </div>
                        <div style="color: rgba(255, 255, 255, 0.6); font-size: 12px;">启用或禁用整个 API 服务</div>
                    </div>

                    <!-- 最大请求数 -->
                    <div class="grid-item">
                        <label>最大请求数 (1-100)</label>
                        <input type="number" name="max_images_per_request" value="<?php echo $config['max_images_per_request']; ?>" min="1" max="100">
                        <div style="color: rgba(255, 255, 255, 0.6); font-size: 12px; margin-top: 5px;">单次请求最多返回的图片数</div>
                    </div>

                    <!-- 默认返回数 -->
                    <div class="grid-item">
                        <label>默认返回数 (1-50)</label>
                        <input type="number" name="default_image_count" value="<?php echo $config['default_image_count']; ?>" min="1" max="50">
                        <div style="color: rgba(255, 255, 255, 0.6); font-size: 12px; margin-top: 5px;">未指定 count 参数时的默认值</div>
                    </div>
                </div>

                <h3 style="margin-top: 30px; margin-bottom: 20px; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 20px;">🚀 高级功能</h3>
                
                <div class="grid">
                    <!-- 外链模式 -->
                    <div class="grid-item">
                        <div class="param-info">
                            <input type="checkbox" id="allow_external_mode" name="allow_external_mode" value="1" <?php echo $config['allow_external_mode'] ? 'checked' : ''; ?>>
                            <label for="allow_external_mode" style="margin: 0;">启用外链模式</label>
                        </div>
                        <div style="color: rgba(255, 255, 255, 0.6); font-size: 12px;">允许从数据库获取外链图片</div>
                    </div>

                    <!-- CORS -->
                    <div class="grid-item">
                        <div class="param-info">
                            <input type="checkbox" id="cors_enabled" name="cors_enabled" value="1" <?php echo $config['cors_enabled'] ? 'checked' : ''; ?>>
                            <label for="cors_enabled" style="margin: 0;">启用 CORS</label>
                        </div>
                        <div style="color: rgba(255, 255, 255, 0.6); font-size: 12px;">允许跨域请求</div>
                    </div>

                    <!-- 缓存 -->
                    <div class="grid-item">
                        <div class="param-info">
                            <input type="checkbox" id="cache_enabled" name="cache_enabled" value="1" <?php echo $config['cache_enabled'] ? 'checked' : ''; ?>>
                            <label for="cache_enabled" style="margin: 0;">启用缓存</label>
                        </div>
                        <div style="color: rgba(255, 255, 255, 0.6); font-size: 12px;">缓存图片列表提升性能</div>
                    </div>

                    <!-- 缓存时间 -->
                    <div class="grid-item">
                        <label>缓存时间 (秒)</label>
                        <input type="number" name="cache_ttl" value="<?php echo $config['cache_ttl']; ?>" min="60" step="60">
                        <div style="color: rgba(255, 255, 255, 0.6); font-size: 12px; margin-top: 5px;">缓存数据保存时长</div>
                    </div>
                </div>

                <h3 style="margin-top: 30px; margin-bottom: 20px; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 20px;">🛡️ 速率限制</h3>
                
                <div class="grid">
                    <!-- 速率限制开关 -->
                    <div class="grid-item">
                        <div class="param-info">
                            <input type="checkbox" id="rate_limit_enabled" name="rate_limit_enabled" value="1" <?php echo $config['rate_limit_enabled'] ? 'checked' : ''; ?>>
                            <label for="rate_limit_enabled" style="margin: 0;">启用速率限制</label>
                        </div>
                        <div style="color: rgba(255, 255, 255, 0.6); font-size: 12px;">防止 API 滥用</div>
                    </div>

                    <!-- 限制请求数 -->
                    <div class="grid-item">
                        <label>时间窗口内最多请求数</label>
                        <input type="number" name="rate_limit_requests" value="<?php echo $config['rate_limit_requests']; ?>" min="10" step="10">
                        <div style="color: rgba(255, 255, 255, 0.6); font-size: 12px; margin-top: 5px;">每个时间窗口允许的最大请求数</div>
                    </div>

                    <!-- 时间窗口 -->
                    <div class="grid-item">
                        <label>时间窗口 (秒)</label>
                        <input type="number" name="rate_limit_window" value="<?php echo $config['rate_limit_window']; ?>" min="60" step="60">
                        <div style="color: rgba(255, 255, 255, 0.6); font-size: 12px; margin-top: 5px;">速率限制的时间周期</div>
                    </div>
                </div>

                <!-- 保存按钮 -->
                <div style="margin-top: 30px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="submit">💾 保存配置</button>
                </div>
            </form>
        </section>

    </div>

    </script>
</body>
</html>
