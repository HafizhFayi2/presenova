<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

class StudentPushNotificationService
{
    public function isEnabled(): bool
    {
        $enabled = filter_var((string) env('PUSH_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN);
        $publicKey = trim((string) env('PUSH_VAPID_PUBLIC_KEY', ''));
        $privateKey = trim((string) env('PUSH_VAPID_PRIVATE_KEY', ''));
        $nodeBin = trim((string) env('PUSH_NODE_BIN', 'node'));
        $sendScript = trim((string) env('PUSH_SEND_SCRIPT', public_path('scripts/webpush/send.js')));

        return $enabled && $publicKey !== '' && $privateKey !== '' && $nodeBin !== '' && $sendScript !== '' && is_file($sendScript);
    }

    /**
     * @return array{ok:bool, log_id:int|null, duplicate:bool, failed:int, errors:array<int, string>}
     */
    public function notifyStudent(
        int $studentId,
        string $type,
        string $title,
        string $body,
        string $url = '/dashboard/siswa.php?page=jadwal',
        ?int $scheduleId = null,
        DateTimeInterface|string|null $scheduledAt = null
    ): array {
        if ($studentId <= 0 || trim($type) === '' || trim($title) === '') {
            return [
                'ok' => false,
                'log_id' => null,
                'duplicate' => false,
                'failed' => 1,
                'errors' => ['Invalid notification payload'],
            ];
        }

        $scheduledAtString = $this->formatScheduledAt($scheduledAt);
        $logId = $this->insertNotificationLog($studentId, $scheduleId, $type, $scheduledAtString);
        if ($logId === null) {
            return [
                'ok' => true,
                'log_id' => null,
                'duplicate' => true,
                'failed' => 0,
                'errors' => [],
            ];
        }

        if (!$this->isEnabled()) {
            $this->updateNotificationLog($logId, 'FAILED', 'Push service not configured');

            return [
                'ok' => false,
                'log_id' => $logId,
                'duplicate' => false,
                'failed' => 1,
                'errors' => ['Push service not configured'],
            ];
        }

        $payload = [
            'title' => trim($title),
            'body' => trim($body),
            'url' => $this->normalizeNotificationUrl($url),
        ];

        $result = $this->sendPushToStudent($studentId, $payload);
        if (($result['failed'] ?? 0) === 0) {
            $this->updateNotificationLog($logId, 'SENT');

            return [
                'ok' => true,
                'log_id' => $logId,
                'duplicate' => false,
                'failed' => 0,
                'errors' => [],
            ];
        }

        $errors = $result['errors'] ?? [];
        $errorMessage = is_array($errors) && $errors !== [] ? implode(' | ', $errors) : 'Push failed';
        $this->updateNotificationLog($logId, 'FAILED', $errorMessage);

        return [
            'ok' => false,
            'log_id' => $logId,
            'duplicate' => false,
            'failed' => (int) ($result['failed'] ?? 1),
            'errors' => is_array($errors) ? $errors : ['Push failed'],
        ];
    }

    /**
     * @param array<int> $studentIds
     */
    public function notifyStudents(
        array $studentIds,
        string $type,
        string $title,
        string $body,
        string $url = '/dashboard/siswa.php?page=jadwal',
        ?int $scheduleId = null,
        DateTimeInterface|string|null $scheduledAt = null
    ): int {
        $sentCount = 0;
        $uniqueIds = array_values(array_unique(array_map(static fn ($id): int => (int) $id, $studentIds)));

        foreach ($uniqueIds as $studentId) {
            if ($studentId <= 0) {
                continue;
            }

            $result = $this->notifyStudent(
                $studentId,
                $type,
                $title,
                $body,
                $url,
                $scheduleId,
                $scheduledAt
            );

            if (!empty($result['ok']) || !empty($result['duplicate'])) {
                $sentCount++;
            }
        }

        return $sentCount;
    }

    public function normalizeNotificationUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return $this->appPath('/dashboard/siswa.php?page=jadwal');
        }
        if (preg_match('~^https?://~i', $url) === 1) {
            return $url;
        }

        return $this->appPath($url);
    }

