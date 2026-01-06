<?php
// 内置配置 - 支持外部配置文件
define('BASE_DIR', __DIR__);
define('IMAGES_DIR', dirname(__DIR__, 2) . '/images/');  // 图片目录
define('SITE_URL', 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']));
define('API_VERSION', '2.0');

// 加载 API 配置
$apiConfigFile = dirname(__DIR__, 2) . '/config/api-config.json';
$apiConfig = [
    'api_enabled' => true,
    'max_images_per_request' => 50,
    'default_image_count' => 1,
    'allow_external_mode' => true,
    'cors_enabled' => true,
];

if (file_exists($apiConfigFile)) {
    $loadedConfig = json_decode(file_get_contents($apiConfigFile), true);
    if (is_array($loadedConfig)) {
        $apiConfig = array_merge($apiConfig, $loadedConfig);
    }
}

// 检查 API 是否启用
if (!$apiConfig['api_enabled']) {
    header('Content-type: application/json; charset=utf-8');
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'API 服务已禁用',
        'code' => 'API_DISABLED'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

define('DEFAULT_IMAGE_COUNT', $apiConfig['default_image_count']);
define('MAX_IMAGES_PER_REQUEST', $apiConfig['max_images_per_request']);
define('CURRENT_IMAGE_MODE', 'random');

// 内置访问权限检查函数
function checkAccess() {
    // 简化的访问检查，可根据需要自定义
    return true;
}

// 检查访问权限
checkAccess();

header("Content-type: application/json; charset=utf-8");

// 根据配置设置 CORS 标头
if ($apiConfig['cors_enabled']) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Allow-Headers: Content-Type');
}

// 设备检测函数（参考api.php）
function isMobile(){
    $useragent=isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $useragent_commentsblock=preg_match('|\\(.*?\\)|',$useragent,$matches)>0?$matches[0]:'';
    function CheckSubstrs($substrs,$text){
        foreach($substrs as $substr)
        if(false!==strpos($text,$substr)){
            return true;
        }
        return false;
    }
    $mobile_os_list=array('Google Wireless Transcoder','Windows CE','WindowsCE','Symbian','Android','armv6l','armv5','Mobile','CentOS','mowser','AvantGo','Opera Mobi','J2ME/MIDP','Smartphone','Go.Web','Palm','iPAQ');
    $mobile_token_list=array('Profile/MIDP','Configuration/CLDC-','160×160','176×220','240×240','240×320','320×240','UP.Browser','UP.Link','SymbianOS','PalmOS','PocketPC','SonyEricsson','Nokia','BlackBerry','Vodafone','BenQ','Novarra-Vision','Iris','NetFront','HTC_','Xda_','SAMSUNG-SGH','Wapaka','DoCoMo','iPhone','iPod');
    $found_mobile=CheckSubstrs($mobile_os_list,$useragent_commentsblock) ||
    CheckSubstrs($mobile_token_list,$useragent);
    if ($found_mobile){
        return true;
    }else{
        return false;
    }
}

// 获取参数
$count = isset($_GET['count']) ? intval($_GET['count']) : DEFAULT_IMAGE_COUNT;
$format = isset($_GET['format']) ? $_GET['format'] : 'json';
$type = isset($_GET['type']) ? $_GET['type'] : '';
$returnType = isset($_GET['return']) ? $_GET['return'] : 'json'; 
$external = isset($_GET['external']) ? $_GET['external'] === 'true' || $_GET['external'] === '1' : false; 

// 如果没有指定type参数，则自动检测设备类型
if (empty($type)) {
    $type = isMobile() ? 'pe' : 'pc';
}

// 参数验证
$count = max(1, min(MAX_IMAGES_PER_REQUEST, $count));

// 内置图片获取函数
function getImages($type, $count, $external = false) {
    global $apiConfig;
    
    // 检查是否许可外链模式
    if ($external && !$apiConfig['allow_external_mode']) {
        return [
            'success' => false,
            'message' => '外链模式已禁用',
            'images' => [],
            'total_available' => 0
        ];
    }
    
    // 外链模式
    if ($external) {
        return getExternalImages($type, $count);
    }
    
    // 获取所有图片文件
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
    $images = [];
    
    // 从 images 目录获取图片
    $imagesBaseDir = dirname(__DIR__, 2) . '/images/' . $type;
    if (is_dir($imagesBaseDir)) {
        // 扫描目录下的所有文件
        $files = scandir($imagesBaseDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $filePath = $imagesBaseDir . '/' . $file;
            // 如果是文件，直接添加
            if (is_file($filePath)) {
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($extension, $allowedExtensions)) {
                    $images[] = [
                        'filename' => $file,
                        'path' => $filePath,
                        'url' => SITE_URL . '/images/' . $type . '/' . $file,
                        'extension' => $extension,
                        'type' => $type,
                        'size' => filesize($filePath),
                        'source' => 'images'
                    ];
                }
            } 
            // 兼容旧的格式子目录结构
            elseif (is_dir($filePath)) {
                $subFiles = scandir($filePath);
                foreach ($subFiles as $subFile) {
                    if ($subFile === '.' || $subFile === '..') continue;
                    $subPath = $filePath . '/' . $subFile;
                    if (is_file($subPath)) {
                        $extension = strtolower(pathinfo($subFile, PATHINFO_EXTENSION));
                        if (in_array($extension, $allowedExtensions)) {
                            $images[] = [
                                'filename' => $subFile,
                                'path' => $subPath,
                                'url' => SITE_URL . '/images/' . $type . '/' . $file . '/' . $subFile,
                                'extension' => $extension,
                                'type' => $type,
                                'size' => filesize($subPath),
                                'source' => 'images'
                            ];
                        }
                    }
                }
            }
        }
    }
    
    if (empty($images)) {
        $message = '没有找到图片，请检查 images 目录';
        return [
            'success' => false,
            'message' => $message,
            'images' => [],
            'total_available' => 0
        ];
    }
    
    // 随机选择图片
    shuffle($images);
    $selectedImages = array_slice($images, 0, $count);
    
    return [
        'success' => true,
        'images' => $selectedImages,
        'total_available' => count($images)
    ];
}

