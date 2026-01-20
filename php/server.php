<?php
/**
 * PrivateAV Server-Side Integration Example
 *
 * Run with: php -S localhost:8080
 * Then open http://localhost:8080 in your browser
 */

session_start();

// Load environment variables from .env file
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// PrivateAV API configuration
define('API_URL', 'https://api.privateav.com/api/v1');
define('SECRET_KEY', $_ENV['PRIVATEAV_SECRET_KEY'] ?? '');

if (empty(SECRET_KEY)) {
    die('Error: PRIVATEAV_SECRET_KEY is required');
}

// Get current URL for return URL
function getBaseUrl(): string {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return "{$protocol}://{$host}";
}

// Make API request
function apiRequest(string $endpoint, array $data): array {
    $ch = curl_init(API_URL . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . SECRET_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $httpCode,
        'data' => json_decode($response, true),
    ];
}

// Router
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($path) {
    case '/':
        homePage();
        break;
    case '/verify':
        startVerification();
        break;
    case '/verified':
        handleCallback();
        break;
    default:
        http_response_code(404);
        echo 'Not found';
}

function homePage(): void {
    $verified = $_SESSION['verified'] ?? false;
    $statusClass = $verified ? 'verified' : 'unverified';
    $statusText = $verified ? 'Age verified successfully!' : 'Age verification required';

    echo <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
      <title>PrivateAV Example</title>
      <style>
        body { font-family: system-ui; max-width: 600px; margin: 50px auto; padding: 20px; }
        .btn { background: #0d9488; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0f766e; }
        .status { padding: 16px; border-radius: 6px; margin: 20px 0; }
        .verified { background: #d1fae5; color: #065f46; }
        .unverified { background: #fef3c7; color: #92400e; }
      </style>
    </head>
    <body>
      <h1>PrivateAV Integration Example</h1>
      <div class="status {$statusClass}">{$statusText}</div>
      <a href="/verify" class="btn">Verify Age</a>
    </body>
    </html>
    HTML;
}

function startVerification(): void {
    $returnUrl = getBaseUrl() . '/verified';

    $result = apiRequest('/sessions/create', [
        'returnUrl' => $returnUrl,
        'externalUserId' => 'example-user-123',
    ]);

    if ($result['status'] !== 201) {
        http_response_code($result['status']);
        echo 'Error creating session: ' . json_encode($result['data']);
        return;
    }

    header('Location: ' . $result['data']['verifyUrl']);
    exit;
}

function handleCallback(): void {
    $sessionId = $_GET['sessionId'] ?? null;
    $status = $_GET['status'] ?? null;

    if ($status === 'cancelled') {
        header('Location: /?cancelled=true');
        exit;
    }

    if (!$sessionId) {
        http_response_code(400);
        echo 'Missing sessionId';
        return;
    }

    $result = apiRequest('/sessions/validate', [
        'sessionId' => $sessionId,
    ]);

    if (($result['data']['accessGranted'] ?? false) === true) {
        $_SESSION['verified'] = true;
        header('Location: /');
        exit;
    }

    $resultStatus = $result['data']['status'] ?? 'unknown';
    echo <<<HTML
    <!DOCTYPE html>
    <html>
    <head><title>Verification Failed</title></head>
    <body>
      <h1>Verification Failed</h1>
      <p>Status: {$resultStatus}</p>
      <a href="/">Try again</a>
    </body>
    </html>
    HTML;
}
