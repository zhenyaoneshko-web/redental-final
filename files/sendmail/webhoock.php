<?php
// ===== Налаштування =====
define('TELEGRAM_BOT_TOKEN', '8491854636:AAERwJuq9t9IitqRqGI-br-FavWizf2FgbY');
define('TELEGRAM_CHAT_ID', '-1002747934639');

// ===== Отримання даних з форми =====
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if ($name === '' && $phone === '' && $message === '') {
    echo "error: no data";
    exit;
}

// ===== Формування повідомлення =====
$txt = "📩 <b>Нова заявка з сайту</b>\n";
if ($name) $txt .= "👤 Ім'я: " . htmlspecialchars($name) . "\n";
if ($phone) $txt .= "📞 Телефон: " . htmlspecialchars($phone) . "\n";
if ($message) $txt .= "💬 Повідомлення: " . htmlspecialchars($message) . "\n";
$txt .= "\n⏰ Час: " . date("Y-m-d H:i:s");

// ===== Відправка в Telegram =====
$send = file_get_contents("https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage?" . http_build_query([
    'chat_id' => TELEGRAM_CHAT_ID,
    'text' => $txt,
    'parse_mode' => 'HTML'
]));

// ===== Відповідь браузеру =====
if ($send) {
    header("Location: https://redental.com.ua/thanks.html", true, 303);
} else {
    echo "error";
}
exit;