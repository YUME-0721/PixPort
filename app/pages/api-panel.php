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
            background: url('/public/assets/images/home-backend.jpg') no-repeat center center fixed;
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
            padding: 20px 30px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }
        .header h1 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
            color: white;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            margin: 0;
        }
        .header .logo-img {
            height: 48px;
            width: auto;
        }
        .logout-btn {
            padding: 10px 20px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .logout-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        .tabs {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            gap: 10px;
            position: relative;
            z-index: 1;
        }
        .tab-btn {
            flex: 1;
            padding: 12px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .tab-btn.active {
            background: rgba(255, 255, 255, 0.4);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .tab-btn:hover:not(.active) {
            background: rgba(255, 255, 255, 0.3);
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
        <a href="/upload.php" style="text-decoration: none;">
            <h1 style="cursor: pointer;">
                <img src="/public/assets/images/logo-white.png" alt="PixPort" class="logo-img">
                <span>- 后台管理</span>
            </h1>
        </a>
        <div style="display: flex; gap: 10px;">
            <a href="/upload.php" style="padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.3s;" onmouseover="this.style.background='#5568d3'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#667eea'; this.style.transform='translateY(0)'">🏠 返回主页</a>
            <a href="?logout=1" class="logout-btn">🚪 退出登录</a>
        </div>
    </div>

    <div class="tabs">
        <a href="/panel.php?tab=system" class="tab-btn">📊 系统监控</a>
        <a href="/panel.php?tab=database" class="tab-btn">🗄️ 数据库监控</a>
        <a href="/file.php" class="tab-btn">🖼️ 图片管理</a>
        <a href="/gallery.php" class="tab-btn">🎨 图片画廊</a>
        <a href="/external-manager.php" class="tab-btn">🔗 外链管理</a>
        <a href="/api-panel.php" class="tab-btn active">🔧 API管理</a>
    </div>

    <div class="container">
        <!-- 消息提示 -->
        <?php if ($message): ?>
            <div class="alert"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- API 状态面板 -->
        <section>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0; flex: 1; border: none;">🔧 API 状态面板</h2>
                <div style="font-size: 28px; font-weight: bold;"><?php echo $apiStatus; ?></div>
            </div>
            <div class="grid">
                <div class="stat-card">
                    <div class="stat-label">API 版本</div>
                    <div class="stat-value">2.0</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">最大请求数</div>
                    <div class="stat-value"><?php echo $config['max_images_per_request']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">缓存状态</div>
                    <div class="stat-value"><?php echo $config['cache_enabled'] ? '✅ 启用' : '❌ 禁用'; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">速率限制</div>
                    <div class="stat-value"><?php echo $config['rate_limit_enabled'] ? '✅ 启用' : '❌ 禁用'; ?></div>
                </div>
            </div>
        </section>

        <!-- API 配置表单 -->
        <section>
            <h2>⚙️ API 配置</h2>
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

        <!-- 请求参数说明 -->
        <section>
            <h2>📝 请求参数说明</h2>
            <div class="grid">
                <!-- count -->
                <div class="grid-item" style="border-left: 4px solid #667eea;">
                    <h4 style="color: #667eea;">count - 返回图片数量</h4>
                    <div style="color: rgba(255, 255, 255, 0.8); margin-bottom: 10px;">
                        <strong>类型:</strong> 整数 | <strong>范围:</strong> 1-<?php echo $config['max_images_per_request']; ?> | <strong>默认:</strong> <?php echo $config['default_image_count']; ?>
                    </div>
                    <div style="color: rgba(255, 255, 255, 0.7); margin-bottom: 10px;">
                        <strong>示例:</strong> <code style="background: rgba(0, 0, 0, 0.3); padding: 2px 4px; border-radius: 3px;">/image_api.php?count=5</code>
                    </div>
                    <div style="color: rgba(255, 255, 255, 0.6); margin-bottom: 5px;">指定一次请求返回多少张随机图片</div>
                </div>

                <!-- type -->
                <div class="grid-item" style="border-left: 4px solid #28a745;">
                    <h4 style="color: #28a745;">type - 设备类型</h4>
                    <div style="color: rgba(255, 255, 255, 0.8); margin-bottom: 10px;">
                        <strong>类型:</strong> 字符串 | <strong>可选值:</strong> pc / pe / auto
                    </div>
                    <div style="color: rgba(255, 255, 255, 0.7); margin-bottom: 10px;">
                        <strong>示例:</strong> <code style="background: rgba(0, 0, 0, 0.3); padding: 2px 4px; border-radius: 3px;">/image_api.php?type=pc</code>
                    </div>
                    <div style="color: rgba(255, 255, 255, 0.6); margin-bottom: 5px;">
                        • <strong>pc</strong>: 桌面端图片<br>
                        • <strong>pe</strong>: 移动端图片<br>
                        • <strong>auto</strong>: 自动检测 (默认)
                    </div>
                </div>

                <!-- format -->
                <div class="grid-item" style="border-left: 4px solid #ffc107;">
                    <h4 style="color: #ffc107;">format - 响应格式</h4>
                    <div style="color: rgba(255, 255, 255, 0.8); margin-bottom: 10px;">
                        <strong>类型:</strong> 字符串 | <strong>可选值:</strong> json / text / url | <strong>默认:</strong> json
                    </div>
                    <div style="color: rgba(255, 255, 255, 0.7); margin-bottom: 10px;">
                        <strong>示例:</strong> <code style="background: rgba(0, 0, 0, 0.3); padding: 2px 4px; border-radius: 3px;">/image_api.php?format=text</code>
                    </div>
                    <div style="color: rgba(255, 255, 255, 0.6); margin-bottom: 5px;">
                        • <strong>json</strong>: 返回完整 JSON 对象<br>
                        • <strong>text</strong>: 每行一个 URL<br>
                        • <strong>url</strong>: 同 text
                    </div>
                </div>

                <!-- return -->
                <div class="grid-item" style="border-left: 4px solid #dc3545;">
                    <h4 style="color: #dc3545;">return - 返回类型</h4>
                    <div style="color: rgba(255, 255, 255, 0.8); margin-bottom: 10px;">
                        <strong>类型:</strong> 字符串 | <strong>可选值:</strong> json / redirect | <strong>默认:</strong> json
                    </div>
                    <div style="color: rgba(255, 255, 255, 0.7); margin-bottom: 10px;">
                        <strong>示例:</strong> <code style="background: rgba(0, 0, 0, 0.3); padding: 2px 4px; border-radius: 3px;">/image_api.php?return=redirect</code>
                    </div>
                    <div style="color: rgba(255, 255, 255, 0.6); margin-bottom: 5px;">
                        • <strong>json</strong>: 返回 JSON 数据<br>
                        • <strong>redirect</strong>: 直接重定向到图片 (需 count=1)
                    </div>
                </div>

                <!-- external -->
                <div class="grid-item" style="border-left: 4px solid #17a2b8;">
                    <h4 style="color: #17a2b8;">external - 外链模式</h4>
                    <div style="color: rgba(255, 255, 255, 0.8); margin-bottom: 10px;">
                        <strong>类型:</strong> 布尔值 | <strong>可选值:</strong> true / false / 1 / 0 | <strong>默认:</strong> false
                    </div>
                    <div style="color: rgba(255, 255, 255, 0.7); margin-bottom: 10px;">
                        <strong>示例:</strong> <code style="background: rgba(0, 0, 0, 0.3); padding: 2px 4px; border-radius: 3px;">/image_api.php?external=true</code>
                    </div>
                    <div style="color: rgba(255, 255, 255, 0.6); margin-bottom: 5px;">
                        启用时从数据库中获取外链图片 (需启用外链模式功能)
                    </div>
                </div>
            </div>
        </section>

        <!-- 使用示例 -->
        <section>
            <h2>💡 使用示例</h2>
            <div class="grid">
                <div class="grid-item">
                    <h4>基础调用</h4>
                    <div class="code-block"># 获取 1 张自动格式的图片
GET /image_api.php

# 获取 5 张桌面端图片
GET /image_api.php?count=5&type=pc

# 获取 3 张移动端图片，文本格式
GET /image_api.php?count=3&type=pe&format=text</div>
                </div>

                <div class="grid-item">
                    <h4>JavaScript 调用</h4>
                    <div class="code-block">// 获取随机图片
fetch('/image_api.php?count=5')
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      data.images.forEach(img => {
        console.log(img.url);
      });
    }
  });

// 直接显示图片
const img = new Image();
img.src = '/image_api.php?return=redirect';
document.body.appendChild(img);</div>
                </div>

                <div class="grid-item">
                    <h4>HTML 直接使用</h4>
                    <div class="code-block">&lt;!-- 直接显示图片 --&gt;
&lt;img src="/image_api.php?return=redirect" alt="Random"&gt;

&lt;!-- 背景图片 --&gt;
&lt;div style="background-image: url('/image_api.php?return=redirect&type=pc')"&gt;
&lt;/div&gt;

&lt;!-- 外链模式 --&gt;
&lt;img src="/image_api.php?external=true&return=redirect"&gt;</div>
                </div>

                <div class="grid-item">
                    <h4>外链模式</h4>
                    <div class="code-block"># 启用外链模式
GET /image_api.php?external=true

# 外链模式获取 5 张移动端图片
GET /image_api.php?external=true&type=pe&count=5

# 外链模式直接重定向
GET /image_api.php?external=1&return=redirect</div>
                </div>
            </div>
        </section>

        <!-- 快速测试 -->
        <section>
            <h2>🚀 快速测试</h2>
            <div class="grid">
                <a href="/image_api.php" target="_blank" style="display: flex; align-items: center; justify-content: center; padding: 30px; background: rgba(102, 126, 234, 0.2); border: 1px solid rgba(102, 126, 234, 0.5); border-radius: 8px; color: #667eea; text-decoration: none; font-weight: 600; transition: all 0.3s;" onmouseover="this.style.background='rgba(102, 126, 234, 0.3)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='rgba(102, 126, 234, 0.2)'; this.style.transform='translateY(0)'">
                    📊 获取 1 张 (JSON)
                </a>
                <a href="/image_api.php?count=5&format=text" target="_blank" style="display: flex; align-items: center; justify-content: center; padding: 30px; background: rgba(40, 167, 69, 0.2); border: 1px solid rgba(40, 167, 69, 0.5); border-radius: 8px; color: #28a745; text-decoration: none; font-weight: 600; transition: all 0.3s;" onmouseover="this.style.background='rgba(40, 167, 69, 0.3)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='rgba(40, 167, 69, 0.2)'; this.style.transform='translateY(0)'">
                    📝 获取 5 张 (文本)
                </a>
                <a href="/image_api.php?return=redirect" target="_blank" style="display: flex; align-items: center; justify-content: center; padding: 30px; background: rgba(255, 193, 7, 0.2); border: 1px solid rgba(255, 193, 7, 0.5); border-radius: 8px; color: #ffc107; text-decoration: none; font-weight: 600; transition: all 0.3s;" onmouseover="this.style.background='rgba(255, 193, 7, 0.3)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='rgba(255, 193, 7, 0.2)'; this.style.transform='translateY(0)'">
                    🖼️ 直接显示图片
                </a>
                <a href="/image_api.php?external=true&count=3" target="_blank" style="display: flex; align-items: center; justify-content: center; padding: 30px; background: rgba(23, 162, 184, 0.2); border: 1px solid rgba(23, 162, 184, 0.5); border-radius: 8px; color: #17a2b8; text-decoration: none; font-weight: 600; transition: all 0.3s;" onmouseover="this.style.background='rgba(23, 162, 184, 0.3)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='rgba(23, 162, 184, 0.2)'; this.style.transform='translateY(0)'">
                    🔗 外链模式 (3 张)
                </a>
            </div>
        </section>
    </div>
</body>
</html>
