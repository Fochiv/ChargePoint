<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve static files
if ($uri !== '/' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    return false;
}

// Route to PHP file
$phpFile = __DIR__ . $uri;
if (is_dir($phpFile)) {
    $phpFile = rtrim($phpFile, '/') . '/index.php';
}

if (file_exists($phpFile) && pathinfo($phpFile, PATHINFO_EXTENSION) === 'php') {
    require $phpFile;
    exit;
}

// Default fallback
if (file_exists(__DIR__ . $uri . '.php')) {
    require __DIR__ . $uri . '.php';
    exit;
}

// Not found
http_response_code(404);
echo '<!DOCTYPE html><html><head><title>404 — ChargePoint</title><link rel="stylesheet" href="/assets/css/style.css"></head><body>';
echo '<div style="text-align:center;padding:80px 20px;"><div style="font-size:4rem;">🔍</div><h1 style="margin:20px 0;">Page introuvable</h1><p style="color:#6b7280;">La page que vous recherchez n\'existe pas.</p><a href="/" style="display:inline-block;margin-top:20px;background:var(--primary);color:white;padding:12px 28px;border-radius:12px;font-weight:700;">Retour à l\'accueil</a></div>';
echo '</body></html>';
