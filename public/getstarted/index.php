<?php

declare(strict_types=1);

$scriptName = '/' . ltrim(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$basePath = preg_replace('~(?:/public)?/getstarted/index\.php$~i', '', $scriptName) ?? '';
$basePath = rtrim($basePath, '/');
$target = $basePath === '' ? '/' : $basePath . '/';

header('Location: ' . $target, true, 302);
exit;
