(function(window, document) {
    'use strict';

    if (window.AppDialog) {
        return;
    }

    const nativeAlert = typeof window.alert === 'function' ? window.alert.bind(window) : function() {};
    const queue = [];
    let running = false;
    let refs = null;
    let currentRequest = null;

    function buildModal() {
        if (refs) {
            return refs;
        }

        const wrapper = document.createElement('div');
        wrapper.innerHTML =
            '<div class="modal fade app-dialog-modal" id="appDialogModal" tabindex="-1" aria-hidden="true">' +
                '<div class="modal-dialog modal-dialog-centered app-dialog-dialog">' +
                    '<div class="modal-content app-dialog-content">' +
                        '<div class="modal-header app-dialog-header">' +
                            '<h5 class="modal-title app-dialog-title">Konfirmasi</h5>' +
                            '<button type="button" class="btn-close app-dialog-close" data-bs-dismiss="modal" aria-label="Tutup"></button>' +
                        '</div>' +
                        '<div class="modal-body app-dialog-body">' +
                            '<p class="app-dialog-message mb-0"></p>' +
                            '<div class="app-dialog-input-wrap d-none mt-3">' +
                                '<input type="text" class="form-control app-dialog-input" autocomplete="off">' +
                                '<div class="form-text app-dialog-hint mt-2"></div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="modal-footer app-dialog-footer">' +
                            '<button type="button" class="btn btn-outline-secondary app-dialog-cancel">Batal</button>' +
                            '<button type="button" class="btn btn-primary app-dialog-ok">OK</button>' +
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
            titleEl: modalEl.querySelector('.app-dialog-title'),
            messageEl: modalEl.querySelector('.app-dialog-message'),
            inputWrapEl: modalEl.querySelector('.app-dialog-input-wrap'),
            inputEl: modalEl.querySelector('.app-dialog-input'),
            hintEl: modalEl.querySelector('.app-dialog-hint'),
            closeBtn: modalEl.querySelector('.app-dialog-close'),
            cancelBtn: modalEl.querySelector('.app-dialog-cancel'),
            okBtn: modalEl.querySelector('.app-dialog-ok')
        };

        refs.okBtn.addEventListener('click', function() {
            if (!currentRequest) {
                return;
            }
            if (currentRequest.mode === 'prompt') {
                settleCurrent(refs.inputEl.value);
            } else {
                settleCurrent(true);
            }
            hideModal();
        });

        refs.cancelBtn.addEventListener('click', function() {
            cancelCurrent();
            hideModal();
        });

        refs.closeBtn.addEventListener('click', function() {
            cancelCurrent();
        });

        refs.modalEl.addEventListener('hidden.bs.modal', function() {
            cancelCurrent();
            runQueue();
        });

        return refs;
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
        refs.modalEl.setAttribute('aria-hidden', 'true');
        runQueue();
    }

    function settleCurrent(value) {
        if (!currentRequest || currentRequest.done) {
            return;
        }
        currentRequest.done = true;
        currentRequest.resolve(value);
        currentRequest = null;
    }

    function cancelCurrent() {
        if (!currentRequest || currentRequest.done) {
            return;
        }
        let fallbackValue = true;
        if (currentRequest.mode === 'confirm') {
            fallbackValue = false;
        } else if (currentRequest.mode === 'prompt') {
            fallbackValue = null;
        }
        settleCurrent(fallbackValue);
    }

    function openDialog(config) {
        const ui = buildModal();
        if (!ui.bsModal) {
            if (config.mode === 'alert') {
                nativeAlert(config.message);
                return Promise.resolve(true);
            }
            if (config.mode === 'confirm') {
                return Promise.resolve(window.confirm(config.message));
            }
            return Promise.resolve(window.prompt(config.message, config.defaultValue || '') || null);
        }

        ui.titleEl.textContent = config.title || 'Konfirmasi';
        ui.messageEl.textContent = config.message || '';
        ui.okBtn.textContent = config.okText || 'OK';
        ui.cancelBtn.textContent = config.cancelText || 'Batal';

        const showCancel = config.mode === 'confirm' || config.mode === 'prompt';
        ui.cancelBtn.classList.toggle('d-none', !showCancel);

        const showInput = config.mode === 'prompt';
        ui.inputWrapEl.classList.toggle('d-none', !showInput);
        ui.inputEl.type = config.inputType || 'text';
        ui.inputEl.placeholder = config.placeholder || '';
        ui.inputEl.value = config.defaultValue || '';
        ui.hintEl.textContent = config.hint || '';
        ui.hintEl.classList.toggle('d-none', !config.hint);

        return new Promise(function(resolve) {
            currentRequest = {
                mode: config.mode,
                resolve: resolve,
                done: false
            };

            ui.bsModal.show();

            if (showInput) {
                window.setTimeout(function() {
                    ui.inputEl.focus();
                    ui.inputEl.select();
                }, 180);
            } else {
                window.setTimeout(function() {
                    ui.okBtn.focus();
                }, 120);
            }
        });
    }

    function enqueue(config) {
        return new Promise(function(resolve) {
            queue.push({
                config: config,
                resolve: resolve
            });
            runQueue();
        });
    }

    function runQueue() {
        if (running || queue.length === 0) {
            return;
        }
        running = true;

        const item = queue.shift();
        openDialog(item.config)
            .then(function(result) {
                item.resolve(result);
            })
            .finally(function() {
                running = false;
                if (!refs || !refs.modalEl.classList.contains('show')) {
                    runQueue();
                }
            });
    }

    function proceedElementAction(element) {
        if (!element) {
            return;
        }

        const tagName = (element.tagName || '').toLowerCase();
        if ((tagName === 'a' || tagName === 'area') && element.href) {
            window.location.href = element.href;
            return;
        }

        const form = element.form || (typeof element.closest === 'function' ? element.closest('form') : null);
        if (form) {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
            return;
        }

        const href = element.getAttribute ? element.getAttribute('data-href') : '';
        if (href) {
            window.location.href = href;
        }
    }

    const api = {
        alert: function(message, options) {
            const opts = options || {};
            return enqueue({
                mode: 'alert',
                title: opts.title || 'Informasi',
                message: String(message || ''),
                okText: opts.okText || 'OK'
            });
        },

        confirm: function(message, options) {
            const opts = options || {};
            return enqueue({
                mode: 'confirm',
                title: opts.title || 'Konfirmasi',
                message: String(message || ''),
                okText: opts.okText || 'OK',
                cancelText: opts.cancelText || 'Batal'
            });
        },

        prompt: function(message, options) {
            const opts = options || {};
            return enqueue({
                mode: 'prompt',
                title: opts.title || 'Input Diperlukan',
                message: String(message || ''),
                okText: opts.okText || 'Lanjut',
                cancelText: opts.cancelText || 'Batal',
                placeholder: opts.placeholder || '',
                defaultValue: opts.defaultValue || '',
                inputType: opts.inputType || 'text',
                hint: opts.hint || ''
            });
        },

        inlineConfirm: function(element, message, options) {
            api.confirm(message, options).then(function(confirmed) {
                if (confirmed) {
                    proceedElementAction(element);
                }
            });
            return false;
        }
    };

    window.__nativeAlert = nativeAlert;
    window.alert = function(message) {
        api.alert(message);
    };
    window.AppDialog = api;
})(window, document);
