import assert from 'node:assert/strict';

import { metadataLookup } from '../../resources/js/metadata-lookup.js';

function makeField(name, value = '') {
    return {
        name,
        type: 'text',
        value,
        events: [],
        dispatchEvent(event) {
            this.events.push(event.type);
        },
    };
}

const fields = [
    makeField('title'),
    makeField('original_title'),
    makeField('description'),
    makeField('description_original'),
    makeField('release_year'),
    makeField('end_year'),
    makeField('genres'),
    makeField('genres'),
    makeField('runtime_minutes'),
    makeField('director'),
    makeField('cast_members'),
    makeField('age_rating'),
    makeField('age_rating'),
    makeField('external_tmdb_id'),
    makeField('season_count'),
    makeField('episode_count'),
    makeField('showrunner'),
    makeField('network'),
    makeField('external_igdb_id'),
    makeField('cover_path'),
    { ...makeField('cover_image', 'selected-cover'), type: 'file' },
    makeField('platform'),
    makeField('developer'),
    makeField('publisher'),
    makeField('modes'),
    makeField('min_players'),
    makeField('max_players'),
    makeField('play_time_minutes'),
    makeField('designer'),
];

const form = {
    elements: {
        namedItem: (name) => fields.find((field) => field.name === name) ?? null,
    },
    querySelectorAll(selector) {
        const match = selector.match(/^\[name="(.+)"\]$/);

        return match ? fields.filter((field) => field.name === match[1]) : [];
    },
    dispatchEvent() {},
};

const root = {
    querySelector: (selector) => selector === 'form' ? form : null,
};

const component = metadataLookup({ type: 'film' });
component.$root = root;
component.$el = {
    closest: () => null,
    querySelector: (selector) => selector === 'form' ? form : null,
};

component.applyImportedMetadata({
    type: 'film',
    title: 'Matrix',
    original_title: 'The Matrix',
    description: 'A hacker discovers reality.',
    release_year: 1999,
    genres: ['Action', 'Science-Fiction'],
    runtime_minutes: 136,
    director: 'Lana Wachowski',
    cast_members: ['Keanu Reeves', 'Laurence Fishburne'],
    age_rating: 'TP',
    external_tmdb_id: 603,
    cover_path: 'covers/matrix.jpg',
    cover_url: 'http://localhost:8000/storage/covers/matrix.jpg',
}, {
    forceTitle: true,
    forceFilmFields: true,
});

function values(name) {
    return fields.filter((field) => field.name === name).map((field) => field.value);
}

assert.equal(values('description')[0], 'A hacker discovers reality.');
assert.equal(values('runtime_minutes')[0], '136');
assert.equal(values('director')[0], 'Lana Wachowski');
assert.deepEqual(values('age_rating'), ['TP', 'TP']);
assert.deepEqual(values('genres'), ['Action, Science-Fiction', 'Action, Science-Fiction']);
assert.equal(values('cast_members')[0], 'Keanu Reeves, Laurence Fishburne');
assert.equal(values('cover_path')[0], 'covers/matrix.jpg');
assert.equal(component.coverPath, 'covers/matrix.jpg');
assert.equal(component.coverPreviewUrl, 'http://localhost:8000/storage/covers/matrix.jpg');

component.applyImportedMetadata({
    type: 'tv_series',
    title: 'The Expanse',
    release_year: 2015,
    end_year: 2022,
    season_count: 6,
    episode_count: 62,
    showrunner: 'Naren Shankar',
    network: 'Syfy / Prime Video',
});

assert.equal(values('season_count')[0], '6');
assert.equal(values('episode_count')[0], '62');
assert.equal(values('end_year')[0], '2022');
assert.equal(values('showrunner')[0], 'Naren Shankar');
assert.equal(values('network')[0], 'Syfy / Prime Video');

