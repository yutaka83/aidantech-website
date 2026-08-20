<?php
/**
 * The single source of truth for "where does this piece of lkim.gov.my content
 * belong in the Joomla portal". Everything downstream (categories, media
 * folders, articles, menus) reads this file, so corrections happen once.
 */

return [

    /**
     * The Joomla category tree. Key = path, value = [title, description].
     * Order matters: parents must be declared before their children.
     * Media folders under images/ mirror these paths exactly.
     */
    'categories' => [
        'info-lkim'                                => ['Info LKIM', 'Maklumat korporat Lembaga Kemajuan Ikan Malaysia.'],
        'info-lkim/perutusan'                      => ['Perutusan', 'Perutusan pengerusi, ketua pengarah dan pendaftar.'],
        'info-lkim/profil'                         => ['Profil', 'Latar belakang, visi, misi, piagam pelanggan dan akta.'],
        'info-lkim/organisasi'                     => ['Organisasi', 'Struktur organisasi, pengurusan atasan dan pejabat negeri.'],

        'perkhidmatan'                             => ['Perkhidmatan', 'Perkhidmatan LKIM kepada masyarakat nelayan dan industri perikanan.'],
        'perkhidmatan/institusi-nelayan'           => ['Institusi Nelayan', 'KUNITA, KUBENA, KESAN dan pembangunan akuakultur.'],
        'perkhidmatan/bantuan-masyarakat-nelayan'  => ['Bantuan Kepada Masyarakat Nelayan', 'Skim bantuan, subsidi dan kebajikan nelayan.'],
        'perkhidmatan/pemasaran-ikan'              => ['Pemasaran Ikan', 'Pasar nelayan, CCDC dan program QFISH.'],
        'perkhidmatan/pembangunan-infrastruktur'   => ['Pembangunan Infrastruktur', 'Kompleks perikanan, jeti pendaratan dan kemudahan sokongan.'],
        'perkhidmatan/kawalselia-penguatkuasaan'   => ['Kawalselia Pendaratan Ikan dan Penguatkuasaan', 'Pengiktirafan, pengawasan kualiti dan operasi penguatkuasaan.'],
        'perkhidmatan/industri-asas-tani'          => ['Pembangunan Industri Asas Tani', 'PPHP, Fishpro, keusahawanan dan perikanan laut dalam.'],
        'perkhidmatan/agrotourism'                 => ['Agrotourism', 'Pusat latihan, chalet, medan ikan bakar dan restoran.'],

        'hubungi-kami'                             => ['Hubungi Kami', 'Direktori kakitangan dan peta lokasi.'],

        'berita'                                   => ['Berita & Pengumuman', 'Hebahan semasa LKIM.'],
        'berita/pengumuman'                        => ['Pengumuman', 'Pengumuman rasmi LKIM.'],
        'berita/berita-terkini'                    => ['Berita Terkini', 'Berita dan sorotan peristiwa.'],
        'berita/sebut-harga'                       => ['Sebut Harga', 'Iklan sebut harga.'],
        'berita/tender'                            => ['Tender', 'Iklan tender.'],

        'arkib'                                    => ['Arkib', 'Kandungan lampau yang dikekalkan untuk rujukan.'],
        'arkib/arkib-pengumuman'                   => ['Arkib Pengumuman', 'Pengumuman lampau.'],
        'arkib/arkib-sebut-harga'                  => ['Arkib Sebut Harga', 'Sebut harga lampau.'],
        'arkib/arkib-berita'                       => ['Arkib Berita', 'Berita lampau.'],

        'galeri'                                   => ['Galeri', 'Galeri gambar dan video.'],
        'lain-lain'                                => ['Lain-lain', 'Halaman yang belum diletakkan di bawah menu utama.'],
    ],

    /**
     * WordPress category id => Joomla category path.
     * The source keeps parallel BM and EN categories; both land in the same
     * Joomla category because Falang carries the language layer.
     */
    'wp_categories' => [
        9    => 'berita/pengumuman',        // pengumuman
        8    => 'berita/pengumuman',        // announcement
        10   => 'berita/berita-terkini',    // berita-terkini
        11   => 'berita/berita-terkini',    // latest-news
        1622 => 'berita/sebut-harga',       // sebutharga
        1624 => 'berita/sebut-harga',       // quotation
        1632 => 'berita/tender',            // tender
        1650 => 'berita/tender',            // tender-en
        1636 => 'arkib/arkib-pengumuman',   // arkib-pengumuman
        1642 => 'arkib/arkib-pengumuman',   // archive-announcement
        1634 => 'arkib/arkib-sebut-harga',  // arkib-sebut-harga
        1640 => 'arkib/arkib-sebut-harga',  // archive-quotation
        25   => 'arkib/arkib-berita',       // arkib-berita-terkini
        26   => 'arkib/arkib-berita',       // archive-latest-news
        13   => 'berita/berita-terkini',    // berita-dan-peristiwa
        14   => 'berita/berita-terkini',    // news-and-events
        1748 => 'lain-lain',                // blog
        1822 => 'lain-lain',                // pendaftar
        1    => 'lain-lain',                // uncategorized
        3    => 'lain-lain',                // uncategorized-ms
    ],

    /**
     * Second-level menu label => Joomla category path. Pages reached through
     * one of these branches inherit its category. Labels are matched
     * case-insensitively after whitespace collapsing, in both languages.
     */
    'menu_sections' => [
        'perutusan'                                     => 'info-lkim/perutusan',
        'messages'                                      => 'info-lkim/perutusan',
        'profil'                                        => 'info-lkim/profil',
        'profile'                                       => 'info-lkim/profil',
        'organisasi'                                    => 'info-lkim/organisasi',
        'organisation'                                  => 'info-lkim/organisasi',
        'organization'                                  => 'info-lkim/organisasi',
        'institusi nelayan'                             => 'perkhidmatan/institusi-nelayan',
        'fishermen institution'                         => 'perkhidmatan/institusi-nelayan',
        'fishermen institutions'                        => 'perkhidmatan/institusi-nelayan',
        'bantuan kepada masyarakat nelayan'             => 'perkhidmatan/bantuan-masyarakat-nelayan',
        'assistance to the fishing community'           => 'perkhidmatan/bantuan-masyarakat-nelayan',
        'pemasaran ikan'                                => 'perkhidmatan/pemasaran-ikan',
        'fish marketing'                                => 'perkhidmatan/pemasaran-ikan',
        'pembangunan infrastruktur'                     => 'perkhidmatan/pembangunan-infrastruktur',
        'infrastructure development'                    => 'perkhidmatan/pembangunan-infrastruktur',
        'kawalselia pendaratan ikan dan penguatkuasaan' => 'perkhidmatan/kawalselia-penguatkuasaan',
        'fish landings regulation and enforcement'      => 'perkhidmatan/kawalselia-penguatkuasaan',
        'pembangunan industri asas tani'                => 'perkhidmatan/industri-asas-tani',
        'agro-based industry development'               => 'perkhidmatan/industri-asas-tani',
        'agrotourism'                                   => 'perkhidmatan/agrotourism',
    ],

    /**
     * Top-level menu label => Joomla category path, used when a menu item hangs
     * directly off a top-level entry with no section in between.
     */
    'menu_roots' => [
        'info lkim'    => 'info-lkim',
        'perkhidmatan' => 'perkhidmatan',
        'services'     => 'perkhidmatan',
        'hubungi kami' => 'hubungi-kami',
        'contact us'   => 'hubungi-kami',
    ],

    /**
     * Posts carry several WordPress categories at once - a quotation is also
     * tagged "pengumuman". Resolve to the most specific one by walking this
     * list in order and taking the first id the post actually has.
     */
    'wp_category_priority' => [
        1632, 1650,        // tender / tender-en
        1622, 1624,        // sebutharga / quotation
        1634, 1640,        // arkib sebut harga
        25, 26,            // arkib berita
        1636, 1642,        // arkib pengumuman
        10, 11, 13, 14,    // berita terkini
        9, 8,              // pengumuman
        1748, 1822, 1, 3,  // uncategorised buckets
    ],

    /**
     * Source slugs that must not be migrated. The live site carries injected
     * SEO spam; these are excluded from articles, menus and media alike.
     */
    'blocklist_slugs' => [
        'research-paper-writing-services-things-to-consider',
        'test-pages',
    ],

    /**
     * Substrings that mark a slug or title as injected spam. Anything matching
     * is skipped and written to reports/skipped-spam.csv for review.
     */
    'spam_patterns' => [
        'steroid', 'anabolic', 'casino', 'essay-writ', 'writing-service',
        'viagra', 'cialis', 'betting', 'payday-loan', 'crypto-invest',
    ],

    /**
     * The one live menu target that does not resolve to a page. Kept explicit
     * so the import does not silently drop the menu item.
     */
    'known_broken_targets' => [
        'pendaratan-ikan-di-kompleks-labuhan-perikanan-lkim',
    ],

    /**
     * Media routing. Files are filed by the category of the article that first
     * references them; these rules catch the rest.
     */
    'media' => [
        'root'          => 'images',
        'documents'     => 'images/muat-turun',
        'news_pattern'  => 'images/berita/{Y}/{m}',
        'orphans'       => 'images/arkib',
        // WordPress' generated derivatives - the originals are enough.
        'skip_suffixes' => '/-\d{2,4}x\d{2,4}(?=\.[a-z0-9]+$)/i',
    ],
];
