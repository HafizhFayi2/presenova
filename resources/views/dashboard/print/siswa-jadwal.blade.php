<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Pelajaran - Presenova</title>
    @php
        $printCssPath = public_path('assets/css/print/jadwal-print.css');
        $printCssUrl = asset('assets/css/print/jadwal-print.css') . '?v=20260220a';
        $logoUrl = asset('assets/images/presenova.png');
        $logoFallbackUrl = asset('assets/images/logo-192.png');
        $logoPrimaryPath = public_path('assets/images/presenova.png');
        $logoFallbackPath = public_path('assets/images/logo-192.png');
        if (is_file($logoPrimaryPath)) {
            $logoPrimaryBinary = file_get_contents($logoPrimaryPath);
            if ($logoPrimaryBinary !== false) {
                $logoUrl = 'data:image/png;base64,' . base64_encode($logoPrimaryBinary);
            }
        }
        if (is_file($logoFallbackPath)) {
            $logoFallbackBinary = file_get_contents($logoFallbackPath);
            if ($logoFallbackBinary !== false) {
                $logoFallbackUrl = 'data:image/png;base64,' . base64_encode($logoFallbackBinary);
            }
        }
        $printCssInline = is_file($printCssPath) ? file_get_contents($printCssPath) : '';
        $dayClassMap = [
            'senin' => 'day-senin',
            'selasa' => 'day-selasa',
            'rabu' => 'day-rabu',
            'kamis' => 'day-kamis',
            'jumat' => 'day-jumat',
            'sabtu' => 'day-sabtu',
            'minggu' => 'day-minggu',
        ];
    @endphp
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ $printCssUrl }}">
    @if (!empty($printCssInline))
    <style>{!! $printCssInline !!}</style>
    @endif
    @if ($orientation === 'portrait')
    <style>@media print { @page { size: A4 portrait; } }</style>
    @elseif ($orientation === 'landscape')
    <style>@media print { @page { size: A4 landscape; } }</style>
    @endif
