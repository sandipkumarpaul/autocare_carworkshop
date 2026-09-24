<?php
// Default settings for a local XAMPP / MySQL setup.
// To override them (e.g. on a live host), create config.local.php next to this
// file and define the constants you need there. It is ignored by git.
if (file_exists(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', 'car_workshop');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');

// Maximum number of cars a single mechanic can take on one day.
defined('MAX_APPOINTMENTS_PER_DAY') || define('MAX_APPOINTMENTS_PER_DAY', 4);
