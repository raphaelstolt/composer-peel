<?php

return [
    'source' => 'config_file',
    'config_file' => [
        'path' => 'bin/composer-peel',
        'pattern' => "/APPLICATION_VERSION\s*=\s*['\"]([^'\"]+)['\"]/",
    ],

    'changelog' => 'CHANGELOG.md',

    'date_format' => 'Y-m-d',

    'scheme' => 'major.minor.patch',

    'sections' => [
        'feat' => 'Added',
        'fix' => 'Fixed',
        'perf' => 'Changed',
        'refactor' => 'Changed',
    ],

    'bumps' => [
        'feat' => 'minor',
        'fix' => 'patch',
        'perf' => 'patch',
    ],

    'git' => [
        'remote' => 'origin',
        'branch' => null,
    ],
];