</head>
<body>
    <main class="print-sheet">
        <header class="sheet-header section-keep">
            <div class="brand-area">
                <img class="brand-logo" src="{{ $logoUrl }}" alt="Logo Presenova" onerror="this.onerror=null;this.src='{{ $logoFallbackUrl }}';">
                <div class="brand-text">
                    <h1>Jadwal Pelajaran</h1>
                    <p>SMKN 1 Cikarang Selatan</p>
                    <p class="academic-year">Presenova</p>
                </div>
            </div>
            <div class="print-meta">
                <div>
                    <span>Printed At</span>
                    <strong>{{ $printedAt }}</strong>
                </div>
                <div>
                    <span>Printed By</span>
                    <strong>{{ $printedBy }}</strong>
                </div>
                <div>
                    <span>Role</span>
                    <strong>{{ $printedRole ?? 'Siswa' }}</strong>
                </div>
            </div>
        </header>

        <section class="info-strip section-keep">
            <div class="info-item">
                <span>Nama Siswa</span>
                <strong>{{ (string) ($studentData['student_name'] ?? '-') }}</strong>
            </div>
            <div class="info-item">
                <span>Kelas</span>
                <strong>{{ (string) ($studentData['class_name'] ?? '-') }}</strong>
            </div>
            <div class="info-item">
                <span>Jurusan</span>
                <strong>{{ (string) ($studentData['jurusan_name'] ?? '-') }}</strong>
            </div>
            <div class="info-item">
                <span>Tanggal</span>
                <strong>{{ $todayLabel }}</strong>
            </div>
        </section>

        <section class="table-section">
            <h2>Jadwal Minggu Ini</h2>

            @if (!empty($groupedSchedule))
                <div class="table-wrap">
                    <table class="schedule-table" aria-label="Jadwal Mingguan Siswa">
                        <thead>
                            <tr>
                                <th>Hari</th>
                                <th>Shift</th>
                                <th>Waktu</th>
                                <th>Mata Pelajaran</th>
                                <th>Guru</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        @foreach ($groupedSchedule as $day => $daySchedules)
                            @php($dayClass = $dayClassMap[strtolower(trim((string) $day))] ?? 'day-lain')
                            <tbody class="day-group {{ $dayClass }} {{ $day === $todayIndonesian ? 'is-today' : '' }}">
                                @foreach ($daySchedules as $index => $schedule)
                                    @php($status = $schedule['resolved_status'] ?? ['status_class' => 'status-muted', 'status_text' => 'MENUNGGU', 'action_text' => '-', 'is_alpa' => false])
                                    <tr>
                                        @if ($index === 0)
                                            <td class="cell-day" rowspan="{{ count($daySchedules) }}">{{ $day }}</td>
                                        @endif
                                        <td class="cell-shift">{{ (string) ($schedule['shift_name'] ?? '-') }}</td>
                                        <td class="cell-time">
                                            {{ date('H:i', strtotime((string) ($schedule['time_in'] ?? '00:00:00'))) }}
                                            -
                                            {{ date('H:i', strtotime((string) ($schedule['time_out'] ?? '00:00:00'))) }}
                                        </td>
                                        <td class="cell-subject">{{ (string) ($schedule['subject'] ?? '-') }}</td>
                                        <td>{{ (string) ($schedule['teacher_name'] ?? '-') }}</td>
                                        <td class="{{ (string) ($status['status_class'] ?? 'status-muted') }}">
                                            {{ (string) ($status['status_text'] ?? 'MENUNGGU') }}
                                        </td>
                                        <td>{{ (string) ($status['action_text'] ?? '-') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @endforeach
                    </table>
                </div>
            @else
                <p class="empty-state">Belum ada jadwal pelajaran untuk kelas Anda.</p>
            @endif
        </section>

        <section class="summary-strip section-keep">
            <div class="summary-item">
                <span>Total Jadwal</span>
                <strong>{{ $attendedCount + $pendingCount + $alpaCount }}</strong>
            </div>
            <div class="summary-item">
                <span>Sudah Absen</span>
                <strong>{{ $attendedCount }}</strong>
            </div>
            <div class="summary-item">
                <span>Pending</span>
                <strong>{{ $pendingCount }}</strong>
            </div>
            <div class="summary-item">
                <span>Alpa</span>
                <strong>{{ $alpaCount }}</strong>
            </div>
            <div class="summary-item">
                <span>Guru Terlibat</span>
                <strong>{{ $teacherCount }}</strong>
            </div>
        </section>

        <footer class="sheet-footer section-keep">
            <div>Presenova - Bringing Back Learning Time</div>
            <div>Printed at {{ $printedAt }} by {{ $printedBy }} ({{ $printedRole ?? 'Siswa' }})</div>
        </footer>
    </main>

    @if ($downloadPdf)
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
    function notifyPdfDownloaded() {
        if (!('Notification' in window) || Notification.permission !== 'granted') {
            return;
        }

        const title = 'Download PDF Berhasil';
        const body = 'File PDF jadwal siswa berhasil diunduh.';
        const icon = @json(asset('assets/images/logo-192.png'));

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.ready.then(function (registration) {
                registration.showNotification(title, {
                    body: body,
                    icon: icon,
                    badge: icon,
                    data: { url: window.location.href }
                });
            }).catch(function () {
                // non-blocking
            });
            return;
        }

        try {
            new Notification(title, { body: body, icon: icon });
        } catch (e) {
            // non-blocking
        }
    }

    window.addEventListener('load', function () {
        const target = document.querySelector('.print-sheet');
        if (!target || typeof window.html2pdf === 'undefined') {
            window.print();
            return;
        }

        const pdfOrientation = @json($orientation === 'portrait' ? 'portrait' : 'landscape');
        const opts = {
            margin: [8, 6, 8, 6],
            filename: @json($pdfFilename),
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, scrollY: 0 },
            jsPDF: { unit: 'mm', format: 'a4', orientation: pdfOrientation },
            pagebreak: { mode: ['css', 'legacy'] }
        };

        setTimeout(function () {
            window.html2pdf().set(opts).from(target).save().then(function () {
                notifyPdfDownloaded();
                setTimeout(function () {
                    window.close();
                }, 250);
            });
        }, 220);
    });
    </script>
    @elseif ($autoprint)
    <script>
    window.addEventListener('load', function () {
        setTimeout(function () {
            window.print();
        }, 250);
    });
    </script>
    @endif
</body>
</html>
