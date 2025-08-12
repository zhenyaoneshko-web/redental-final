<?php
// Simple webhook for form submissions
// Place this file at: /files/sendmail/webhoock.php

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Kyiv');

// ==== CONFIG (set your values) ====
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: '');
define('TELEGRAM_CHAT_ID', getenv('TELEGRAM_CHAT_ID') ?: '');
// Optional Google Apps Script Web App URL to write to Sheets
define('GOOGLE_SCRIPT_URL', getenv('GOOGLE_SCRIPT_URL') ?: '');

// Set to true to only log locally (no external requests)
$DRY_RUN = false;

// ==== HELPERS ====
function respond($ok, $msg='', $extra=[]) {
    http_response_code($ok ? 200 : 400);
    echo json_encode(array_merge(['ok'=>$ok,'message'=>$msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitize($s) {
    return trim(filter_var($s, FILTER_SANITIZE_STRING));
}

// ==== INPUT ====
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    respond(false, 'Method not allowed');
}

$name    = sanitize($_POST['name']    ?? '');
$phone   = sanitize($_POST['phone']   ?? '');
$comment = sanitize($_POST['comment'] ?? '');

$utm = [
  'utm_source'   => sanitize($_POST['utm_source']   ?? ''),
  'utm_medium'   => sanitize($_POST['utm_medium']   ?? ''),
  'utm_campaign' => sanitize($_POST['utm_campaign'] ?? ''),
  'utm_content'  => sanitize($_POST['utm_content']  ?? ''),
  'utm_term'     => sanitize($_POST['utm_term']     ?? ''),
];

if ($phone === '') {
    respond(false, 'Phone is required');
}

// ==== LOG ====
$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }
$logLine = date('Y-m-d H:i:s') . " | phone={$phone} | name={$name} | comment={$comment} | " . json_encode($utm, JSON_UNESCAPED_UNICODE) . PHP_EOL;
@file_put_contents($logDir.'/requests.log', $logLine, FILE_APPEND);

// ==== DRY RUN (for local test) ====
if ($DRY_RUN) {
    respond(true, 'Logged locally (DRY_RUN)');
}

// ==== TELEGRAM ====
$tg_ok = null;
if (TELEGRAM_BOT_TOKEN && TELEGRAM_CHAT_ID) {
    $text = "🦷 Нова заявка:\n"
          . "Ім'я: {$name}\n"
          . "Телефон: {$phone}\n"
          . ($comment ? "Коментар: {$comment}\n" : "")
          . "UTM: " . json_encode($utm, JSON_UNESCAPED_UNICODE);
    $payload = ['chat_id' => TELEGRAM_CHAT_ID, 'text' => $text, 'parse_mode' => 'HTML'];

    $ch = curl_init("https://api.telegram.org/bot".TELEGRAM_BOT_TOKEN."/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $tg_ok = ($err === '' && $code == 200);
}

// ==== GOOGLE SHEETS via Apps Script (optional) ====
$gs_ok = null;
if (GOOGLE_SCRIPT_URL) {
    $payload = [
      'name'    => $name,
      'phone'   => $phone,
      'comment' => $comment,
      'utm'     => $utm,
      'ts'      => date('c')
    ];
    $ch = curl_init(GOOGLE_SCRIPT_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $gs_ok = ($err === '' && $code == 200);
}

respond(true, 'Submitted', ['telegram'=>$tg_ok, 'google_sheets'=>$gs_ok]);
