<?php

return [
    'private_disks' => array_values(array_filter(array_map('trim', explode(',', env('ATTACHMENT_PRIVATE_DISKS', 'local'))))),
    'max_kb' => (int) env('ATTACHMENT_MAX_KB', 10240),
    'mimes' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('ATTACHMENT_MIMES', 'pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx')),
    ))),
];
