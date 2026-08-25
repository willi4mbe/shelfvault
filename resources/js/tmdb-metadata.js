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

export function tmdbMetadataLookup(config = {}) {
    return {
        searchUrl: config.searchUrl ?? '',
        importUrl: config.importUrl ?? '',
        labels: {
            searchMetadata: config.labels?.searchMetadata ?? '',
            searching: config.labels?.searching ?? '',
            resultsFound: config.labels?.resultsFound ?? '',
            noResultFound: config.labels?.noResultFound ?? '',
            tmdbNotConfigured: config.labels?.tmdbNotConfigured ?? '',
            searchError: config.labels?.searchError ?? '',
            metadataImported: config.labels?.metadataImported ?? '',
            chooseThisResult: config.labels?.chooseThisResult ?? '',
            posterImportFailed: config.labels?.posterImportFailed ?? '',
        },
        externalTmdbId: config.externalTmdbId ?? '',
        busy: false,
        state: '',
        message: '',
        warning: '',
        error: '',
        candidates: [],
        activeCandidateId: null,
        formElement() {
            return this.$el.closest('form');
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
        clearStatus() {
            this.error = '';
            this.warning = '';
        },
        searchMetadata() {
            this.clearStatus();

            const title = this.currentFieldValue('title');
            const releaseYear = this.currentFieldValue('release_year');

            if (title === '') {
                this.state = '';
                this.message = '';
                return Promise.resolve();
            }

            if (!this.searchUrl) {
                this.state = 'no_source';
                this.message = this.labels.tmdbNotConfigured;
                return Promise.resolve();
            }

            this.busy = true;
            this.state = 'searching';
            this.message = this.labels.searching;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

            return fetch(this.searchUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    title,
                    release_year: releaseYear === '' ? null : Number(releaseYear),
                }),
            })
                .then(async (response) => ({
                    response,
                    payload: await response.json().catch(() => ({})),
                }))
                .then(({ response, payload }) => {
                    this.busy = false;

                    if (response.status === 422) {
                        this.state = 'invalid';
                        this.error = payload?.message ?? this.labels.searchError;
                        this.message = this.error;
                        return;
                    }

                    if (!response.ok) {
                        this.state = 'error';
                        this.error = payload?.message ?? this.labels.searchError;
                        this.message = this.error;
                        return;
                    }

                    this.state = payload?.status ?? 'not_found';
                    this.message = payload?.message
                        ?? (
                            this.state === 'found'
                                ? this.labels.resultsFound
                                : this.state === 'no_source'
                                    ? this.labels.tmdbNotConfigured
                                    : this.labels.noResultFound
                        );
                    this.candidates = payload?.data?.candidates ?? [];
                    this.activeCandidateId = null;

                    if (this.state !== 'found') {
                        this.candidates = [];
                    }
                })
                .catch(() => {
                    this.busy = false;
                    this.state = 'error';
                    this.error = this.labels.searchError;
                    this.message = this.error;
                });
        },
        chooseCandidate(candidate) {
            if (!candidate || typeof candidate !== 'object') {
                return;
            }

            this.activeCandidateId = candidate.tmdb_id ?? null;
            this.importMetadata(candidate.tmdb_id);
        },
        importMetadata(tmdbId) {
            this.clearStatus();

            if (!this.importUrl) {
                this.state = 'no_source';
                this.message = this.labels.tmdbNotConfigured;
                return Promise.resolve();
            }

            if (!tmdbId) {
                return Promise.resolve();
            }

            this.busy = true;
            this.state = 'searching';
            this.message = this.labels.searching;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

            return fetch(this.importUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ tmdb_id: tmdbId }),
            })
                .then(async (response) => ({
                    response,
                    payload: await response.json().catch(() => ({})),
                }))
                .then(({ response, payload }) => {
                    this.busy = false;

                    if (!response.ok) {
                        this.state = 'error';
                        this.error = payload?.message ?? this.labels.searchError;
                        this.message = this.error;
                        return;
                    }

                    this.state = payload?.status ?? 'found';
                    this.message = payload?.message ?? this.labels.metadataImported;
                    this.candidates = [];
                    this.applyImportedMetadata(payload?.data ?? {});

                    const warnings = Array.isArray(payload?.warnings) ? payload.warnings : [];
                    this.warning = warnings[0] ?? '';
                    if (this.warning !== '') {
                        this.message = this.warning;
                    }
                })
                .catch(() => {
                    this.busy = false;
                    this.state = 'error';
                    this.error = this.labels.searchError;
                    this.message = this.error;
                });
        },
        applyImportedMetadata(data) {
            if (!data || typeof data !== 'object') {
                return;
            }

            if (data.type && !this.currentFieldValue('type')) {
                this.setFieldValue('type', data.type, { force: true, dispatchChange: true });
            }

            this.setFieldValue('title', data.title);
            this.setFieldValue('original_title', data.original_title);
            this.setFieldValue('description', data.description);
            this.setFieldValue('release_year', data.release_year);
            this.setFieldValue('genres', data.genres);
            this.setFieldValue('runtime_minutes', data.runtime_minutes);
            this.setFieldValue('studio', data.studio);
            this.setFieldValue('cast_members', data.cast_members);
            this.setFieldValue('age_rating', data.age_rating);
            this.setFieldValue('external_tmdb_id', data.external_tmdb_id, { force: true });

            if (data.cover_path && data.cover_url && !this.currentFieldValue('cover_path')) {
                this.setFieldValue('cover_path', data.cover_path, { force: true });
                this.formElement()?.dispatchEvent(
                    new CustomEvent('barcode-cover-preview', {
                        bubbles: true,
                        detail: { path: data.cover_path, url: data.cover_url },
                    })
                );
            }
        },
    };
}
