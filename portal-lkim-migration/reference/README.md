# Reference design assets

Images downloaded from the reference build at <https://nurzamf.github.io/lkim>
by `fetch-reference-images.php`. The full manifest — every URL, where it was
referenced, HTTP status, size and type — is in
`reports/reference-images.csv`.

Nothing here is wired into the portal yet.

## `site/` — the design's own assets (23 files, 3.3 MB)

These are the ones worth keeping. They map onto the bands already built in
`tpl_lkim2`:

| Files | Used for |
|---|---|
| `main-logo.png` (1324x132) | LKIM masthead lockup |
| `1-icon-licensing.png`, `1-icon-industry-info.png`, `1-icon-funding-and-programme.png`, `1-icon-facilities-and-offices.png`, `1-icon-online-services.png` | Popular Services cards |
| `fisherman-img.png`, `fish-trader-img.png`, `entrepreneur-img.png`, `aquaculture-operator-img.png`, `researcher-img.png`, `general-publics-img.png` (150x150) | Profile chooser — currently emoji placeholders in the template |
| `e_dana-thumbnail.png`, `kubena-thumbnail.png`, `kunita-thumbnail.png`, `qfish-thumbnail.png` (400x210) | Programme cards |
| `mygov.png`, `pertanian.png`, `perikanan.png`, `veterinar.png`, `fama.png` | Related agencies strip |
| `banner-MAHA-2026.png` (2880x1320, 2 MB) | Event banner |
| `licensing_icon_1782699145873.jpg` (1024x1024) | Licensing artwork |

## `images.unsplash.com/` — stock photography (7 files)

Including `photo-1596022312589-*.jpg` (2070x1380), the hero image the reference
CSS hot-links. **Check the licence before publishing any of these.** The portal
currently uses LKIM's own photography for the hero instead.

## `picsum.photos/` — placeholders (24 files)

Randomly generated filler at 120x120, 400x178, 400x200 and 80x70. They carry no
meaning; the real equivalents are news thumbnails and agency logos. Delete these
once the corresponding real images exist.

## Provenance

The `site/` assets came from a third-party GitHub Pages demo, not from LKIM
directly. Confirm with the client that they own them — several are clearly
LKIM's own marks, but the stock photography and placeholders are not.
