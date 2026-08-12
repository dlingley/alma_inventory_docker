<?php
// Set default application timezone to Purdue time (America/Indiana/Indianapolis - Eastern Time)
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'America/Indiana/Indianapolis');

// Load local .env file if present
$envFile = __DIR__ . '/.env';
if (file_exists($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val, " \t\n\r\0\x0B\"'");
                if ($key !== '' && (getenv($key) === false || getenv($key) === '')) {
                    putenv("$key=$val");
                    $_ENV[$key] = $val;
                }
            }
        }
    }
}

// Set your Alma Shelflist API Key here, or supply it via the ALMA_SHELFLIST_API_KEY
// environment variable (recommended when using Docker / .env).
define("ALMA_SHELFLIST_API_KEY", trim(getenv("ALMA_SHELFLIST_API_KEY") ?: "*****YOUR KEY HERE *********"));
define("ALMA_ANALYTICS_API_KEY", trim(getenv("ALMA_ANALYTICS_API_KEY") ?: ALMA_SHELFLIST_API_KEY));
define("ALMA_ANALYTICS_REPORT_PATH", trim(getenv("ALMA_ANALYTICS_REPORT_PATH") ?: "/shared/Purdue University/Reports/CallNumberSortCheck"));
define("ENABLE_ALMA_ANALYTICS_AUTO_CHECK", filter_var(getenv("ENABLE_ALMA_ANALYTICS_AUTO_CHECK") ?: "false", FILTER_VALIDATE_BOOLEAN));

