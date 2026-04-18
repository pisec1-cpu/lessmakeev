<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Replace these placeholders on the VPS and keep this file outside the Astro static build.
$allowedOrigin = 'https://lesstrsnab.ru';
$apiKey = '4F1EZA3vx7Vy2xGROWTlUBBGeFT8SJeLe5g4';
$fromEmail = 'no-reply@lesstrsnab.ru';
$fromName = 'Лесстройснаб';
$toEmail = 'massma29@ya.ru';
$subject = 'Новая заявка с сайта lesstrsnab.ru';

if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Метод не поддерживается.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$name = trim(strip_tags((string) ($_POST['name'] ?? '')));
$phone = trim(strip_tags((string) ($_POST['phone'] ?? '')));
$product = trim(strip_tags((string) ($_POST['product'] ?? '')));

if ($name === '' || $phone === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Заполните имя и телефон.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($apiKey === '4F1EZA3vx7Vy2xGROWTlUBBGeFT8SJeLe5g4' || $toEmail === 'massma29@ya.ru') {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'На сервере не настроены параметры отправки.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safePhone = htmlspecialchars($phone, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeProduct = htmlspecialchars($product !== '' ? $product : 'Не указан', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$html = <<<HTML
<html>
  <head><meta charset="UTF-8"></head>
  <body>
    <h2>Новая заявка с сайта</h2>
    <p><strong>Имя:</strong> {$safeName}</p>
    <p><strong>Телефон:</strong> {$safePhone}</p>
    <p><strong>Товар:</strong> {$safeProduct}</p>
  </body>
</html>
HTML;

$text = "Новая заявка с сайта\n"
    . "Имя: {$name}\n"
    . "Телефон: {$phone}\n"
    . "Товар: " . ($product !== '' ? $product : 'Не указан');

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'https://api.smtp.bz/v1/smtp/send',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => [
        'authorization: ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS => http_build_query([
        'from' => $fromEmail,
        'name' => $fromName,
        'to' => $toEmail,
        'subject' => $subject,
        'html' => $html,
        'text' => $text,
    ]),
]);

$response = curl_exec($curl);
$error = curl_error($curl);
$httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($error !== '') {
    error_log('SMTP.BZ cURL error: ' . $error);
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка соединения с почтовым сервисом.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    error_log('SMTP.BZ API error ' . $httpCode . ': ' . (string) $response);
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка отправки заявки.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Заявка отправлена.',
], JSON_UNESCAPED_UNICODE);
