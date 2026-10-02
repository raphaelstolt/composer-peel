<?php

declare(strict_types=1);

return [
    'peel' => [
        'sections' => ['require-dev', 'scripts'],
    ],
    'release' => [
        'backup' => [
            'enabled' => false,
            'path' => 'my-backup.json',
        ],
        'files' => [
            'my-file.txt',
            'my-dir/',
        ],
        'commit_message' => 'My before message',
    ],
    'rollback' => [
        'commit_message' => 'My after message',
    ],
];
