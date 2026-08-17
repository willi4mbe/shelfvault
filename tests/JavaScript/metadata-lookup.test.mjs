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
    makeField('platform'),
    makeField('developer'),
    makeField('publisher'),
    makeField('modes'),
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
    season_count: 6,
    episode_count: 62,
    showrunner: 'Naren Shankar',
    network: 'Syfy / Prime Video',
});

assert.equal(values('season_count')[0], '6');
assert.equal(values('episode_count')[0], '62');
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
