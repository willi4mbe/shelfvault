# ShelfVault - Product Specification V1

## Vision

ShelfVault is a free, self-hosted application for cataloging a physical home collection of films, video games, and board games.

It should feel like a professional, private, home-focused alternative to large catalog platforms, but installable on a NAS or classic web server.

## Target users

- Families with physical films and games
- Collectors
- Retro gamers
- Board game players
- People who lend physical media to friends or family
- Self-hosting users who want data ownership

## Core principles

1. Stable core first.
2. Manual cataloging must always work.
3. Barcode/API enrichment is optional.
4. Installation must be simple.
5. Mobile usage is primary.
6. Data belongs to the user.

## V1 scope

### Admin

- One admin account
- Login/logout
- Password change
- Basic settings

### Collection

- Add/edit/delete items
- Item types:
  - Film
  - Video game
  - Board game
- Upload cover/photo
- Search and filters
- Physical condition
- Completion status
- Location in the home
- Notes

### Loans

- Mark item as loaned
- Borrower name
- Loan date
- Expected return date
- Return status

### Wishlist

- Add wanted items
- Priority
- Notes
- Optional link

### Guest sharing

- Generate read-only share link
- Optional password
- Privacy options
- No modification rights

### Barcode scanning

- Use phone camera in browser
- Support EAN/UPC where possible
- Manual barcode input fallback
- Store barcode locally

### PWA

- Installable on iOS/Android home screen
- Responsive mobile-first layout
- App manifest
- Icons

### Installation

- Docker Compose installation for NAS/server
- Browser-based installer `/install`
- Server requirement checks
- Database configuration
- Admin account creation
- Installation lock

## Out of scope for V1

- Multi-admin/multi-user roles
- Marketplace
- Cloud subscription
- Native app store apps
- Automatic price estimation
- Required external providers
- Social network features

## Optional integrations after core

- IGDB/Twitch for video game metadata
- TMDb for movie metadata
- Film barcode providers
- ScanDex or equivalent for video game barcode mapping

## Main tagline

Your physical collection, self-hosted.

French tagline:

Votre collection physique, chez vous.