component.applyImportedMetadata({
    type: 'video_game',
    title: 'Super Mario Galaxy',
    description: 'Mario explores space.',
    description_original: 'Mario explores space in English.',
    release_year: 2007,
    genres: ['Platform', 'Adventure'],
    platform: 'Wii',
    developer: 'Nintendo EAD Tokyo',
    publisher: 'Nintendo',
    modes: ['Single player'],
    age_rating: 'PEGI 3',
    external_igdb_id: 1234,
}, {
    forceTitle: true,
    forceVideoGameFields: true,
});

assert.equal(values('title')[0], 'Super Mario Galaxy');
assert.equal(values('description')[0], 'Mario explores space.');
assert.equal(values('description_original')[0], 'Mario explores space in English.');
assert.equal(component.releaseYear, '2007');
assert.deepEqual(values('genres'), ['Platform, Adventure', 'Platform, Adventure']);
assert.equal(values('platform')[0], 'Wii');
assert.equal(values('developer')[0], 'Nintendo EAD Tokyo');
assert.equal(values('publisher')[0], 'Nintendo');
assert.equal(values('modes')[0], 'Single player');
assert.equal(values('external_igdb_id')[0], '1234');

component.applyImportedMetadata({
    type: 'board_game',
    title: 'CATAN',
    description: 'Trade, build, and settle.',
    release_year: 1995,
    genres: ['Negotiation', 'Dice Rolling'],
    min_players: 3,
    max_players: 4,
    play_time_minutes: 120,
    designer: 'Klaus Teuber',
    publisher: 'Kosmos',
    age_rating: '10+',
}, {
    forceTitle: true,
    forceBoardGameFields: true,
});

assert.equal(values('title')[0], 'CATAN');
assert.equal(values('description')[0], 'Trade, build, and settle.');
assert.equal(values('release_year')[0], '1995');
assert.deepEqual(values('genres'), ['Negotiation, Dice Rolling', 'Negotiation, Dice Rolling']);
assert.equal(values('min_players')[0], '3');
assert.equal(values('max_players')[0], '4');
assert.equal(values('play_time_minutes')[0], '120');
assert.equal(values('designer')[0], 'Klaus Teuber');
assert.equal(values('publisher')[0], 'Kosmos');
assert.deepEqual(values('age_rating'), ['10+', '10+']);

const originalDocument = globalThis.document;
const originalFetch = globalThis.fetch;

component.importUrl = '/admin/collection/metadata/import';
component.resultsScope = 'title';
component.resultsSource = '';
component.$el = {
    closest: () => null,
    querySelector: () => null,
};

globalThis.document = {
    querySelector(selector) {
        return selector === 'meta[name="csrf-token"]'
            ? { getAttribute: () => 'csrf-token' }
            : null;
    },
};

globalThis.fetch = async (url, options) => {
    assert.equal(url, '/admin/collection/metadata/import');
    assert.equal(options.method, 'POST');
    assert.deepEqual(JSON.parse(options.body), {
        source: 'tmdb',
        tmdb_id: 603,
        type: 'film',
    });

    return {
        ok: true,
        async json() {
            return {
                status: 'found',
                message: 'Imported',
                data: {
                    type: 'film',
                    title: 'Imported Matrix',
                    original_title: 'The Matrix',
                    description: 'Imported description.',
                    release_year: 1999,
                    genres: ['Action'],
                    runtime_minutes: 136,
                    director: 'Lana Wachowski',
                    external_tmdb_id: 603,
                },
            };
        },
    };
};

try {
    await component.importCandidate({ tmdb_id: 603 });
} finally {
    globalThis.document = originalDocument;
    globalThis.fetch = originalFetch;
}

assert.equal(values('title')[0], 'Imported Matrix');
assert.equal(values('original_title')[0], 'The Matrix');
assert.equal(values('description')[0], 'Imported description.');
assert.equal(values('release_year')[0], '1999');
assert.equal(values('external_tmdb_id')[0], '603');
assert.equal(component.title, 'Imported Matrix');
assert.equal(component.originalTitle, 'The Matrix');

