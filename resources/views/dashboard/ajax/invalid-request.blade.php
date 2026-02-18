<div class="modal-header bg-danger text-white">
    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Error</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i>
        {{ $message ?? 'Akses tidak valid. Form harus diakses melalui metode POST.' }}
    </div>
</div>
