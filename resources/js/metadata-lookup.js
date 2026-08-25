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

function importedMetadataPayload(payload) {
    if (!payload || typeof payload !== 'object') {
        return {};
    }

    if (payload.data && typeof payload.data === 'object' && !Array.isArray(payload.data)) {
        return payload.data;
    }

    return payload;
}

function statePropertyForField(name) {
    return {
        original_title: 'originalTitle',
        release_year: 'releaseYear',
        end_year: 'endYear',
        physical_format: 'physicalFormat',
        cover_path: 'coverPath',
    }[name] ?? name;
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

function namedFieldElements(form, name) {
    if (!form) {
        return [];
    }

    const selectorName = String(name).replace(/["\\]/g, '\\$&');
    const fields = Array.from(form.querySelectorAll(`[name="${selectorName}"]`));

    if (fields.length > 0) {
        return fields;
    }

    const field = form.elements.namedItem(name);

    return field ? [field] : [];
}

function jsonHeaders(csrfToken = '') {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
    };
}

function metadataCandidateKey(candidate) {
    if (!candidate || typeof candidate !== 'object') {
        return '';
    }

    const source = candidate.source ?? '';
    const id = candidate.tmdb_id ?? candidate.igdb_id ?? candidate.bgg_id ?? candidate.barcode ?? candidate.id ?? '';

    if (id !== '') {
        return `${source}:${id}`;
    }

    return [source, candidate.type ?? '', candidate.title ?? '', candidate.release_year ?? '', candidate.poster_url ?? '']
        .map((value) => String(value).trim().toLowerCase())
        .join(':');
}

export function metadataLookup(config = {}) {
    return {
        type: config.type ?? '',
        title: config.title ?? '',
        originalTitle: config.originalTitle ?? '',
        releaseYear: config.releaseYear ?? '',
        endYear: config.endYear ?? '',
        barcode: config.barcode ?? '',
        physicalFormat: config.physicalFormat ?? '',
        coverPath: config.coverPath ?? '',
        coverPreviewUrl: config.coverPreviewUrl ?? '',
        localCoverPreviewUrl: '',
        removeCover: Boolean(config.removeCover ?? false),
        typeLabels: config.typeLabels ?? {},
        formatOptions: config.formatOptions ?? {},
        titleSearchUrl: config.titleSearchUrl ?? '',
        barcodeLookupUrl: config.barcodeLookupUrl ?? '',
        importUrl: config.importUrl ?? '',
        hasValidationErrors: config.hasValidationErrors ?? false,
        labels: {
            search: config.labels?.search ?? '',
            choose: config.labels?.choose ?? '',
            searching: config.labels?.searching ?? '',
            resultsFound: config.labels?.resultsFound ?? '',
            noResultFound: config.labels?.noResultFound ?? '',
            tmdbNotConfigured: config.labels?.tmdbNotConfigured ?? '',
            barcodeSourceUnavailable: config.labels?.barcodeSourceUnavailable ?? '',
            chooseTypeBeforeSearching: config.labels?.chooseTypeBeforeSearching ?? '',
            enterTitleToSearch: config.labels?.enterTitleToSearch ?? '',
            automaticSearchNotAvailableForThisType: config.labels?.automaticSearchNotAvailableForThisType ?? '',
            enterBarcodeToSearch: config.labels?.enterBarcodeToSearch ?? '',
            enterValidBarcode: config.labels?.enterValidBarcode ?? '',
            searchError: config.labels?.searchError ?? '',
            metadataImported: config.labels?.metadataImported ?? '',
            coverImported: config.labels?.coverImported ?? '',
            coverNotImported: config.labels?.coverNotImported ?? '',
            posterImportFailed: config.labels?.posterImportFailed ?? '',
            cameraUnavailable: config.labels?.cameraUnavailable ?? '',
            cameraPermissionDenied: config.labels?.cameraPermissionDenied ?? '',
            barcodeDetected: config.labels?.barcodeDetected ?? '',
            manualEntryAvailable: config.labels?.manualEntryAvailable ?? '',
            placeBarcodeInFrame: config.labels?.placeBarcodeInFrame ?? '',
            minutes: config.labels?.minutes ?? '',
            scan: config.labels?.scan ?? '',
            scanBarcode: config.labels?.scanBarcode ?? '',
            close: config.labels?.close ?? '',
            cancel: config.labels?.cancel ?? '',
            loadMore: config.labels?.loadMore ?? '',
            allResultsShown: config.labels?.allResultsShown ?? '',
            coverRemoved: config.labels?.coverRemoved ?? '',
        },
        typeMeta: {
            '': { label: config.typeLabels?.none ?? '', chip: 'slate', rgb: '148 163 184' },
            film: { label: config.typeLabels?.film ?? '', chip: 'amber', rgb: '245 158 11' },
            tv_series: { label: config.typeLabels?.tv_series ?? '', chip: 'sky', rgb: '14 165 233' },
            video_game: { label: config.typeLabels?.video_game ?? '', chip: 'emerald', rgb: '20 184 166' },
            board_game: { label: config.typeLabels?.board_game ?? '', chip: 'violet', rgb: '167 139 250' },
        },
        titleBusy: false,
        titleMessage: '',
        titleError: '',
        barcodeBusy: false,
        barcodeMessage: '',
        barcodeError: '',
        resultsOpen: false,
        resultsScope: '',
        resultsSource: '',
        resultsCandidates: [],
        resultsMessage: '',
        resultsPagination: { currentPage: 1, hasMore: false, nextPage: null, perPage: null },
        resultsLoadMoreBusy: false,
        resultsAllShown: false,
        resultsSearchParams: null,
        resultsScrollY: 0,
        resultsBodyStyles: null,
        importBusy: false,
        importNotice: '',
        activeCandidateId: null,
        lastTitleQuery: '',
        lastBarcodeQuery: '',
        scannerOpen: false,
        scannerBusy: false,
        scannerError: '',
        scannerMessage: '',
        scannerReader: null,
        scannerControls: null,
        scannerCloseTimer: null,
        scannerSessionId: 0,
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

            if (!this.hasValidationErrors) {
                this.syncPhysicalFormat();
            }
        },
        destroy() {
            this.stopScanner();
            this.closeResults();
            this.revokeLocalCoverPreview();

            if (this.scannerBeforeUnloadHandler) {
                window.removeEventListener('beforeunload', this.scannerBeforeUnloadHandler);
            }

            if (this.scannerVisibilityHandler) {
                document.removeEventListener('visibilitychange', this.scannerVisibilityHandler);
            }
        },
        formElement() {
            return this.$root?.querySelector('form')
                ?? this.$el?.closest('form')
                ?? this.$el?.querySelector('form')
                ?? globalThis.document?.getElementById?.('collection-form')
                ?? null;
        },
        currentTypeValue() {
            return String(this.type ?? '').trim();
        },
        get currentMeta() {
            return this.typeMeta[this.currentTypeValue()] ?? this.typeMeta[''];
        },
        get currentLabel() {
            return this.currentMeta.label;
        },
        get currentChip() {
            return this.currentMeta.chip;
        },
        get currentAccent() {
            return this.currentMeta.rgb;
        },
        get currentPhysicalFormatLabel() {
            return this.formatOptions?.[this.currentTypeValue()]?.[this.physicalFormat] ?? this.physicalFormat;
        },
        revokeLocalCoverPreview() {
            if (this.localCoverPreviewUrl && typeof URL !== 'undefined' && typeof URL.revokeObjectURL === 'function') {
                URL.revokeObjectURL(this.localCoverPreviewUrl);
            }

            this.localCoverPreviewUrl = '';
        },
        previewCoverImage(event) {
            const file = event?.target?.files?.[0] ?? null;

            this.revokeLocalCoverPreview();

            if (!file || !String(file.type ?? '').startsWith('image/')) {
                return;
            }

            this.removeCover = false;

            if (typeof URL !== 'undefined' && typeof URL.createObjectURL === 'function') {
                this.localCoverPreviewUrl = URL.createObjectURL(file);
                this.coverPreviewUrl = this.localCoverPreviewUrl;
            }
        },
        clearCoverInput() {
            namedFieldElements(this.formElement(), 'cover_image').forEach((field) => {
                if (field.type === 'file') {
                    field.value = '';
                }
            });
        },
        removeCoverImage() {
            this.revokeLocalCoverPreview();
            this.coverPath = '';
            this.coverPreviewUrl = '';
            this.removeCover = true;
            this.importNotice = this.labels.coverRemoved;
            this.clearCoverInput();
        },
        currentCoverPath() {
            return String(this.coverPath ?? '').trim();
        },
        currentFieldValue(name) {
            if (Object.prototype.hasOwnProperty.call(this, name) && typeof this[name] !== 'function') {
                return String(this[name] ?? '').trim();
            }

            const field = namedFieldElements(this.formElement(), name)[0] ?? null;

            return field ? String(field.value ?? '').trim() : '';
        },
        setFieldValue(name, value, { force = false, dispatchChange = false } = {}) {
            const fields = namedFieldElements(this.formElement(), name);

            if (fields.length === 0) {
                return;
            }

            const normalizedValue = normalizeLookupValue(value);

            if (normalizedValue === '') {
                return;
            }

            fields.forEach((field) => {
                if (!force && fieldHasValue(field)) {
                    return;
                }

                field.value = normalizedValue;

                field.dispatchEvent(new Event('input', { bubbles: true }));

                if (dispatchChange) {
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            const stateProperty = statePropertyForField(name);

            if (Object.prototype.hasOwnProperty.call(this, stateProperty)) {
                this[stateProperty] = normalizedValue;
            }
        },
        clearTitleState() {
            this.titleBusy = false;
            this.titleMessage = '';
            this.titleError = '';
            this.importNotice = '';
        },
        clearBarcodeState() {
            this.barcodeBusy = false;
            this.barcodeMessage = '';
            this.barcodeError = '';
        },
        closeResults() {
            this.unlockResultsScroll();
            this.resultsOpen = false;
            this.resultsScope = '';
            this.resultsSource = '';
            this.resultsCandidates = [];
            this.resultsMessage = '';
            this.resultsPagination = { currentPage: 1, hasMore: false, nextPage: null, perPage: null };
            this.resultsLoadMoreBusy = false;
            this.resultsAllShown = false;
            this.resultsSearchParams = null;
            this.activeCandidateId = null;
        },
        candidateKey(candidate) {
            return metadataCandidateKey(candidate);
        },
        uniqueCandidates(candidates) {
            const seen = new Set();

            return candidates.filter((candidate) => {
                const key = this.candidateKey(candidate);

                if (key === '' || seen.has(key)) {
                    return false;
                }

                seen.add(key);
                return true;
            });
        },
        normalizePagination(pagination = {}) {
            const nextPage = Number(pagination?.next_page ?? 0);

            return {
                currentPage: Number(pagination?.current_page ?? 1) || 1,
                hasMore: Boolean(pagination?.has_more),
                nextPage: nextPage > 0 ? nextPage : null,
                perPage: Number(pagination?.per_page ?? 0) || null,
            };
        },
        openResults(scope, source, candidates, message, pagination = {}, { append = false } = {}) {
            const incomingCandidates = Array.isArray(candidates) ? candidates : [];
            this.resultsScope = scope;
            this.resultsSource = source;
            this.resultsCandidates = append
                ? this.uniqueCandidates([...this.resultsCandidates, ...incomingCandidates])
                : this.uniqueCandidates(incomingCandidates);
            this.resultsMessage = message;
            this.resultsPagination = this.normalizePagination(pagination);
            this.resultsAllShown = this.resultsCandidates.length > 0 && !this.resultsPagination.hasMore;
            this.resultsOpen = this.resultsCandidates.length > 0;

            if (this.resultsOpen) {
                this.lockResultsScroll();

                if (!append) {
                    this.$nextTick?.(() => {
                        this.$refs?.resultsClose?.focus?.();
                    });
                }
            }
        },
        lockResultsScroll() {
            if (typeof document === 'undefined' || typeof window === 'undefined' || this.resultsBodyStyles !== null) {
                return;
            }

            const body = document.body;

            if (!body?.style) {
                return;
            }

            this.resultsScrollY = window.scrollY || window.pageYOffset || 0;
            this.resultsBodyStyles = {
                position: body.style.position,
                top: body.style.top,
                left: body.style.left,
                right: body.style.right,
                width: body.style.width,
                overflow: body.style.overflow,
            };

            body.style.position = 'fixed';
            body.style.top = `-${this.resultsScrollY}px`;
            body.style.left = '0';
            body.style.right = '0';
            body.style.width = '100%';
            body.style.overflow = 'hidden';
        },
        unlockResultsScroll() {
            if (typeof document === 'undefined' || typeof window === 'undefined' || this.resultsBodyStyles === null) {
                return;
            }

            const body = document.body;

            if (!body?.style) {
                this.resultsBodyStyles = null;
                return;
            }

            body.style.position = this.resultsBodyStyles.position;
            body.style.top = this.resultsBodyStyles.top;
            body.style.left = this.resultsBodyStyles.left;
            body.style.right = this.resultsBodyStyles.right;
            body.style.width = this.resultsBodyStyles.width;
            body.style.overflow = this.resultsBodyStyles.overflow;

            window.scrollTo?.(0, this.resultsScrollY);
            this.resultsBodyStyles = null;
            this.resultsScrollY = 0;
        },
        clearResultsFor(scope) {
            if (this.resultsScope === scope) {
                this.closeResults();
            }
        },
        syncPhysicalFormat() {
            const options = this.formatOptions[this.currentTypeValue()] ?? {};

            if (!Object.prototype.hasOwnProperty.call(options, this.physicalFormat)) {
                this.physicalFormat = '';
                const field = this.formElement()?.elements.namedItem('physical_format');

                if (field) {
                    field.value = '';
                }
            }
        },
        async searchTitle() {
            this.clearTitleState();
            this.closeResults();

            const type = this.currentTypeValue();
            const title = String(this.title ?? '').trim();
            const releaseYear = String(this.releaseYear ?? '').trim();
            const normalizedReleaseYear = releaseYear === '' ? null : Number(releaseYear);
            this.lastTitleQuery = title;

            if (type === '') {
                this.titleMessage = this.labels.chooseTypeBeforeSearching;
                return;
            }

            if (title === '') {
                this.titleMessage = this.labels.enterTitleToSearch;
                return;
            }

            if (!['film', 'tv_series', 'video_game', 'board_game'].includes(type)) {
                this.titleMessage = this.labels.automaticSearchNotAvailableForThisType;
                return;
            }

            if (!this.titleSearchUrl) {
                this.titleMessage = this.labels.tmdbNotConfigured;
                return;
            }

            this.titleBusy = true;
            this.titleMessage = this.labels.searching;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

            try {
                const response = await fetch(this.titleSearchUrl, {
                    method: 'POST',
                    headers: jsonHeaders(csrfToken),
                    body: JSON.stringify({
                        mode: 'title',
                        type,
                        title,
                        release_year: normalizedReleaseYear,
                        page: 1,
                    }),
                });

                const payload = await response.json().catch(() => ({}));
                this.titleBusy = false;

                if (response.status === 422) {
                    this.titleMessage = payload?.message ?? this.labels.searchError;
                    return;
                }

                if (!response.ok) {
                    this.titleMessage = payload?.message ?? this.labels.searchError;
                    return;
                }

                const candidates = Array.isArray(payload?.data?.candidates) ? payload.data.candidates : [];
                const message = payload?.message
                    ?? (candidates.length > 0 ? this.labels.resultsFound : this.labels.noResultFound);
                const pagination = payload?.data?.pagination ?? {};

                this.resultsSearchParams = { type, title, releaseYear: normalizedReleaseYear };
                this.titleMessage = message;
                this.openResults('title', payload?.source ?? (type === 'video_game' ? 'igdb' : 'tmdb'), candidates, message, pagination);
            } catch (error) {
                this.titleBusy = false;
                this.titleMessage = this.labels.searchError;
            }
        },
        async loadMoreResults() {
            if (this.resultsScope !== 'title' || this.resultsLoadMoreBusy || !this.resultsPagination.hasMore || !this.resultsPagination.nextPage || !this.resultsSearchParams) {
                return;
            }

            this.resultsLoadMoreBusy = true;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

            try {
                const response = await fetch(this.titleSearchUrl, {
                    method: 'POST',
                    headers: jsonHeaders(csrfToken),
                    body: JSON.stringify({
                        mode: 'title',
                        type: this.resultsSearchParams.type,
                        title: this.resultsSearchParams.title,
                        release_year: this.resultsSearchParams.releaseYear,
                        page: this.resultsPagination.nextPage,
                    }),
                });

                const payload = await response.json().catch(() => ({}));
                this.resultsLoadMoreBusy = false;

                if (!response.ok) {
                    this.titleMessage = payload?.message ?? this.labels.searchError;
                    return;
                }

                const candidates = Array.isArray(payload?.data?.candidates) ? payload.data.candidates : [];
                const pagination = payload?.data?.pagination ?? {};
                const message = payload?.message ?? this.resultsMessage ?? this.labels.resultsFound;

                this.titleMessage = message;
                this.openResults('title', payload?.source ?? this.resultsSource, candidates, this.resultsMessage || message, pagination, { append: true });
            } catch (error) {
                this.resultsLoadMoreBusy = false;
                this.titleMessage = this.labels.searchError;
            }
        },
        async searchBarcode({ barcode = null, force = false, source = 'manual' } = {}) {
            this.clearBarcodeState();
            this.closeResults();

            const candidate = String(barcode ?? this.barcode).trim();
            this.lastBarcodeQuery = candidate;

            if (candidate === '') {
                this.barcodeMessage = this.labels.enterBarcodeToSearch;
                return;
            }

            if (!/^\d+$/.test(candidate)) {
                this.barcodeMessage = this.labels.enterValidBarcode;
                return;
            }

            if (!this.barcodeLookupUrl) {
                this.barcodeMessage = this.labels.barcodeSourceUnavailable;
                return;
            }

            if (!force && candidate.length < 8) {
                this.barcodeMessage = this.labels.enterValidBarcode;
                return;
            }

            this.barcodeBusy = true;
            this.barcodeMessage = this.labels.searching;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

            try {
                const response = await fetch(this.barcodeLookupUrl, {
                    method: 'POST',
                    headers: jsonHeaders(csrfToken),
                    body: JSON.stringify({
                        mode: 'barcode',
                        barcode: candidate,
                        type: this.currentTypeValue(),
                        source,
                    }),
                });

                const payload = await response.json().catch(() => ({}));
                this.barcodeBusy = false;

                if (response.status === 422) {
                    this.barcodeMessage = payload?.message ?? this.labels.searchError;
                    return;
                }

                if (!response.ok) {
                    this.barcodeMessage = payload?.message ?? this.labels.searchError;
                    return;
                }

                const candidates = Array.isArray(payload?.data?.candidates) ? payload.data.candidates : [];
                const message = payload?.message
                    ?? (candidates.length > 0 ? this.labels.resultsFound : this.labels.noResultFound);

                this.barcodeMessage = message;

                if (candidates.length > 0) {
                    this.openResults('barcode', payload?.source ?? 'barcode', candidates, message);
                }
            } catch (error) {
                this.barcodeBusy = false;
                this.barcodeMessage = this.labels.searchError;
            }
        },
        chooseCandidate(candidate) {
            if (!candidate || typeof candidate !== 'object') {
                return;
            }

            const candidateId = candidate.tmdb_id ?? candidate.igdb_id ?? candidate.bgg_id ?? candidate.barcode ?? candidate.id ?? null;
            this.activeCandidateId = candidateId;
            this.importCandidate(candidate);
        },
        async importCandidate(candidate) {
            const source = candidate?.source ?? this.resultsSource ?? 'tmdb';
            const scope = this.resultsScope;

            if (!this.importUrl) {
                const message = source === 'barcode' ? this.labels.barcodeSourceUnavailable : this.labels.tmdbNotConfigured;
                if (scope === 'barcode') {
                    this.barcodeMessage = message;
                } else {
                    this.titleMessage = message;
                }
                return;
            }

            const payload = source === 'barcode'
                ? {
                    source: 'barcode',
                    barcode: candidate?.barcode ?? this.currentFieldValue('barcode'),
                    type: this.currentTypeValue(),
                }
                : source === 'igdb'
                    ? {
                    source: 'igdb',
                    igdb_id: candidate?.igdb_id ?? candidate?.id ?? null,
                }
                : source === 'bgg'
                    ? {
                        source: 'bgg',
                        bgg_id: candidate?.bgg_id ?? candidate?.id ?? null,
                    }
                    : {
                    source: 'tmdb',
                    tmdb_id: candidate?.tmdb_id ?? candidate?.id ?? null,
                    type: candidate?.type ?? this.currentTypeValue(),
                };

            if (!payload.tmdb_id && !payload.igdb_id && !payload.bgg_id && !payload.barcode) {
                return;
            }

            this.importBusy = true;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

            try {
                const response = await fetch(this.importUrl, {
                    method: 'POST',
                    headers: jsonHeaders(csrfToken),
                    body: JSON.stringify(payload),
                });

                const payloadJson = await response.json().catch(() => ({}));
                this.importBusy = false;

                if (!response.ok) {
                    const message = payloadJson?.message ?? this.labels.searchError;
                    if (scope === 'barcode') {
                        this.barcodeMessage = message;
                    } else {
                        this.titleMessage = message;
                    }
                    return;
                }

                const importedData = importedMetadataPayload(payloadJson?.data ?? {});
                const importedType = normalizeLookupValue(importedData.type);
                const importedSource = payloadJson?.source ?? source;

                this.applyImportedMetadata(importedData, {
                    source: importedSource,
                    forceTitle: scope === 'title' || importedSource === 'tmdb',
                    forceBarcode: scope === 'barcode',
                    forceFilmFields: importedType === 'film' || (importedSource === 'tmdb' && !importedType),
                    forceTvSeriesFields: importedType === 'tv_series',
                    forceVideoGameFields: importedSource === 'igdb' || importedType === 'video_game',
                    forceBoardGameFields: importedSource === 'bgg' || importedType === 'board_game',
                });
                this.closeResults();

                const warnings = Array.isArray(payloadJson?.warnings) ? payloadJson.warnings : [];
                this.titleMessage = payloadJson?.message ?? this.labels.metadataImported;
                this.importNotice = warnings[0] ?? (importedData.cover_path ? this.labels.coverImported : '');
            } catch (error) {
                this.importBusy = false;
                this.titleMessage = this.labels.searchError;
            }
        },
        applyImportedMetadata(data, { forceTitle = false, forceBarcode = false, forceFilmFields = false, forceTvSeriesFields = false, forceVideoGameFields = false, forceBoardGameFields = false } = {}) {
            data = importedMetadataPayload(data);

            if (!data || typeof data !== 'object') {
                return;
            }

            if (data.type) {
                this.setFieldValue('type', data.type, { force: forceFilmFields || forceTvSeriesFields || forceVideoGameFields || forceBoardGameFields || !this.currentTypeValue(), dispatchChange: true });
                this.syncPhysicalFormat();
            }

            this.setFieldValue('title', data.title, { force: forceTitle || forceFilmFields || forceTvSeriesFields || forceVideoGameFields || forceBoardGameFields });
            this.setFieldValue('original_title', data.original_title, { force: forceFilmFields || forceTvSeriesFields });
            this.setFieldValue('description', data.description, { force: forceFilmFields || forceTvSeriesFields || forceVideoGameFields || forceBoardGameFields });
            this.setFieldValue('description_original', data.description_original, { force: true });
            this.setFieldValue('release_year', data.release_year, { force: forceFilmFields || forceTvSeriesFields || forceVideoGameFields || forceBoardGameFields });
            this.setFieldValue('end_year', data.end_year, { force: forceTvSeriesFields });
            this.setFieldValue('genres', data.genres, { force: forceFilmFields || forceTvSeriesFields || forceVideoGameFields || forceBoardGameFields });
            this.setFieldValue('runtime_minutes', data.runtime_minutes, { force: forceFilmFields || forceTvSeriesFields });
            this.setFieldValue('studio', data.studio, { force: forceFilmFields || forceTvSeriesFields });
            this.setFieldValue('cast_members', data.cast_members, { force: forceFilmFields || forceTvSeriesFields });
            this.setFieldValue('director', data.director, { force: forceFilmFields });
            this.setFieldValue('age_rating', data.age_rating, { force: forceFilmFields || forceTvSeriesFields || forceVideoGameFields || forceBoardGameFields });
            this.setFieldValue('external_tmdb_id', data.external_tmdb_id, { force: true });
            this.setFieldValue('season_count', data.season_count, { force: forceTvSeriesFields });
            this.setFieldValue('episode_count', data.episode_count, { force: forceTvSeriesFields });
            this.setFieldValue('showrunner', data.showrunner, { force: forceTvSeriesFields });
            this.setFieldValue('network', data.network, { force: forceTvSeriesFields });
            this.setFieldValue('barcode', data.barcode, { force: forceBarcode });
            this.setFieldValue('platform', data.platform, { force: forceVideoGameFields });
            this.setFieldValue('developer', data.developer, { force: forceVideoGameFields });
            this.setFieldValue('publisher', data.publisher, { force: forceVideoGameFields || forceBoardGameFields });
            this.setFieldValue('modes', data.modes, { force: forceVideoGameFields });
            this.setFieldValue('external_igdb_id', data.external_igdb_id, { force: true });
            this.setFieldValue('min_players', data.min_players, { force: forceBoardGameFields });
            this.setFieldValue('max_players', data.max_players, { force: forceBoardGameFields });
            this.setFieldValue('play_time_minutes', data.play_time_minutes, { force: forceBoardGameFields });
            this.setFieldValue('designer', data.designer, { force: forceBoardGameFields });

            if (data.cover_path && data.cover_url) {
                this.setFieldValue('cover_path', data.cover_path, { force: true });
                this.coverPath = normalizeLookupValue(data.cover_path);
                this.coverPreviewUrl = normalizeLookupValue(data.cover_url);
                this.removeCover = false;
                this.formElement()?.dispatchEvent(
                    new CustomEvent('barcode-cover-preview', {
                        bubbles: true,
                        detail: {
                            path: data.cover_path,
                            url: data.cover_url,
                        },
                    })
                );
            }
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

            stopMediaStream(this.$refs?.scannerVideo);

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
    };
}
