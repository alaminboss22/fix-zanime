<?php
// =========================================================
//  ANIME PRINTING ENGINE V4.2 (MYANIMELIST INTEGRATION)
// =========================================================

// SECURITY: DOMAIN LOCKING (সাময়িকভাবে সব ডোমেন এলাও করা হয়েছে পরীক্ষার সুবিধার্থে)
header("Access-Control-Allow-Origin: *");
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');
header('X-Robots-Tag: noindex, nofollow');

define('JIKAN_BASE_URL', 'https://api.jikan.moe/v4');
define('CACHE_PATH', __DIR__ . '/cache/');

// CACHE SYSTEM
function getCached($key, $duration = 3600) {
    $file = CACHE_PATH . md5($key) . '.json';
    if (file_exists($file)) {
        if ((time() - filemtime($file)) < $duration) {
            return json_decode(file_get_contents($file), true);
        } else { @unlink($file); }
    }
    return null;
}

function saveCached($key, $data) {
    if (!is_dir(CACHE_PATH)) mkdir(CACHE_PATH, 0755, true);
    if (rand(1, 100) <= 5) { // Garbage collection: 5% chance
        $files = glob(CACHE_PATH . '*');
        $now = time();
        foreach ($files as $f) {
            if (is_file($f) && ($now - filemtime($f) >= 86400)) @unlink($f);
        }
    }
    file_put_contents(CACHE_PATH . md5($key) . '.json', json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// ROUTER
$action = strtolower(trim($_GET['action'] ?? ''));
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$query = trim($_GET['query'] ?? '');
$type = strtolower($_GET['type'] ?? 'movie');
$type = in_array($type, ['movie', 'tv'], true) ? $type : 'movie';

switch ($action) {
    case 'discover':
        serveDiscover($page, $type);
        break;
    case 'search':
        serveSearch($query);
        break;
    case 'details':
        serveDetails($id);
        break;
    default:
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid Action'
        ]);
        break;
}

// LOGIC FUNCTIONS
function serveDiscover($page, $type) {
    $cacheKey = "disc_{$type}_{$page}_" . http_build_query($_GET);
    $cached = getCached($cacheKey, 3600);
    if ($cached) { echo json_encode($cached); return; }

    $mal_type = ($type === 'movie') ? 'movie' : 'tv';
    $endpoint = "/top/anime";
    $params = [
        'page' => $page,
        'type' => $mal_type,
        'filter' => $_GET['filter'] ?? 'bypopularity'
    ];
    
    $data = fetchAPI($endpoint, $params);
    if ($data && !isset($data['status'])) {
        saveCached($cacheKey, $data);
    }
    echo json_encode($data);
}

function serveSearch($query) {
    if (empty($query)) { echo json_encode(['data' => []]); return; }
    
    $cacheKey = "search_" . urlencode($query);
    $cached = getCached($cacheKey, 1800);
    if ($cached) { echo json_encode($cached); return; }

    $endpoint = "/anime";
    $params = [
        'q' => $query,
        'limit' => 20,
        'sfw' => true
    ];
    
    $data = fetchAPI($endpoint, $params);
    if ($data && !isset($data['status'])) {
        saveCached($cacheKey, $data);
    }
    echo json_encode($data);
}

function serveDetails($id) {
    if (empty($id)) { echo json_encode(['status' => 'error']); return; }
    
    $cacheKey = "det_{$id}";
    $cached = getCached($cacheKey, 86400);
    if ($cached) { echo json_encode($cached); return; }

    $endpoint = "/anime/{$id}/full";
    $data = fetchAPI($endpoint, []);
    
    if ($data && !isset($data['status'])) {
        saveCached($cacheKey, $data);
    }
    echo json_encode($data);
}

// HELPER: CURL FETCH
function fetchAPI($endpoint, $params) {
    $url = JIKAN_BASE_URL . $endpoint;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AnimeEngine/4.2');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Auto-retry on Rate Limit (429) [1]
    if ($http_code === 429) {
        sleep(1); 
        $res = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    }

    if ($res === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return [
            'status' => 'error',
            'message' => $err
        ];
    }

    curl_close($ch);

    if ($http_code !== 200) {
        return [
            'status' => 'error',
            'http_code' => $http_code,
            'response' => json_decode($res, true)
        ];
    }
    return json_decode($res, true);
}
?>
