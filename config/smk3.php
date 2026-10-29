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
];
