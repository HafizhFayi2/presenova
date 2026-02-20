<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Mengajar Guru - Presenova</title>
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
        $groupedSchedules = collect($schedules)->groupBy(function ($item) {
            return (string) ($item['day_name'] ?? '-');
        });
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
                    <h1>Jadwal Mengajar</h1>
                    <p>Portal Guru</p>
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
                    <strong>{{ $printedRole ?? 'Guru' }}</strong>
                </div>
            </div>
        </header>

        <section class="info-strip section-keep">
            <div class="info-item">
                <span>Nama Guru</span>
                <strong>{{ (string) ($teacher['teacher_name'] ?? '-') }}</strong>
            </div>
            <div class="info-item">
                <span>Mata Pelajaran</span>
                <strong>{{ (string) ($teacher['subject'] ?? '-') }}</strong>
            </div>
            <div class="info-item">
                <span>Filter Hari</span>
                <strong>{{ $dayLabel }}</strong>
            </div>
            <div class="info-item">
                <span>Filter Kelas</span>
                <strong>{{ $classLabel }}</strong>
            </div>
        </section>

        <section class="table-section">
            <h2>Daftar Jadwal Mengajar</h2>
            @if (!empty($schedules))
                <div class="table-wrap">
                    <table class="schedule-table schedule-table-grouped" aria-label="Jadwal Mengajar Guru">
                        <thead>
                            <tr>
                                <th>Hari</th>
                                <th>Jadwal</th>
                            </tr>
                        </thead>
                        @foreach ($groupedSchedules as $dayName => $daySchedules)
                            @php($dayClass = $dayClassMap[strtolower(trim((string) $dayName))] ?? 'day-lain')
                            <tbody class="day-group {{ $dayClass }}">
                                @foreach ($daySchedules as $index => $schedule)
                                    <tr>
                                        @if ($index === 0)
                                            <td class="cell-day" rowspan="{{ count($daySchedules) }}">{{ $dayName !== '' ? $dayName : '-' }}</td>
                                        @endif
                                        <td class="cell-schedule">
                                            <div class="schedule-line">
                                                <span class="line-pill">{{ (string) ($schedule['shift_name'] ?? '-') }}</span>
                                                <span class="line-time">
                                                    {{ date('H:i', strtotime((string) ($schedule['time_in'] ?? '00:00:00'))) }}
                                                    -
                                                    {{ date('H:i', strtotime((string) ($schedule['time_out'] ?? '00:00:00'))) }}
                                                </span>
                                            </div>
                                            <div class="schedule-main">{{ (string) ($schedule['subject'] ?? '-') }}</div>
                                            <div class="schedule-sub">
                                                <span>{{ (string) ($schedule['teacher_name'] ?? ($teacher['teacher_name'] ?? '-')) }}</span>
                                                <span>{{ (string) ($schedule['class_name'] ?? '-') }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @endforeach
                    </table>
                </div>
            @else
                <p class="empty-state">Belum ada jadwal mengajar sesuai filter saat ini.</p>
            @endif
        </section>

        <section class="summary-strip section-keep">
            <div class="summary-item">
                <span>Total Jadwal</span>
                <strong>{{ count($schedules) }}</strong>
            </div>
            <div class="summary-item">
                <span>Filter Hari</span>
                <strong>{{ $dayLabel }}</strong>
            </div>
            <div class="summary-item">
                <span>Filter Kelas</span>
                <strong>{{ $classLabel }}</strong>
            </div>
            <div class="summary-item">
                <span>Guru</span>
                <strong>{{ (string) ($teacher['teacher_name'] ?? '-') }}</strong>
            </div>
            <div class="summary-item">
                <span>Mapel</span>
                <strong>{{ (string) ($teacher['subject'] ?? '-') }}</strong>
            </div>
        </section>

        <footer class="sheet-footer section-keep">
            <div>Presenova - Bringing Back Learning Time</div>
            <div>Printed at {{ $printedAt }} by {{ $printedBy }} ({{ $printedRole ?? 'Guru' }})</div>
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
        const body = 'File PDF jadwal guru berhasil diunduh.';
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

        const opts = {
            margin: [8, 6, 8, 6],
            filename: @json($pdfFilename),
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, scrollY: 0 },
            jsPDF: { unit: 'mm', format: 'a4', orientation: @json($orientation === 'portrait' ? 'portrait' : 'landscape') },
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
