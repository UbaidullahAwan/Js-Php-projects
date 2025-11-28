<?php
// email_config.php
define('EMAIL_FROM', 'noreply@yourairline.com');
define('EMAIL_FROM_NAME', 'Airline Booking System');
define('EMAIL_REPLY_TO', 'customer.service@yourairline.com');
define('SUPPORT_PHONE', '+1-800-FLY-AWAY');
define('SUPPORT_EMAIL', 'support@yourairline.com');

// SMTP Configuration (if using PHPMailer in future)
define('SMTP_HOST', 'smtp.yourairline.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@yourairline.com');
define('SMTP_PASSWORD', 'your_password');
define('SMTP_SECURE', 'tls');
?>