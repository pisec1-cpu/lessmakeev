<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'Method not allowed.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$apiKey = getenv('SMTPBZ_API_KEY') ?: 'lDycyXP8A3WWInrAMcpj16Y6ZJp5RuB3XZ6l';
$fromEmail = getenv('ORDER_FROM_EMAIL') ?: 'no-reply@lesstrsnab.ru';
$toEmail = getenv('ORDER_TO_EMAIL') ?: 'massma29@ya.ru';
$fromName = getenv('ORDER_FROM_NAME') ?: 'Лесстройснаб';
$subject = getenv('ORDER_EMAIL_SUBJECT') ?: 'Новая заявка с сайта lesstrsnab.ru';
$replyTo = getenv('ORDER_REPLY_TO') ?: $fromEmail;

if ($apiKey === '' || $fromEmail === '' || $toEmail === '') {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'На сервере не настроены почтовые параметры.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody ?: '', true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Некорректный формат данных.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$name = trim((string) ($payload['name'] ?? ''));
$phone = trim((string) ($payload['phone'] ?? ''));
$product = trim((string) ($payload['product'] ?? ''));

if ($name === '' || $phone === '') {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'error' => 'Имя и телефон обязательны.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safePhone = htmlspecialchars($phone, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeProduct = htmlspecialchars($product !== '' ? $product : 'Не указан', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeHost = htmlspecialchars((string) ($_SERVER['HTTP_HOST'] ?? 'lesstrsnab.ru'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeIp = htmlspecialchars((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeUserAgent = htmlspecialchars((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeTime = htmlspecialchars(date('d.m.Y H:i:s'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$html = <<<HTML
<html>
  <head>
    <meta charset="UTF-8">
    <title>{$subject}</title>
  </head>
  <body>
    <h2>Новая заявка с сайта {$safeHost}</h2>
    <p><strong>Имя:</strong> {$safeName}</p>
    <p><strong>Телефон:</strong> {$safePhone}</p>
    <p><strong>Товар:</strong> {$safeProduct}</p>
    <hr>
    <p><strong>Время:</strong> {$safeTime}</p>
    <p><strong>IP:</strong> {$safeIp}</p>
    <p><strong>User-Agent:</strong> {$safeUserAgent}</p>
  </body>
</html>
HTML;

$postFields = [
    'subject' => $subject,
    'name' => $fromName,
    'html' => $html,
    'from' => $fromEmail,
    'to' => $toEmail,
    'reply' => $replyTo,
    'text' => "Новая заявка с сайта {$safeHost}\nИмя: {$name}\nТелефон: {$phone}\nТовар: " . ($product !== '' ? $product : 'Не указан') . "\nВремя: " . date('d.m.Y H:i:s'),
];

$ch = curl_init('https://api.smtp.bz/v1/smtp/send');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postFields,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . $apiKey,
    ],
]);

$responseBody = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($responseBody === false) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => 'Не удалось связаться с почтовым сервисом.',
        'details' => $curlError,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$responseJson = json_decode($responseBody, true);

if ($httpCode < 200 || $httpCode >= 300) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => 'Почтовый сервис вернул ошибку.',
        'details' => $responseJson ?? $responseBody,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'result' => $responseJson ?? $responseBody,
], JSON_UNESCAPED_UNICODE);
