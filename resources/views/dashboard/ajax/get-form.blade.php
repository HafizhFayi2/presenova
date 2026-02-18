@if ($table === 'student')
    <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">
            <i class="fas fa-{{ $isEdit ? 'edit' : 'plus-circle' }} me-2"></i>
            {{ $isEdit ? 'Edit' : 'Tambah' }} Siswa
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>

    <form method="POST" action="admin.php?table=student" id="studentForm">
        <input type="hidden" name="student_id" value="{{ $id }}">

        <div class="modal-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="studentCode" class="form-label">Kode Siswa <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="studentCode" readonly
                           value="{{ $isEdit ? '******' : 'AUTO GENERATED' }}"
                           placeholder="Dibuat otomatis oleh sistem">
                    <input type="hidden" name="student_code" id="studentCodeHidden" value="">
                    @if ($isOperator)
                        <small class="text-muted ms-1">Operator tidak dapat melihat kode siswa.</small>
                    @else
                        <small class="text-muted ms-1">Kode siswa dibuat otomatis saat simpan (format SW + kode acak).</small>
                    @endif
                </div>
                <div class="col-md-6 mb-3">
                    <label for="studentNisn" class="form-label">NISN <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="student_nisn" id="studentNisn" required
                           value="{{ (string) ($student['student_nisn'] ?? '') }}"
                           placeholder="Masukkan NISN">
                </div>
            </div>

            <div class="mb-3">
                <label for="studentName" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="student_name" id="studentName" required
                       value="{{ (string) ($student['student_name'] ?? '') }}"
                       placeholder="Masukkan nama lengkap siswa">
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="student_class_modal" class="form-label">Kelas <span class="text-danger">*</span></label>
                    <select class="form-select" name="class_id" id="student_class_modal" required>
                        <option value="">Pilih Kelas</option>
                        @foreach($classes as $class)
                            <option value="{{ (int) ($class['class_id'] ?? 0) }}"
                                data-jurusan-id="{{ (int) ($class['jurusan_id'] ?? 0) }}"
                                data-jurusan-name="{{ (string) ($class['jurusan_name'] ?? '') }}"
                                data-jurusan-code="{{ (string) ($class['jurusan_code'] ?? '') }}"
                                {{ (int) ($student['class_id'] ?? 0) === (int) ($class['class_id'] ?? 0) ? 'selected' : '' }}>
                                {{ (string) ($class['class_name'] ?? '') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="student_jurusan_display_modal" class="form-label">Jurusan <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="student_jurusan_display_modal" readonly
                           placeholder="Otomatis terisi"
                           style="background-color: var(--input-disabled-bg, #e9ecef);">
                    <input type="hidden" name="jurusan_id" id="student_jurusan_modal"
                           value="{{ (string) ($student['jurusan_id'] ?? '') }}">
                </div>
            </div>

            @if ($isEdit)
                <div class="mb-3">
                    <label for="studentPassword" class="form-label">Password Baru</label>
                    <div class="input-group">
                        <input type="password" class="form-control" name="password" id="studentPassword"
                               placeholder="Masukkan password baru">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('studentPassword')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <small class="text-muted ms-1">Kosongkan jika tidak ingin mengubah password</small>
                </div>
            @endif

            <div class="alert alert-info mt-4">
                <div class="d-flex">
                    <i class="fas fa-info-circle me-3 mt-1"></i>
                    <div>
                        <strong>Catatan:</strong>
                        <ul class="mb-0">
                            <li>Jurusan akan otomatis terisi berdasarkan kelas yang dipilih</li>
                            @if (!$isEdit)
                                <li>Password default siswa otomatis mengikuti NISN</li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="fas fa-times me-2"></i>Batal
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i>{{ $isEdit ? 'Update' : 'Simpan' }}
            </button>
        </div>
    </form>

    <script>
    const classJurusanMapModal = @json($classJurusanMap);

    function updateJurusanFromClass() {
        const classId = $('#student_class_modal').val();
        if (classId && classJurusanMapModal[classId]) {
            const jurusanData = classJurusanMapModal[classId];
            $('#student_jurusan_modal').val(jurusanData.jurusan_id);
            $('#student_jurusan_display_modal').val(jurusanData.jurusan_code + ' - ' + jurusanData.jurusan_name);
        } else {
            $('#student_jurusan_modal').val('');
            $('#student_jurusan_display_modal').val('');
        }
    }

    $(document).on('change', '#student_class_modal', updateJurusanFromClass);
    setTimeout(updateJurusanFromClass, 0);

    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
    }

    $('#studentForm').on('submit', function(e) {
        const nisn = $('#studentNisn').val();
        const name = $('#studentName').val();
        const classId = $('#student_class_modal').val();
        const jurusanId = $('#student_jurusan_modal').val();

        if (!nisn || !name || !classId || !jurusanId) {
            e.preventDefault();
            alert('Harap lengkapi semua field yang wajib diisi!');
            return false;
        }
    });
    </script>
@elseif ($table === 'teacher')
    <div class="modal-header bg-success text-white">
        <h5 class="modal-title">
            <i class="fas fa-{{ $isEdit ? 'edit' : 'plus-circle' }} me-2"></i>
            {{ $isEdit ? 'Edit' : 'Tambah' }} Guru
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>

    <form method="POST" action="admin.php?table=teacher">
        <input type="hidden" name="teacher_id" value="{{ $id }}">

        <div class="modal-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Kode Guru <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="teacher_code" required
                           value="{{ (string) ($teacher['teacher_code'] ?? '') }}"
                           placeholder="Contoh: GR001">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="teacher_name" required
                           value="{{ (string) ($teacher['teacher_name'] ?? '') }}"
                           placeholder="Masukkan nama lengkap">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="subject" required
                           value="{{ (string) ($teacher['subject'] ?? '') }}"
                           placeholder="Contoh: Matematika">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tipe Guru <span class="text-danger">*</span></label>
                    <select class="form-select" name="teacher_type" required>
                        <option value="">Pilih Tipe</option>
                        <option value="UMUM" {{ (string) ($teacher['teacher_type'] ?? '') === 'UMUM' ? 'selected' : '' }}>Umum</option>
                        <option value="KEJURUAN" {{ (string) ($teacher['teacher_type'] ?? '') === 'KEJURUAN' ? 'selected' : '' }}>Kejuruan</option>
                    </select>
                </div>
            </div>

            @if ($isEdit)
                <div class="mb-3">
                    <label class="form-label">Password Baru (Kosongkan jika tidak ingin mengubah)</label>
                    <input type="password" class="form-control" name="password"
                           placeholder="Masukkan password baru">
                </div>
            @endif

            @if (!$isEdit)
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Catatan:</strong> Username akan dibuat otomatis dari nama (lowercase, spasi diganti titik). Password default adalah "guru123".
                </div>
            @endif
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="fas fa-times me-2"></i>Batal
            </button>
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save me-2"></i>{{ $isEdit ? 'Update' : 'Simpan' }}
            </button>
        </div>
    </form>
@else
    @include('dashboard.ajax.form-not-found', ['table' => $table])
@endif
