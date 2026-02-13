<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';

header('Content-Type: application/json');

try {
    $disks = [];
    $osFamily = PHP_OS_FAMILY;

    if ($osFamily === 'Windows') {
        foreach (range('C', 'Z') as $letter) {
            $path = $letter . ':\\';
            $total = @disk_total_space($path);
            if ($total === false || $total <= 0) {
                continue;
            }
            $free = @disk_free_space($path);
            $used = ($free !== false) ? ($total - $free) : null;
            $disks[] = [
                'name' => $letter . ':',
                'total' => $total,
                'used' => $used
            ];
        }
    } else {
        $mounts = [];
        if (is_readable('/proc/mounts')) {
            $lines = file('/proc/mounts', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $parts = preg_split('/\s+/', $line);
                if (count($parts) < 3) continue;
                $mountPoint = $parts[1];
                $fsType = $parts[2];
                // skip virtual filesystems
                if (in_array($fsType, ['proc', 'sysfs', 'tmpfs', 'devtmpfs', 'cgroup', 'overlay', 'squashfs'])) {
                    continue;
                }
                $mounts[$mountPoint] = true;
            }
        }

        foreach (array_keys($mounts) as $mountPoint) {
            $total = @disk_total_space($mountPoint);
            if ($total === false || $total <= 0) continue;
            $free = @disk_free_space($mountPoint);
            $used = ($free !== false) ? ($total - $free) : null;
            $disks[] = [
                'name' => $mountPoint,
                'total' => $total,
                'used' => $used
            ];
        }
    }

    // RAM info (best-effort)
    $ramTotal = null;
    $ramFree = null;
    if ($osFamily === 'Windows' && function_exists('shell_exec')) {
        $wmic = @shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value');
        if ($wmic) {
            foreach (explode("\n", $wmic) as $line) {
                $line = trim($line);
                if (stripos($line, 'FreePhysicalMemory=') === 0) {
                    $ramFree = (int) substr($line, strlen('FreePhysicalMemory=')) * 1024;
                }
                if (stripos($line, 'TotalVisibleMemorySize=') === 0) {
                    $ramTotal = (int) substr($line, strlen('TotalVisibleMemorySize=')) * 1024;
                }
            }
        }
    } elseif (is_readable('/proc/meminfo')) {
        $meminfo = file('/proc/meminfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($meminfo as $line) {
            if (strpos($line, 'MemTotal:') === 0) {
                $ramTotal = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT) * 1024;
            }
            if (strpos($line, 'MemAvailable:') === 0) {
                $ramFree = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT) * 1024;
            }
        }
    }

    $ramUsed = null;
    if ($ramTotal !== null && $ramFree !== null) {
        $ramUsed = $ramTotal - $ramFree;
    } else {
        // Fallback to PHP process usage
        $ramUsed = memory_get_usage(true);
        $limit = ini_get('memory_limit');
        if ($limit && $limit !== '-1') {
            $unit = strtoupper(substr($limit, -1));
            $value = (int) $limit;
            $mult = 1;
            if ($unit === 'G') $mult = 1024 * 1024 * 1024;
            if ($unit === 'M') $mult = 1024 * 1024;
            if ($unit === 'K') $mult = 1024;
            $ramTotal = $value * $mult;
        }
    }

    $cpuLoad = null;
    if ($osFamily === 'Windows' && function_exists('shell_exec')) {
        $cpuOut = @shell_exec('wmic cpu get loadpercentage /Value');
        if ($cpuOut && preg_match('/LoadPercentage=(\\d+)/i', $cpuOut, $m)) {
            $cpuLoad = (int) $m[1] . '%';
        }
    } elseif (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        if ($load && isset($load[0])) {
            $cpuLoad = $load[0];
        }
    }

    $uptime = null;
    if (is_readable('/proc/uptime')) {
        $uptimeRaw = trim(file_get_contents('/proc/uptime'));
        $uptimeSeconds = (int) floor((float) explode(' ', $uptimeRaw)[0]);
        $hours = floor($uptimeSeconds / 3600);
        $minutes = floor(($uptimeSeconds % 3600) / 60);
        $uptime = $hours . 'h ' . $minutes . 'm';
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'disks' => $disks,
            'ram_total' => $ramTotal,
            'ram_used' => $ramUsed,
            'cpu_load' => $cpuLoad,
            'uptime' => $uptime,
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