// 外链模式图片获取函数（从数据库读取）
function getExternalImages($type, $count) {
    try {
        require_once dirname(__DIR__, 2) . '/includes/Database.php';
        $db = Database::getInstance();
        
        $limit = intval($count * 10);
        // 查询外链图片
        $sql = "SELECT id, filename, url, device_type, format, width, height, file_size, tags, description, upload_time 
                FROM images 
                WHERE storage_type = 'external' AND device_type = :type
                ORDER BY RAND()
                LIMIT {$limit}";
        
        $result = $db->fetchAll($sql, ['type' => $type]);
        
        if (empty($result)) {
            return [
                'success' => false,
                'message' => '数据库中没有找到外链图片，请先添加外链图片',
                'images' => [],
                'total_available' => 0
            ];
        }
        
        $images = [];
        foreach ($result as $row) {
            $images[] = [
                'id' => $row['id'],
                'filename' => $row['filename'],
                'url' => $row['url'],
                'extension' => strtolower(pathinfo($row['url'], PATHINFO_EXTENSION)),
                'type' => $row['device_type'],
                'format' => $row['format'],
                'width' => $row['width'],
                'height' => $row['height'],
                'size' => $row['file_size'],
                'tags' => $row['tags'],
                'description' => $row['description'],
                'upload_time' => $row['upload_time'],
                'external' => true,
                'storage_type' => 'external'
            ];
        }
        
        // 随机选择图片
        shuffle($images);
        $selectedImages = array_slice($images, 0, $count);
        
        return [
            'success' => true,
            'images' => $selectedImages,
            'total_available' => count($images)
        ];
        
    } catch (Exception $e) {
        error_log('获取外链图片失败: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => '数据库查询失败: ' . $e->getMessage(),
            'images' => [],
            'total_available' => 0
        ];
    }
}

// 使用内置函数获取图片
$result = getImages($type, $count, $external);

if (!$result['success']) {
    $response = [
        'success' => false,
        'message' => $result['message'],
        'count' => 0,
        'images' => []
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$selectedImages = $result['images'];
$totalImages = $result['total_available'];

// 如果只要一张图片且返回类型是重定向，直接重定向
if ($count == 1 && $returnType === 'redirect') {
    header('Location: ' . $selectedImages[0]['url'], true, 302);
    exit;
}

// 统一模式标记
foreach ($selectedImages as &$image) {
    $image['converted'] = false;
    if ($external) {
        $image['external_mode'] = true;
    }
}
unset($image);

// 根据格式返回数据
if ($format === 'text' || $format === 'url') {
    header('Content-Type: text/plain; charset=utf-8');
    foreach ($selectedImages as $image) {
        echo $image['url'] . "\n";
    }
} else {
    // JSON格式 (默认)
    $response = [
        'success' => true,
        'count' => count($selectedImages),
        'type' => $type,
        'mode' => CURRENT_IMAGE_MODE,
        'total_available' => $totalImages,
        'timestamp' => time(),
        'api_version' => API_VERSION,
        'return_type' => $returnType,
        'external_mode' => $external,
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
        'images' => $selectedImages
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>
