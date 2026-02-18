<div class="modal-header bg-primary text-white">
    <h5 class="modal-title">
        <i class="fas fa-{{ $scheduleId > 0 ? 'edit' : 'plus-circle' }} me-2"></i>
        {{ $scheduleId > 0 ? 'Edit' : 'Tambah' }} Jadwal Mengajar
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form method="POST" action="admin.php?table=schedule" id="scheduleForm">
    <input type="hidden" name="schedule_id" value="{{ $scheduleId }}">

    <div class="modal-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Guru <span class="text-danger">*</span></label>
                <select class="form-select" name="teacher_id" id="teacherSelect" required>
                    <option value="">Pilih Guru</option>
                    @foreach ($teachers as $teacher)
                        <option value="{{ (int) ($teacher['id'] ?? 0) }}"
                            data-subject="{{ (string) ($teacher['subject'] ?? '') }}"
                            {{ (int) ($schedule['teacher_id'] ?? 0) === (int) ($teacher['id'] ?? 0) ? 'selected' : '' }}>
                            {{ (string) ($teacher['teacher_name'] ?? '') }}
                            @if (!empty($teacher['subject']))
                                ({{ (string) $teacher['subject'] }})
                            @endif
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Mata pelajaran akan otomatis terisi</div>
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Kelas <span class="text-danger">*</span></label>
                <select class="form-select" name="class_id" required>
                    <option value="">Pilih Kelas</option>
                    @foreach ($classes as $class)
                        <option value="{{ (int) ($class['class_id'] ?? 0) }}"
                            {{ (int) ($schedule['class_id'] ?? 0) === (int) ($class['class_id'] ?? 0) ? 'selected' : '' }}>
                            {{ (string) ($class['class_name'] ?? '') }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="subject" id="subjectInput"
                   value="{{ (string) ($schedule['subject'] ?? '') }}"
                   placeholder="Contoh: Matematika, Bahasa Indonesia" required>
            <div class="form-text">Otomatis terisi berdasarkan guru yang dipilih</div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Hari <span class="text-danger">*</span></label>
                <select class="form-select" name="day_id" required>
                    <option value="">Pilih Hari</option>
                    @foreach ($days as $day)
                        <option value="{{ (int) ($day['day_id'] ?? 0) }}"
                            {{ (int) ($schedule['day_id'] ?? 0) === (int) ($day['day_id'] ?? 0) ? 'selected' : '' }}>
                            {{ (string) ($day['day_name'] ?? '') }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3 mb-3">
                <label class="form-label">JP Mulai <span class="text-danger">*</span></label>
                <select class="form-select" name="jp_start" id="jpStart" required>
                    @for ($i = 1; $i <= 12; $i++)
                        @php $isBreak = in_array($i, [5, 9], true); @endphp
                        <option value="{{ $i }}"
                            {{ (int) $jpStart === $i ? 'selected' : '' }}
                            {{ $isBreak ? 'disabled' : '' }}>
                            JP{{ $i }}{{ $isBreak ? ' (Istirahat)' : '' }}
                        </option>
                    @endfor
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">JP Selesai <span class="text-danger">*</span></label>
                <select class="form-select" name="jp_end" id="jpEnd" required>
                    @for ($i = 1; $i <= 12; $i++)
                        @php $isBreak = in_array($i, [5, 9], true); @endphp
                        <option value="{{ $i }}"
                            {{ (int) $jpEnd === $i ? 'selected' : '' }}
                            {{ $isBreak ? 'disabled' : '' }}>
                            JP{{ $i }}{{ $isBreak ? ' (Istirahat)' : '' }}
                        </option>
                    @endfor
                </select>
            </div>
        </div>

        <div class="alert alert-info mt-3">
            <i class="fas fa-info-circle"></i>
            <small>
                Mata pelajaran otomatis terisi berdasarkan guru yang dipilih.
                Anda dapat mengubahnya jika diperlukan.
            </small>
        </div>
    </div>

    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Jadwal</button>
    </div>
</form>

<script>
$(document).ready(function() {
    const $teacherSelect = $('#teacherSelect');
    const $subjectInput = $('#subjectInput');

    $teacherSelect.on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const teacherSubject = selectedOption.data('subject');
        if (teacherSubject && teacherSubject.trim() !== '') {
            $subjectInput.val(teacherSubject);
            $subjectInput.addClass('is-valid');
            setTimeout(() => {
                $subjectInput.removeClass('is-valid');
            }, 1000);
        } else {
            $subjectInput.val('');
        }
    });

    setTimeout(function() {
        if ($teacherSelect.val()) {
            $teacherSelect.trigger('change');
        }
    }, 500);

    function syncJpEndOptions() {
        const start = parseInt($('#jpStart').val(), 10);
        $('#jpEnd option').each(function() {
            const value = parseInt($(this).val(), 10);
            const isBreak = value === 5 || value === 9;
            $(this).prop('disabled', value < start || isBreak);
        });
        const currentEnd = parseInt($('#jpEnd').val(), 10);
        if (currentEnd < start || currentEnd === 5 || currentEnd === 9) {
            $('#jpEnd').val(start);
        }
    }

    $('#jpStart').on('change', syncJpEndOptions);
    syncJpEndOptions();

    $('#scheduleForm').on('submit', function(e) {
        const requiredFields = $(this).find('[required]');
        let isValid = true;

        requiredFields.each(function() {
            if ($(this).val() === '') {
                $(this).addClass('is-invalid');
                isValid = false;
            } else {
                $(this).removeClass('is-invalid');
            }
        });

        if (!isValid) {
            e.preventDefault();
            alert('Harap lengkapi semua field yang wajib diisi!');
            return false;
        }

        $('button[type="submit"]').html('<i class="fas fa-spinner fa-spin me-2"></i>Menyimpan...').prop('disabled', true);
    });

    $subjectInput.on('input', function() {
        const currentTeacherId = $teacherSelect.val();
        const currentSubject = $(this).val();

        if (currentTeacherId) {
            const selectedOption = $teacherSelect.find('option:selected');
            const autoSubject = selectedOption.data('subject') || '';

            if (currentSubject !== autoSubject) {
                $(this).addClass('border-warning');
            } else {
                $(this).removeClass('border-warning');
            }
        }
    });
});
</script>
