(function(window, document) {
    'use strict';

    if (window.SchedulePrintDialog) {
        return;
    }

    let refs = null;

    function showAlert(message) {
        if (window.AppDialog && typeof window.AppDialog.alert === 'function') {
            window.AppDialog.alert(message, { title: 'Perhatian' });
            return;
        }
        window.alert(message);
    }

    function normalizeUrl(url) {
        return String(url || '').trim();
    }

    function openWindowSafely(targetUrl) {
        const popup = window.open('', '_blank');
        if (!popup) {
            return null;
        }

        try {
            popup.opener = null;
        } catch (error) {
            // Ignore cross-browser opener protection issues.
        }

        try {
            popup.location.replace(targetUrl);
        } catch (error) {
            try {
                popup.location.href = targetUrl;
            } catch (ignored) {
                // If location assignment fails, caller fallback will handle it.
            }
        }

        return popup;
    }

    function tryOpen(url, options) {
        const opts = options || {};
        const targetUrl = normalizeUrl(url);
        if (!targetUrl) {
            return false;
        }

        const popup = openWindowSafely(targetUrl);
        if (popup) {
            return true;
        }

        if (opts.fallback === 'same-tab') {
            window.location.assign(targetUrl);
            return true;
        }

        if (!opts.silentBlocked) {
            showAlert('Popup diblokir. Izinkan popup untuk melanjutkan print atau download PDF.');
        }
        return false;
    }

    function hideModal() {
        if (!refs) {
            return;
        }
        if (refs.bsModal) {
            refs.bsModal.hide();
            return;
        }
        refs.modalEl.classList.remove('show');
        refs.modalEl.style.display = 'none';
        refs.current = null;
    }

    function ensureModal() {
        if (refs) {
            return refs;
        }

        const wrapper = document.createElement('div');
        wrapper.innerHTML =
            '<div class="modal fade" id="schedulePrintDialogModal" tabindex="-1" aria-hidden="true">' +
                '<div class="modal-dialog modal-dialog-centered">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header">' +
                            '<h5 class="modal-title js-spd-title">Pilih Output Jadwal</h5>' +
                            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>' +
                        '</div>' +
                        '<div class="modal-body">' +
                            '<p class="mb-0 js-spd-message">Pilih metode output jadwal yang Anda inginkan.</p>' +
                        '</div>' +
                        '<div class="modal-footer d-flex flex-wrap gap-2 justify-content-end">' +
                            '<button type="button" class="btn btn-outline-secondary js-spd-cancel" data-bs-dismiss="modal">Batal</button>' +
                            '<button type="button" class="btn btn-primary js-spd-print">' +
                                '<i class="fas fa-print me-1"></i>Print' +
                            '</button>' +
                            '<button type="button" class="btn btn-success js-spd-pdf">' +
                                '<i class="fas fa-file-pdf me-1"></i>Download PDF' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';

        const modalEl = wrapper.firstElementChild;
        document.body.appendChild(modalEl);

        const bsModal = (window.bootstrap && window.bootstrap.Modal)
            ? new window.bootstrap.Modal(modalEl, { backdrop: true, keyboard: true })
            : null;

        refs = {
            modalEl: modalEl,
            bsModal: bsModal,
            titleEl: modalEl.querySelector('.js-spd-title'),
            messageEl: modalEl.querySelector('.js-spd-message'),
            printBtn: modalEl.querySelector('.js-spd-print'),
            pdfBtn: modalEl.querySelector('.js-spd-pdf'),
            current: null
        };

        refs.printBtn.addEventListener('click', function() {
            if (!refs || !refs.current) {
                return;
            }
            tryOpen(refs.current.printUrl, { fallback: 'same-tab', silentBlocked: true });
            hideModal();
        });

        refs.pdfBtn.addEventListener('click', function() {
            if (!refs || !refs.current) {
                return;
            }
            tryOpen(refs.current.pdfUrl, { fallback: 'same-tab', silentBlocked: true });
            hideModal();
        });

        refs.modalEl.addEventListener('hidden.bs.modal', function() {
            if (refs) {
                refs.current = null;
            }
        });

        return refs;
    }

    function openWithFallback(title, message, printUrl, pdfUrl) {
        const promptMessage = (message || 'Pilih output jadwal.')
            + '\n\nTekan OK untuk Download PDF, atau Cancel untuk Print.';
        const choosePdf = window.confirm(promptMessage);
        if (choosePdf) {
            tryOpen(pdfUrl, { fallback: 'same-tab', silentBlocked: true });
        } else {
            tryOpen(printUrl, { fallback: 'same-tab', silentBlocked: true });
        }
    }

    window.SchedulePrintDialog = {
        open: function(options) {
            const opts = options || {};
            const printUrl = normalizeUrl(opts.printUrl || opts.url);
            const pdfUrl = normalizeUrl(opts.pdfUrl);

            if (!printUrl || !pdfUrl) {
                showAlert('URL print atau PDF belum tersedia.');
                return false;
            }

            const title = String(opts.title || 'Pilih Output Jadwal');
            const message = String(opts.message || 'Ingin langsung cetak atau download PDF?');

            const ui = ensureModal();
            if (!ui.bsModal) {
                openWithFallback(title, message, printUrl, pdfUrl);
                return true;
            }

            ui.titleEl.textContent = title;
            ui.messageEl.textContent = message;
            ui.current = {
                printUrl: printUrl,
                pdfUrl: pdfUrl
            };

            ui.bsModal.show();
            return true;
        }
    };
})(window, document);