    /**
     * @return array{failed:int, errors:array<int, string>}
     */
    private function sendPushToStudent(int $studentId, array $payload): array
    {
        $pushTtl = $this->pushTtlSeconds();

        $tokens = DB::table('push_tokens')
            ->where('student_id', $studentId)
            ->where('is_active', 'Y')
            ->select('id', 'endpoint', 'p256dh', 'auth', 'content_encoding')
            ->get()
            ->map(static fn ($row) => (array) $row)
            ->all();

        if ($tokens === []) {
            return ['failed' => 0, 'errors' => []];
        }

        $tasks = [];
        foreach ($tokens as $token) {
            $endpoint = trim((string) ($token['endpoint'] ?? ''));
            $p256dh = trim((string) ($token['p256dh'] ?? ''));
            $auth = trim((string) ($token['auth'] ?? ''));
            if ($endpoint === '' || $p256dh === '' || $auth === '') {
                continue;
            }

            $tasks[] = [
                'id' => (string) ($token['id'] ?? ''),
                'subscription' => [
                    'endpoint' => $endpoint,
                    'keys' => [
                        'p256dh' => $p256dh,
                        'auth' => $auth,
                    ],
                ],
                'payload' => [
                    'title' => (string) ($payload['title'] ?? 'Notifikasi'),
                    'body' => (string) ($payload['body'] ?? ''),
                    'url' => (string) ($payload['url'] ?? '/'),
                ],
                'options' => [
                    'TTL' => $pushTtl,
                ],
            ];
        }

        if ($tasks === []) {
            return ['failed' => 0, 'errors' => []];
        }

        $results = $this->dispatchPushTasks($tasks);
        $failed = 0;
        $errors = [];

        foreach ($tasks as $task) {
            $taskId = (string) ($task['id'] ?? '');
            $result = $results[$taskId] ?? null;
            $isSuccess = is_array($result) && !empty($result['success']);

            if ($isSuccess) {
                continue;
            }

            $failed++;
            $message = is_array($result)
                ? trim((string) ($result['message'] ?? 'Push failed'))
                : 'Push dispatcher failed';
            $statusCode = is_array($result) ? (int) ($result['statusCode'] ?? 0) : 0;
            $errors[] = $message !== '' ? $message : 'Push failed';

            if (in_array($statusCode, [404, 410], true) && $taskId !== '') {
                DB::table('push_tokens')
                    ->where('id', (int) $taskId)
                    ->update([
                        'is_active' => 'N',
                        'updated_at' => now(),
                    ]);
            }
        }

        return ['failed' => $failed, 'errors' => $errors];
    }

    /**
     * @param array<int, array<string, mixed>> $tasks
     * @return array<string, array<string, mixed>>
     */
    private function dispatchPushTasks(array $tasks): array
    {
        if ($tasks === [] || !$this->isEnabled()) {
            return [];
        }

        $payload = [
            'vapid' => [
                'publicKey' => trim((string) env('PUSH_VAPID_PUBLIC_KEY', '')),
                'privateKey' => trim((string) env('PUSH_VAPID_PRIVATE_KEY', '')),
                'subject' => trim((string) env('PUSH_VAPID_SUBJECT', 'mailto:admin@presenova.local')),
            ],
            'tasks' => $tasks,
        ];

        $storageApp = storage_path('app');
        if (!is_dir($storageApp)) {
            @mkdir($storageApp, 0777, true);
        }

        try {
            $token = bin2hex(random_bytes(8));
        } catch (\Throwable) {
            $token = sha1(microtime(true) . '|' . mt_rand());
        }

        $inputPath = $storageApp . DIRECTORY_SEPARATOR . 'push-send-' . $token . '.json';
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || @file_put_contents($inputPath, $encoded) === false) {
            return [];
        }

        $nodeBin = trim((string) env('PUSH_NODE_BIN', 'node'));
        $sendScript = trim((string) env('PUSH_SEND_SCRIPT', public_path('scripts/webpush/send.js')));

