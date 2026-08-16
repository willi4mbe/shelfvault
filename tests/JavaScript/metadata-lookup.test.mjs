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
    makeField('release_year'),
    makeField('genres'),
    makeField('genres'),
    makeField('runtime_minutes'),
    makeField('director'),
    makeField('cast_members'),
    makeField('age_rating'),
    makeField('age_rating'),
    makeField('external_tmdb_id'),
    makeField('cover_path'),
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

const component = metadataLookup({ type: 'film' });
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
