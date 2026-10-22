<?php
// PHP 7.2+. Keep all provider credentials outside the web root.
ini_set('display_errors', '0');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=utf-8');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');
ini_set('session.cookie_samesite', 'Strict');
session_name('AIICUT_CREATOR');
session_start();
function reply($data, $code = 200) { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function fail($message, $code = 400) { reply(array('error' => $message), $code); }
$configPath = '/www/aiicut-private/config.php';
$config = is_file($configPath) ? require $configPath : array();
if (!is_array($config)) $config = array();
$key = isset($config['api_key']) ? trim($config['api_key']) : trim((string)getenv('ARK_API_KEY'));
$model = isset($config['model']) ? trim($config['model']) : (getenv('AIICUT_SEEDANCE_MODEL') ?: 'doubao-seedance-2-0-260128');
$accessCode = isset($config['access_code']) ? (string)$config['access_code'] : (string)getenv('AIICUT_CREATOR_CODE');
$dailyLimit = isset($config['daily_limit']) ? (int)$config['daily_limit'] : 20;
// Native Ark protocol only. Do not silently route keys to a third-party host.
$apiBase = 'https://ark.cn-beijing.volces.com/api/v3/contents/generations/tasks';
$ready = strlen($key) > 10 && strlen($accessCode) >= 12 && function_exists('curl_init') && $dailyLimit > 0;
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
if (!isset($_SESSION['tasks'])) $_SESSION['tasks'] = array();
$action = isset($_GET['action']) ? $_GET['action'] : 'status';
$authenticated = !empty($_SESSION['authorized_until']) && $_SESSION['authorized_until'] > time();
if ($action === 'status' && $_SERVER['REQUEST_METHOD'] === 'GET') reply(array('ready' => $ready, 'authenticated' => $authenticated, 'csrf' => $_SESSION['csrf'], 'provider' => '火山引擎方舟', 'model' => 'Seedance 2.0', 'message' => $ready ? '服务已配置' : 'AI 生成功能尚待配置，基础剪辑可正常使用。'));
if (!$ready) fail('AI 生成功能尚待管理员配置服务。可先使用基础剪辑工作台。', 503);
function rateLimit($name, $limit, $seconds) {
    $dir = sys_get_temp_dir() . '/aiicut-' . substr(hash('sha256', __DIR__), 0, 20);
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) fail('服务暂时不可用。', 503);
    $path = $dir . '/' . hash('sha256', $name) . '.json';
    $file = @fopen($path, 'c+');
    if (!$file || !flock($file, LOCK_EX)) fail('服务暂时不可用。', 503);
    $items = json_decode(stream_get_contents($file), true);
    if (!is_array($items)) $items = array();
    $now = time();
    $items = array_values(array_filter($items, function ($t) use ($now, $seconds) { return is_int($t) && $t > $now - $seconds; }));
    if (count($items) >= $limit) { flock($file, LOCK_UN); fclose($file); fail('请求次数已达限制，请稍后重试或联系管理员。', 429); }
    $items[] = $now; rewind($file); ftruncate($file, 0); fwrite($file, json_encode($items)); fflush($file); flock($file, LOCK_UN); fclose($file);
}
$body = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    $expected = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
    if ($origin !== '' && $origin !== $expected) fail('请求来源不匹配。', 403);
    $csrf = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
    if (!hash_equals($_SESSION['csrf'], $csrf)) fail('页面会话已失效，请刷新后重试。', 403);
    if (stripos(isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '', 'application/json') !== 0) fail('请求格式不正确。', 415);
    $raw = file_get_contents('php://input', false, null, 0, 20001);
    if (strlen($raw) > 20000) fail('输入内容过长。', 413);
    $body = json_decode($raw, true);
    if (!is_array($body)) fail('请求格式不正确。');
}
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('login:' . $_SERVER['REMOTE_ADDR'], 12, 900);
    if (!isset($body['code']) || !is_string($body['code']) || !hash_equals($accessCode, $body['code'])) fail('创作访问码不正确。', 401);
    session_regenerate_id(true); $_SESSION['authorized_until'] = time() + 3600 * 12;
    reply(array('authenticated' => true));
}
if (!$authenticated) fail('请先输入管理员提供的创作访问码。', 401);
function upstream($url, $payload = null) {
    global $key;
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 60, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $key, 'Content-Type: application/json')));
    if ($payload !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); }
    $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($raw === false || $code === 0) fail('连接生成服务超时。任务可能已提交，请勿立即重复提交，请管理员在方舟控制台核对。', 504);
    $data = json_decode($raw, true);
    if (!is_array($data)) fail('生成服务返回异常，请稍后查询或联系管理员。', 502);
    if ($code < 200 || $code >= 300) {
        $errorCode = isset($data['error']['code']) ? preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$data['error']['code']) : (string)$code;
        fail('生成服务未接受请求（' . substr($errorCode, 0, 90) . '），请检查服务权限、余额与内容要求。', 502);
    }
    return $data;
}
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($body['consent']) || empty($body['billing_consent'])) fail('请确认生成内容的服务处理和计费说明。');
    $prompt = isset($body['prompt']) && is_string($body['prompt']) ? trim($body['prompt']) : '';
    if (strlen($prompt) < 6 || strlen($prompt) > 12000) fail('请填写 2–4000 字以内的画面描述。');
    $duration = isset($body['duration']) ? $body['duration'] : 5;
    if (!is_int($duration) || $duration < 4 || $duration > 15) fail('视频时长须为 4–15 秒的整数。');
    $ratio = isset($body['ratio']) ? $body['ratio'] : '16:9';
    if (!in_array($ratio, array('16:9', '9:16', '1:1', '4:3', '3:4', '21:9'), true)) fail('画幅不支持。');
    $image = isset($body['image']) && is_string($body['image']) ? trim($body['image']) : '';
    $content = array(array('type' => 'text', 'text' => $prompt));
    if ($image !== '') {
        $parts = parse_url($image);
        if (!$parts || !isset($parts['scheme'], $parts['host']) || $parts['scheme'] !== 'https' || isset($parts['user']) || isset($parts['pass']) || strlen($image) > 2048 || filter_var($parts['host'], FILTER_VALIDATE_IP) || strpos($parts['host'], '.') === false || preg_match('/[\s\x00-\x1f]/', $image)) fail('参考图请填写可供生成服务读取的公开 HTTPS 地址。');
        $content[] = array('type' => 'image_url', 'image_url' => array('url' => $image), 'role' => 'first_frame');
    }
    $requestId = isset($body['request_id']) && is_string($body['request_id']) ? $body['request_id'] : '';
    if (!preg_match('/^[a-f0-9-]{20,64}$/', $requestId)) fail('请求标识无效，请刷新重试。');
    if (isset($_SESSION['requests'][$requestId])) reply($_SESSION['requests'][$requestId]);
    rateLimit('create-session:' . session_id(), 1, 60);
    rateLimit('create-global', $dailyLimit, 86400);
    // Reserve the request before submission; never repeat an ambiguous charged call.
    $_SESSION['requests'][$requestId] = array('uncertain' => true, 'message' => '该请求已提交或正在处理中。请勿重复创建，请在控制台核对。');
    if (count($_SESSION['requests']) > 100) array_shift($_SESSION['requests']);
    $data = upstream($apiBase, array('model' => $model, 'content' => $content, 'duration' => $duration, 'ratio' => $ratio, 'resolution' => '720p', 'generate_audio' => !empty($body['audio']), 'watermark' => true));
    if (empty($data['id']) || !is_string($data['id']) || !preg_match('/^[A-Za-z0-9_-]{5,180}$/', $data['id'])) fail('任务已提交但未取得有效编号，请在方舟控制台核对。', 502);
    $_SESSION['tasks'][$data['id']] = array('created' => time());
    $response = array('id' => $data['id'], 'status' => 'queued'); $_SESSION['requests'][$requestId] = $response; reply($response);
}
if ($action === 'task' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? $_GET['id'] : '';
    if (!is_string($id) || !isset($_SESSION['tasks'][$id])) fail('该任务不属于当前会话，或会话已过期。', 404);
    $saved = $_SESSION['tasks'][$id];
    if (isset($saved['cached'], $saved['checked']) && time() - $saved['checked'] < 8) reply($saved['cached']);
    $data = upstream($apiBase . '/' . rawurlencode($id));
    $status = isset($data['status']) ? $data['status'] : 'unknown';
    $result = array('id' => $id, 'status' => $status);
    if ($status === 'succeeded' && isset($data['content']['video_url'])) {
        $url = $data['content']['video_url'];
        if (!is_string($url) || stripos($url, 'https://') !== 0 || !filter_var($url, FILTER_VALIDATE_URL)) fail('生成结果地址异常，请在控制台核对。', 502);
        $result['video_url'] = $url;
    }
    if (isset($data['error']['code'])) $result['error'] = '生成未完成（' . substr(preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$data['error']['code']), 0, 90) . '）。请检查内容和服务要求。';
    $_SESSION['tasks'][$id]['cached'] = $result; $_SESSION['tasks'][$id]['checked'] = time(); reply($result);
}
fail('接口或请求方法不存在。', 404);
