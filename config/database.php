<?php
const DEPLOYMENT_ENV = 'development'; // Change to 'production' in production environment

// Database configuration - Check multiple sources with fallbacks
define('DB_HOST', 'localhost');
define('DB_USER', DEPLOYMENT_ENV === 'production' ? 'gatherly_sys' : 'root');
define('DB_PASS', DEPLOYMENT_ENV === 'production' ? 'zeND{ATJuYIY' : '');
define('DB_NAME', DEPLOYMENT_ENV === 'production' ? 'gatherly_sad_db' : 'sad_db');

// Debug: Log if values are not loaded (can be removed in production)
if (empty(DB_HOST) || DB_HOST === 'localhost' && empty(DB_NAME)) {
    error_log("DB_HOST: " . DB_HOST . ", DB_NAME: " . DB_NAME . ", DB_USER: " . DB_USER);
}