        $command = escapeshellarg($nodeBin) . ' ' . escapeshellarg($sendScript) . ' ' . escapeshellarg($inputPath) . ' 2>&1';
        $raw = shell_exec($command);
        @unlink($inputPath);

        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode(trim($raw), true);
        if (!is_array($decoded) || !isset($decoded['results']) || !is_array($decoded['results'])) {
            if (!preg_match('/\{.*\}\s*$/s', trim($raw), $match)) {
                return [];
            }
            $decoded = json_decode($match[0], true);
            if (!is_array($decoded) || !isset($decoded['results']) || !is_array($decoded['results'])) {
                return [];
            }
        }

        $results = [];
        foreach ($decoded['results'] as $row) {
            if (!is_array($row) || !isset($row['id'])) {
                continue;
            }
            $results[(string) $row['id']] = $row;
        }

        return $results;
    }

    private function insertNotificationLog(int $studentId, ?int $scheduleId, string $type, string $scheduledAt): ?int
    {
        $query = DB::table('push_notification_logs')
            ->where('student_id', $studentId)
            ->where('type', $type)
            ->where('scheduled_at', $scheduledAt);

        if ($scheduleId !== null && $scheduleId > 0) {
            $query->where('student_schedule_id', $scheduleId);
        } else {
            $query->whereNull('student_schedule_id');
        }

        $existingId = (int) ($query->value('id') ?? 0);
        if ($existingId > 0) {
            return null;
        }

        DB::table('push_notification_logs')->insert([
            'student_id' => $studentId,
            'student_schedule_id' => ($scheduleId !== null && $scheduleId > 0) ? $scheduleId : null,
            'type' => $type,
            'scheduled_at' => $scheduledAt,
            'status' => 'PENDING',
            'created_at' => now(),
        ]);

        $logId = (int) DB::getPdo()->lastInsertId();

        return $logId > 0 ? $logId : null;
    }

    private function updateNotificationLog(int $logId, string $status, ?string $errorMessage = null): void
    {
        if ($logId <= 0) {
            return;
        }

        DB::table('push_notification_logs')
            ->where('id', $logId)
            ->update([
                'status' => $status,
                'sent_at' => now(),
                'error_message' => $errorMessage,
            ]);
    }

    private function formatScheduledAt(DateTimeInterface|string|null $scheduledAt): string
    {
        if ($scheduledAt instanceof DateTimeInterface) {
            return $scheduledAt->format('Y-m-d H:i:s');
        }

        $timezone = new DateTimeZone('Asia/Jakarta');
        if (is_string($scheduledAt) && trim($scheduledAt) !== '') {
            try {
                return (new DateTimeImmutable($scheduledAt, $timezone))->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                // fallthrough
            }
        }

        return (new DateTimeImmutable('now', $timezone))->format('Y-m-d H:i:s');
    }

    private function appPath(string $path): string
    {
        if (preg_match('~^https?://~i', $path) === 1) {
            return $path;
        }

        $path = '/' . ltrim($path, '/');
        $prefix = $this->resolveAppPrefix();
        if ($prefix === '') {
            return $path;
        }

        return '/' . $prefix . $path;
    }

    private function resolveAppPrefix(): string
    {
        $configuredPath = trim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');
        if ($configuredPath === '') {
            return '';
        }

        $segments = array_values(array_filter(explode('/', $configuredPath), static fn (string $segment): bool => $segment !== ''));
        $segmentCount = count($segments);
        if ($segmentCount < 2) {
            return implode('/', $segments);
        }

        for ($size = intdiv($segmentCount, 2); $size >= 1; $size--) {
            if (($segmentCount % $size) !== 0 || $segmentCount < ($size * 2)) {
                continue;
            }

            $pattern = array_slice($segments, 0, $size);
            $allSame = true;
            for ($index = $size; $index < $segmentCount; $index += $size) {
                if (array_slice($segments, $index, $size) !== $pattern) {
                    $allSame = false;
                    break;
                }
            }

            if ($allSame) {
                return implode('/', $pattern);
            }
        }

        return implode('/', $segments);
    }

    private function pushTtlSeconds(): int
    {
        $rawTtl = (int) env('PUSH_TTL_SECONDS', 86400);
        if ($rawTtl < 60) {
            return 60;
        }
        if ($rawTtl > 604800) {
            return 604800;
        }

        return $rawTtl;
    }
}
