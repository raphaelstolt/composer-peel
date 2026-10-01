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
        'managed_files' => [
            'my-file.txt',
            'my-dir/',
        ],
    ],
    'git' => [
        'commit_messages' => [
            'release' => 'My before message',
            'after_release' => 'My after message',
        ],
    ],
];