component.resultsScope = 'title';
component.resultsSource = 'bgg';

globalThis.document = {
    querySelector(selector) {
        return selector === 'meta[name="csrf-token"]'
            ? { getAttribute: () => 'csrf-token' }
            : null;
    },
};

globalThis.fetch = async (url, options) => {
    assert.equal(url, '/admin/collection/metadata/import');
    assert.equal(options.method, 'POST');
    assert.deepEqual(JSON.parse(options.body), {
        source: 'bgg',
        bgg_id: 13,
    });

    return {
        ok: true,
        async json() {
            return {
                status: 'found',
                source: 'bgg',
                message: 'Imported',
                data: {
                    type: 'board_game',
                    title: 'CATAN imported',
                    min_players: 3,
                    max_players: 4,
                    play_time_minutes: 120,
                },
            };
        },
    };
};

try {
    await component.importCandidate({ source: 'bgg', bgg_id: 13 });
} finally {
    globalThis.document = originalDocument;
    globalThis.fetch = originalFetch;
}

assert.equal(values('title')[0], 'CATAN imported');
assert.equal(values('min_players')[0], '3');
assert.equal(values('max_players')[0], '4');
assert.equal(values('play_time_minutes')[0], '120');

const formatComponent = metadataLookup({
    type: 'film',
    physicalFormat: 'digital_copy',
    formatOptions: {
        film: {
            digital_copy: 'Digital copy',
        },
    },
});

assert.equal(formatComponent.currentPhysicalFormatLabel, 'Digital copy');

const originalUrl = globalThis.URL;
let revokedUrl = null;

globalThis.URL = {
    createObjectURL(file) {
        assert.equal(file.type, 'image/png');

        return 'blob:test-cover';
    },
    revokeObjectURL(url) {
        revokedUrl = url;
    },
};

try {
    formatComponent.previewCoverImage({
        target: {
            files: [{ type: 'image/png' }],
        },
    });

    assert.equal(formatComponent.coverPreviewUrl, 'blob:test-cover');
    assert.equal(formatComponent.localCoverPreviewUrl, 'blob:test-cover');

    formatComponent.destroy();

    assert.equal(revokedUrl, 'blob:test-cover');
} finally {
    globalThis.URL = originalUrl;
}

const scrollComponent = metadataLookup({});
const originalWindow = globalThis.window;
const originalScrollDocument = globalThis.document;
const bodyStyle = {
    position: '',
    top: '',
    left: '',
    right: '',
    width: '',
    overflow: '',
};
let restoredScrollY = null;

globalThis.window = {
    scrollY: 312,
    pageYOffset: 312,
    scrollTo(x, y) {
        assert.equal(x, 0);
        restoredScrollY = y;
    },
};
globalThis.document = {
    body: {
        style: bodyStyle,
    },
};

try {
    scrollComponent.openResults('title', 'bgg', [{ bgg_id: 13, title: 'CATAN' }], 'Results');

    assert.equal(bodyStyle.position, 'fixed');
    assert.equal(bodyStyle.top, '-312px');
    assert.equal(bodyStyle.overflow, 'hidden');
    assert.equal(scrollComponent.resultsOpen, true);

    scrollComponent.closeResults();

    assert.equal(bodyStyle.position, '');
    assert.equal(bodyStyle.top, '');
    assert.equal(bodyStyle.overflow, '');
    assert.equal(restoredScrollY, 312);
    assert.equal(scrollComponent.resultsOpen, false);
} finally {
    globalThis.window = originalWindow;
    globalThis.document = originalScrollDocument;
}


const loadMoreComponent = metadataLookup({
    type: 'film',
    title: 'Matrix',
    titleSearchUrl: '/admin/collection/metadata/search',
    labels: {
        searching: 'Searching',
        resultsFound: 'Results found',
        noResultFound: 'No result found',
        searchError: 'Search error',
        loadMore: 'Show more',
        allResultsShown: 'All results are shown.',
    },
});
loadMoreComponent.$root = root;
loadMoreComponent.$el = component.$el;

