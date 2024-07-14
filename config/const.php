<?php

return [
    'images' => [
        'max_uploads' => 10,
        'max_filesize' => 2048,
        'ignore_max_count' => false,
        'thumb_width' => 320,
        'thumb_height' => 180,
        'mimetypes' => [
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
        ]
    ],
    'gallery' => [
        'max_images' => 10
    ],
    'pagination' => [
        'items_per_page' => 10,
        'max_items_per_page' => 20,
    ]
];
