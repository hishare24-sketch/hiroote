<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Providers\DomainServiceProvider;

return [
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    DomainServiceProvider::class,
];
