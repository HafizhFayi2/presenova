<?php
// ajax/config.php
session_start();

// Naik 2 level ke root dari ajax folder
$base_dir = dirname(__DIR__, 2);

// Include konfigurasi utama
require_once $base_dir . '/includes/config.php';
require_once $base_dir . '/includes/database.php';