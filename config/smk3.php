<?php

/*
| Reference data carried over from the legacy system (smk3old). Codes are kept
| exactly as the legacy database stores them so old rows can be imported as-is.
*/

return [
    // users.pabrik / non_conformities.pabrik
    'plants' => [
        'ckp' => 'Cikampek (CKP)',
        'ing' => 'Ingredient (ING)',
        'psr' => 'Pasuruan (PSR)',
        'taa' => 'Tanjung Api-Api (TAA)',
        'smg' => 'Semarang (SMG)',
    ],

    // Extra users.pabrik values that grant several plants at once.
    'plant_groups' => [
        'ckp,ing' => 'CKP + ING (Multi-pabrik)',
    ],

    // Departments per plant; plants without their own list use "default".
    'departments' => [
        'default' => [
            'OIL' => 'Produksi Minyak',
            'BMB' => 'Produksi Bumbu',
            'WHRM' => 'Warehouse RM',
            'WHFG' => 'Warehouse FG',
            'HR' => 'HR (FASUM, LINGKUNGAN, OFFICE)',
            'QC' => 'Quality Control',
            'TEKNIK' => 'Teknik',
        ],
        'ing' => [
            'premix' => 'Premix',
            'spices' => 'Spices',
            'freezedry' => 'Freeze Dry',
            'qcing' => 'QC Ing',
            'hring' => 'HR Ing',
            'tekniking' => 'Teknik Ing',
            'warehouseing' => 'Warehouse Ing',
        ],
    ],

    // non_conformities.kategori
    'categories' => ['Minor', 'Major', 'Kritis'],

    // The 12 SMK3 elements (PP 50/2012); a criterion belongs to the element of the
    // first part of its number, e.g. 6.7.2 -> 6.
    'elements' => [
        1 => 'Pembangunan dan Pemeliharaan Komitmen',
        2 => 'Pembuatan dan Pendokumentasian Rencana K3',
        3 => 'Pengendalian Perancangan dan Peninjauan Kontrak',
        4 => 'Pengendalian Dokumen',
        5 => 'Pembelian dan Pengendalian Produk',
        6 => 'Keamanan Bekerja Berdasarkan SMK3',
        7 => 'Standar Pemantauan',
        8 => 'Pelaporan dan Perbaikan Kekurangan',
        9 => 'Pengelolaan Material dan Perpindahannya',
        10 => 'Pengumpulan dan Penggunaan Data',
        11 => 'Pemeriksaan SMK3',
        12 => 'Pengembangan Keterampilan dan Kemampuan',
    ],
];