const loadRequests = [];
const loadResponses = [
    {
        status: 'found',
        source: 'tmdb',
        message: 'Results found',
        data: {
            candidates: [
                { source: 'tmdb', tmdb_id: 1, title: 'Matrix 1' },
                { source: 'tmdb', tmdb_id: 2, title: 'Matrix 2' },
            ],
            pagination: { current_page: 1, per_page: 10, has_more: true, next_page: 2 },
        },
    },
    {
        status: 'found',
        source: 'tmdb',
        message: 'Results found',
        data: {
            candidates: [
                { source: 'tmdb', tmdb_id: 2, title: 'Matrix 2 duplicate' },
                { source: 'tmdb', tmdb_id: 3, title: 'Matrix 3' },
            ],
            pagination: { current_page: 2, per_page: 10, has_more: false, next_page: null },
        },
    },
    {
        status: 'found',
        source: 'tmdb',
        message: 'Results found',
        data: {
            candidates: [
                { source: 'tmdb', tmdb_id: 4, title: 'New query result' },
            ],
            pagination: { current_page: 1, per_page: 10, has_more: false, next_page: null },
        },
    },
];

globalThis.document = {
    querySelector(selector) {
        return selector === 'meta[name="csrf-token"]'
            ? { getAttribute: () => 'csrf-token' }
            : null;
    },
};

globalThis.fetch = async (url, options) => {
    loadRequests.push(JSON.parse(options.body));

    return {
        ok: true,
        status: 200,
        async json() {
            return loadResponses.shift();
        },
    };
};

try {
    await loadMoreComponent.searchTitle();

    assert.equal(loadRequests[0].title, 'Matrix');
    assert.equal(loadRequests[0].page, 1);
    assert.equal(loadMoreComponent.resultsCandidates.length, 2);
    assert.equal(loadMoreComponent.resultsPagination.hasMore, true);
    assert.equal(loadMoreComponent.resultsPagination.nextPage, 2);

    loadMoreComponent.title = 'Changed while modal is open';
    await loadMoreComponent.loadMoreResults();

    assert.equal(loadRequests[1].title, 'Matrix');
    assert.equal(loadRequests[1].page, 2);
    assert.deepEqual(loadMoreComponent.resultsCandidates.map((candidate) => candidate.tmdb_id), [1, 2, 3]);
    assert.equal(loadMoreComponent.resultsPagination.hasMore, false);
    assert.equal(loadMoreComponent.resultsAllShown, true);

    loadMoreComponent.title = 'New query';
    await loadMoreComponent.searchTitle();

    assert.equal(loadRequests[2].title, 'New query');
    assert.deepEqual(loadMoreComponent.resultsCandidates.map((candidate) => candidate.tmdb_id), [4]);
    assert.equal(loadMoreComponent.resultsPagination.hasMore, false);
} finally {
    globalThis.document = originalDocument;
    globalThis.fetch = originalFetch;
}

const coverComponent = metadataLookup({
    coverPath: 'covers/current.jpg',
    coverPreviewUrl: '/storage/covers/current.jpg',
    labels: { coverRemoved: 'Cover removed.' },
});
coverComponent.$root = root;
coverComponent.$el = component.$el;

coverComponent.removeCoverImage();

assert.equal(coverComponent.coverPath, '');
assert.equal(coverComponent.coverPreviewUrl, '');
assert.equal(coverComponent.removeCover, true);
assert.equal(coverComponent.importNotice, 'Cover removed.');
assert.equal(values('cover_image')[0], '');

coverComponent.applyImportedMetadata({
    cover_path: 'covers/imported.jpg',
    cover_url: '/storage/covers/imported.jpg',
});

assert.equal(coverComponent.coverPath, 'covers/imported.jpg');
assert.equal(coverComponent.coverPreviewUrl, '/storage/covers/imported.jpg');
assert.equal(coverComponent.removeCover, false);
