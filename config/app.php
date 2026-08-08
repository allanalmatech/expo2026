<?php
declare(strict_types=1);

defined('APP_NAME') || define('APP_NAME', 'Freshers Expo Stall Management Portal');
defined('APP_EVENT_NAME') || define('APP_EVENT_NAME', 'Freshers Expo 2026');
defined('APP_TIMEZONE') || define('APP_TIMEZONE', 'Africa/Kampala');

// Leave APP_URL empty to auto-detect the project path. Set it on cPanel if needed,
// for example: https://example.com/expo2026
defined('APP_URL') || define('APP_URL', '');
defined('APP_MAX_UPLOAD_BYTES') || define('APP_MAX_UPLOAD_BYTES', 5 * 1024 * 1024);

date_default_timezone_set(APP_TIMEZONE);
