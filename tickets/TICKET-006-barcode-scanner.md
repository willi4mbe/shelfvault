# TICKET-006 - Barcode scanner

## Goal

Add barcode scanning from mobile browser camera.

## Scope

- Scanner page/component
- ZXing JS integration
- Camera permission handling
- EAN/UPC detection
- Manual barcode input fallback
- Store barcode on item
- Local lookup before external providers

## Acceptance criteria

- Phone camera can scan supported barcodes on HTTPS/local supported contexts
- User can type barcode manually if camera fails
- Scanned barcode can prefill an item form
- No external API is required
