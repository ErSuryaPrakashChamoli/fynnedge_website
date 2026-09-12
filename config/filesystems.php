<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        /*
         * Every admin image upload (banners, lender logos, testimonials, company
         * photos) lands here and is read back with Storage::disk('public').
         *
         * `url` is deliberately a root-relative path, NOT APP_URL.'/storage'.
         * Building it from APP_URL bakes whatever that env value happens to be
         * into every <img src> in the page: a server whose .env still carried
         * the local value served every uploaded image as
         * http://localhost:8000/storage/... — broken for every visitor, and
         * cached into config:cache so it survived the .env being corrected.
         * A relative URL follows whatever host, port and scheme the request
         * arrived on, so the same database works on localhost, staging and
         * production. The few tags that must carry an absolute URL (og:image,
         * twitter:image, JSON-LD) are made absolute at the point of use — see
         * App\Support\Seo\SeoDefaults::absolute().
         *
         * `throw` is deliberately true: with it false, a write that fails on the
         * server — a storage/app/public that php-fpm cannot write to, or a path
         * masked by a container volume mount — returned false instead of raising.
         * Filament treats that blank return as "no file", so the record saved with
         * an empty *_path and the admin saw a successful save with no image and no
         * error. A failed upload must be loud.
         */
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => '/storage',
            'visibility' => 'public',
            'throw' => true,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
