function isPermissionError(error) {
    const name = error?.name ?? '';

    return ['NotAllowedError', 'PermissionDeniedError', 'SecurityError'].includes(name);
}

function stopMediaStream(videoElement) {
    const stream = videoElement?.srcObject;

    if (!stream || typeof stream.getTracks !== 'function') {
        return;
    }

    stream.getTracks().forEach((track) => track.stop());
    videoElement.srcObject = null;
}

function normalizeLookupValue(value) {
    if (value === null || value === undefined) {
        return '';
    }

    if (Array.isArray(value)) {
        return value
            .map((entry) => String(entry).trim())
            .filter(Boolean)
            .join(', ');
    }

    return String(value).trim();
}

function fieldHasValue(field) {
    if (!field) {
        return false;
    }

    if (field.type === 'checkbox' || field.type === 'radio') {
        return field.checked;
    }

    return String(field.value ?? '').trim() !== '';
}

export function barcodeScanner(config = {}) {
    return {
        barcode: config.barcode ?? '',
        lookupUrl: config.lookupUrl ?? '',
        lookupMinLength: config.lookupMinLength ?? 8,
        labels: {
            scan: config.labels?.scan ?? '',
            scanBarcode: config.labels?.scanBarcode ?? '',
            close: config.labels?.close ?? '',
            cancel: config.labels?.cancel ?? '',
            search: config.labels?.search ?? '',
            searching: config.labels?.searching ?? '',
            informationFound: config.labels?.informationFound ?? '',
            noResultFound: config.labels?.noResultFound ?? '',
            noSourceConfigured: config.labels?.noSourceConfigured ?? '',
            invalidBarcodeOrTitleSearch: config.labels?.invalidBarcodeOrTitleSearch ?? '',
            cameraUnavailable: config.labels?.cameraUnavailable ?? '',
            cameraPermissionDenied: config.labels?.cameraPermissionDenied ?? '',
            barcodeDetected: config.labels?.barcodeDetected ?? '',
            manualEntryAvailable: config.labels?.manualEntryAvailable ?? '',
            placeBarcodeInFrame: config.labels?.placeBarcodeInFrame ?? '',
        },
        scannerOpen: false,
        scannerBusy: false,
        scannerError: '',
        scannerMessage: '',
        scannerReader: null,
        scannerControls: null,
        scannerCloseTimer: null,
        scannerSessionId: 0,
        lookupBusy: false,
        lookupState: '',
        lookupMessage: '',
        lookupError: '',
        lookupTimer: null,
        lookupLastRequestedBarcode: '',
        scannerPendingCode: '',
        scannerPendingCount: 0,
        scannerPendingTimer: null,
        scannerBeforeUnloadHandler: null,
        scannerVisibilityHandler: null,
        init() {
            this.scannerBeforeUnloadHandler = () => {
                this.stopScanner();
            };

            this.scannerVisibilityHandler = () => {
                if (document.hidden) {
                    this.stopScanner();
                }
            };

            window.addEventListener('beforeunload', this.scannerBeforeUnloadHandler);
            document.addEventListener('visibilitychange', this.scannerVisibilityHandler);
        },
        destroy() {
            this.stopScanner();
            this.clearLookupTimer();
            this.clearScannerCandidate();

            if (this.scannerBeforeUnloadHandler) {
                window.removeEventListener('beforeunload', this.scannerBeforeUnloadHandler);
            }

            if (this.scannerVisibilityHandler) {
                document.removeEventListener('visibilitychange', this.scannerVisibilityHandler);
            }
        },
        formElement() {
            return this.$el.closest('form');
        },
        currentTypeValue() {
            return this.formElement()?.elements.namedItem('type')?.value ?? '';
        },
        currentCoverPath() {
            return this.formElement()?.elements.namedItem('cover_path')?.value ?? '';
        },
        currentFieldValue(name) {
            const field = this.formElement()?.elements.namedItem(name);

            return field ? String(field.value ?? '').trim() : '';
        },
        setFieldValue(name, value, { force = false, dispatchChange = false } = {}) {
            const field = this.formElement()?.elements.namedItem(name);

            if (!field) {
                return;
            }

            const normalizedValue = normalizeLookupValue(value);

            if (normalizedValue === '') {
                return;
            }

            if (!force && fieldHasValue(field)) {
                return;
            }

            field.value = normalizedValue;
            field.dispatchEvent(new Event('input', { bubbles: true }));

            if (dispatchChange) {
                field.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },
        setCoverPreview(path, url) {
            if (!path || !url) {
                return;
            }

            this.formElement()?.dispatchEvent(
                new CustomEvent('barcode-cover-preview', {
                    bubbles: true,
                    detail: { path, url },
                })
            );
        },
        async waitForPreviewFrame() {
            await new Promise((resolve) => {
                window.requestAnimationFrame(() => {
                    window.requestAnimationFrame(resolve);
                });
            });
        },
        clearScannerCandidate() {
            if (this.scannerPendingTimer) {
                window.clearTimeout(this.scannerPendingTimer);
                this.scannerPendingTimer = null;
            }

            this.scannerPendingCode = '';
            this.scannerPendingCount = 0;
        },
        openScanner() {
            if (this.scannerOpen) {
                return;
            }

            const sessionId = ++this.scannerSessionId;
            this.clearScannerCandidate();
            this.scannerOpen = true;
            this.scannerBusy = true;
            this.scannerError = '';
            this.scannerMessage = this.labels.placeBarcodeInFrame;

            this.waitForPreviewFrame().then(() => {
                if (!this.scannerOpen || sessionId !== this.scannerSessionId) {
                    return;
                }

                this.startScanner(sessionId);
            });
        },
        async startScanner(sessionId) {
            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                this.failScanner(new Error('camera-unavailable'));
                return;
            }

            try {
                const { BrowserMultiFormatReader } = await import('@zxing/browser');

                if (!this.scannerOpen || sessionId !== this.scannerSessionId) {
                    return;
                }

                const videoElement = this.$refs.scannerVideo;

                if (!videoElement) {
                    throw new Error('scanner-missing');
                }

                this.scannerReader = new BrowserMultiFormatReader();
                this.scannerControls = await this.scannerReader.decodeFromVideoDevice(
                    undefined,
                    videoElement,
                    (result, error, controls) => {
                        if (sessionId !== this.scannerSessionId) {
                            if (controls?.stop) {
                                controls.stop();
                            }

                            return;
                        }

                        if (controls) {
                            this.scannerControls = controls;
                        }

                        if (result) {
                            this.registerScanCandidate(result.getText());
                            return;
                        }

                        if (error && this.isFatalScannerError(error)) {
                            this.failScanner(error);
                        }
                    }
                );

                this.scannerBusy = false;
            } catch (error) {
                this.failScanner(error);
            }
        },
        isFatalScannerError(error) {
            const name = error?.name ?? '';

            return ['NotAllowedError', 'PermissionDeniedError', 'SecurityError'].includes(name);
        },
        registerScanCandidate(code) {
            const normalized = String(code ?? '').trim();

            if (normalized === '') {
                return;
            }

            if (this.scannerPendingCode === normalized) {
                this.scannerPendingCount += 1;
            } else {
                this.scannerPendingCode = normalized;
                this.scannerPendingCount = 1;
            }

            if (this.scannerPendingTimer) {
                window.clearTimeout(this.scannerPendingTimer);
            }

            this.scannerPendingTimer = window.setTimeout(() => {
                this.scannerPendingCode = '';
                this.scannerPendingCount = 0;
                this.scannerPendingTimer = null;
            }, 1200);

            if (this.scannerPendingCount < 2) {
                this.scannerMessage = this.labels.barcodeDetected;
                return;
            }

            this.handleScanResult(normalized);
        },
        handleScanResult(code) {
            const sessionAfterResult = this.scannerSessionId + 1;

            if (!code) {
                return;
            }

            this.barcode = code;
            this.scannerError = '';
            this.scannerMessage = this.labels.barcodeDetected;
            this.clearScannerCandidate();
            this.stopScanner(true, true);
            this.searchBarcode({ barcode: code, force: true, source: 'scan' });

            this.scannerCloseTimer = window.setTimeout(() => {
                if (this.scannerSessionId !== sessionAfterResult) {
                    return;
                }

                this.scannerOpen = false;
                this.scannerMessage = '';
            }, 700);
        },
        failScanner(error) {
            this.scannerBusy = false;
            this.scannerError = isPermissionError(error)
                ? this.labels.cameraPermissionDenied
                : this.labels.cameraUnavailable;
            this.scannerMessage = this.labels.manualEntryAvailable;
            this.clearScannerCandidate();
            this.stopScanner(true, true);
        },
        stopScanner(keepOpen = false, keepMessage = false) {
            this.scannerSessionId += 1;

            if (this.scannerCloseTimer) {
                window.clearTimeout(this.scannerCloseTimer);
                this.scannerCloseTimer = null;
            }

            if (this.scannerControls?.stop) {
                this.scannerControls.stop();
            }

            if (this.scannerReader?.reset) {
                this.scannerReader.reset();
            }

            stopMediaStream(this.$refs.scannerVideo);

            this.scannerControls = null;
            this.scannerReader = null;
            this.scannerBusy = false;

            if (!keepOpen) {
                this.scannerOpen = false;
            }

            if (!keepMessage) {
                this.scannerMessage = '';
            }
        },
        closeScanner() {
            this.stopScanner();
            this.clearScannerCandidate();
            this.scannerError = '';
            this.scannerMessage = '';
        },
        clearLookupTimer() {
            if (this.lookupTimer) {
                window.clearTimeout(this.lookupTimer);
                this.lookupTimer = null;
            }
        },
        queueBarcodeLookup() {
            this.lookupError = '';

            if (!this.lookupUrl) {
                this.lookupState = '';
                this.lookupMessage = '';
                return;
            }

            if (this.lookupBusy) {
                return;
            }

            const barcode = this.barcode.trim();

            if (barcode.length < Number(this.lookupMinLength ?? 0)) {
                this.lookupState = '';
                this.lookupMessage = '';
                return;
            }

            if (barcode === this.lookupLastRequestedBarcode) {
                return;
            }

            this.clearLookupTimer();
            this.lookupTimer = window.setTimeout(() => {
                this.searchBarcode({ barcode, source: 'manual' });
            }, 700);
        },
        searchBarcode({ barcode = null, force = false, source = 'manual' } = {}) {
            if (!this.lookupUrl) {
                this.lookupState = 'no_source';
                this.lookupMessage = this.labels.noSourceConfigured;
                return Promise.resolve();
            }

            const candidate = String(barcode ?? this.barcode).trim();

            if (candidate === '') {
                return Promise.resolve();
            }

            if (!/^\d+$/.test(candidate)) {
                this.lookupState = 'invalid';
                this.lookupError = this.labels.invalidBarcodeOrTitleSearch;
                this.lookupMessage = this.lookupError;
                return Promise.resolve();
            }

            if (!force && candidate.length < Number(this.lookupMinLength ?? 0)) {
                return Promise.resolve();
            }

            if (this.lookupBusy) {
                return Promise.resolve();
            }

            this.clearLookupTimer();
            this.lookupBusy = true;
            this.lookupState = 'searching';
            this.lookupMessage = this.labels.searching;
            this.lookupError = '';

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const type = this.currentTypeValue();

            return fetch(this.lookupUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    barcode: candidate,
                    type,
                    source,
                }),
            })
                .then(async (response) => ({
                    response,
                    payload: await response.json().catch(() => ({})),
                }))
                .then(({ response, payload }) => {
                    this.lookupBusy = false;
                    this.lookupLastRequestedBarcode = candidate;

                    if (response.status === 422) {
                        this.lookupState = 'invalid';
                        this.lookupError = payload?.message ?? this.labels.noResultFound;
                        this.lookupMessage = this.lookupError;
                        return;
                    }

                    if (!response.ok) {
                        this.lookupState = 'error';
                        this.lookupError = payload?.message ?? this.labels.noResultFound;
                        this.lookupMessage = this.lookupError;
                        return;
                    }

                    this.lookupState = payload?.status ?? 'not_found';
                    this.lookupMessage = payload?.message
                        ?? (
                            this.lookupState === 'found'
                                ? this.labels.informationFound
                                : this.lookupState === 'no_source'
                                    ? this.labels.noSourceConfigured
                                    : this.labels.noResultFound
                        );

                    if (this.lookupState === 'found') {
                        this.applyLookupResult(payload?.data ?? {});
                    }
                })
                .catch(() => {
                    this.lookupBusy = false;
                    this.lookupState = 'error';
                    this.lookupError = this.labels.noResultFound;
                    this.lookupMessage = this.lookupError;
                });
        },
        applyLookupResult(data) {
            if (!data || typeof data !== 'object') {
                return;
            }

            const typeChanged = Boolean(data.type && !this.currentFieldValue('type'));

            if (data.type && !this.currentFieldValue('type')) {
                this.setFieldValue('type', data.type, { dispatchChange: true, force: true });
            }

            this.setFieldValue('title', data.title);
            this.setFieldValue('original_title', data.original_title);
            this.setFieldValue('release_year', data.release_year);
            this.setFieldValue('description', data.description);
            this.setFieldValue('barcode', data.barcode ?? this.barcode);
            if (data.physical_format) {
                const applyPhysicalFormat = () => {
                    this.setFieldValue('physical_format', data.physical_format);
                };

                if (typeChanged) {
                    window.requestAnimationFrame(() => {
                        window.requestAnimationFrame(applyPhysicalFormat);
                    });
                } else {
                    applyPhysicalFormat();
                }
            }

            this.setFieldValue('edition', data.edition);
            this.setFieldValue('region', data.region);
            this.setFieldValue('location', data.location);
            this.setFieldValue('runtime_minutes', data.runtime_minutes);
            this.setFieldValue('director', data.director);
            this.setFieldValue('studio', data.studio);
            this.setFieldValue('age_rating', data.age_rating);
            this.setFieldValue('genres', data.genres);
            this.setFieldValue('cast_members', data.cast_members);
            this.setFieldValue('platform', data.platform);
            this.setFieldValue('developer', data.developer);
            this.setFieldValue('publisher', data.publisher);
            this.setFieldValue('modes', data.modes);
            this.setFieldValue('min_players', data.min_players);
            this.setFieldValue('max_players', data.max_players);
            this.setFieldValue('play_time_minutes', data.play_time_minutes);
            this.setFieldValue('designer', data.designer);

            if (data.cover_path && data.cover_url && !this.currentCoverPath()) {
                this.setFieldValue('cover_path', data.cover_path, { force: true });
                this.setCoverPreview(data.cover_path, data.cover_url);
            }
        },
    };
}
