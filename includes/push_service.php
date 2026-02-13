<?php
require_once __DIR__ . '/config.php';

function getPushConfig() {
    static $config = null;
    if ($config === null) {
        $configPath = __DIR__ . '/../config/push.php';
        $config = file_exists($configPath) ? require $configPath : [];
    }
    return $config;
}

function isPushEnabled() {
    $config = getPushConfig();
    return !empty($config['enabled'])
        && !empty($config['vapid_public_key'])
        && !empty($config['vapid_private_key']);
}

function buildSubscriptionFromRow($row) {
    return [
        'endpoint' => $row['endpoint'],
        'expirationTime' => null,
        'keys' => [
            'p256dh' => $row['p256dh'],
            'auth' => $row['auth']
        ]
    ];
}

function fetchActivePushTokens($db, $studentId = null) {
    $params = [];
    $sql = "SELECT * FROM push_tokens WHERE is_active = 'Y'";
    if ($studentId !== null) {
        $sql .= " AND student_id = ?";
        $params[] = $studentId;
    }
    $stmt = $db->query($sql, $params);
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function deactivatePushTokens($db, $tokenIds) {
    if (empty($tokenIds)) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($tokenIds), '?'));
    $db->query("UPDATE push_tokens SET is_active = 'N', updated_at = NOW() WHERE id IN ($placeholders)", $tokenIds);
}

function sendWebPushBatch($tasks, $config, &$errorMessage = null) {
    $errorMessage = null;

    if (empty($tasks)) {
        return [];
    }

    if (empty($config['send_script']) || empty($config['node_bin'])) {
        $errorMessage = 'Push sender script is not configured.';
        return [];
    }

    $payload = [
        'vapid' => [
            'publicKey' => $config['vapid_public_key'] ?? '',
            'privateKey' => $config['vapid_private_key'] ?? '',
            'subject' => $config['subject'] ?? 'mailto:admin@localhost'
        ],
        'tasks' => $tasks
    ];

    $tempFile = tempnam(sys_get_temp_dir(), 'presenova_push_');
    if ($tempFile === false) {
        $errorMessage = 'Unable to create temp file for push payload.';
        return [];
    }

    file_put_contents($tempFile, json_encode($payload));

    $nodeBin = $config['node_bin'];
    $scriptPath = $config['send_script'];
    $command = escapeshellcmd($nodeBin) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($tempFile);

    $descriptorSpec = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];

    $process = proc_open($command, $descriptorSpec, $pipes);
    if (!is_resource($process)) {
        unlink($tempFile);
        $errorMessage = 'Failed to start push sender process.';
        return [];
    }

    $output = stream_get_contents($pipes[1]);
    $errorOutput = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    unlink($tempFile);

    if ($exitCode !== 0) {
        $errorMessage = trim($errorOutput) ?: 'Push sender failed with exit code ' . $exitCode;
        return [];
    }

    $decoded = json_decode($output, true);
    if (!is_array($decoded) || !isset($decoded['results'])) {
        $errorMessage = 'Push sender returned invalid response.';
        return [];
    }

    return $decoded['results'];
}

function sendPushToStudent($db, $studentId, $payload, $options = []) {
    if (!isPushEnabled()) {
        return [
            'sent' => 0,
            'failed' => 0,
            'errors' => ['Push service disabled']
        ];
    }

    $tokens = fetchActivePushTokens($db, $studentId);
    if (empty($tokens)) {
        return [
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];
    }

    $tasks = [];
    foreach ($tokens as $token) {
        $tasks[] = [
            'id' => $token['id'],
            'subscription' => buildSubscriptionFromRow($token),
            'payload' => $payload,
            'options' => $options
        ];
    }

    $config = getPushConfig();
    $errorMessage = null;
    $results = sendWebPushBatch($tasks, $config, $errorMessage);

    $sent = 0;
    $failed = 0;
    $invalidTokens = [];
    $errors = [];

    if ($errorMessage) {
        $errors[] = $errorMessage;
        $failed = count($tasks);
    } else {
        foreach ($results as $result) {
            if (!empty($result['success'])) {
                $sent++;
            } else {
                $failed++;
                $statusCode = (int) ($result['statusCode'] ?? 0);
                if (in_array($statusCode, [404, 410], true) && !empty($result['id'])) {
                    $invalidTokens[] = $result['id'];
                }
                if (!empty($result['message'])) {
                    $errors[] = $result['message'];
                }
            }
        }
    }

    if (!empty($invalidTokens)) {
        deactivatePushTokens($db, $invalidTokens);
    }

    return [
        'sent' => $sent,
        'failed' => $failed,
        'errors' => $errors
    ];
}
?>
