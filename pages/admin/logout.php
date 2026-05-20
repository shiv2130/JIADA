<?php
define('JIADA_APP', true);
require_once __DIR__ . '/../../config/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
AdminAuth::logout();