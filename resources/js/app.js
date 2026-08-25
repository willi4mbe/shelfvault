import Alpine from 'alpinejs';
import { metadataLookup } from './metadata-lookup';

window.Alpine = Alpine;

Alpine.data('metadataLookup', metadataLookup);

Alpine.start();
