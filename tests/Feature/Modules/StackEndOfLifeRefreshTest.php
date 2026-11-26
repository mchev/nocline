<?php

use App\Modules\Hosts\Services\EndOfLifeCatalog;
use Illuminate\Support\Facades\Http;

it('refreshes the stack eol catalog from endoflife.date', function () {
    config(['nocline.stack_eol_check' => true]);

    Http::fake([
        'https://endoflife.date/api/v1/products/php/' => Http::response([
            'result' => [
                'releases' => [
                    [
                        'name' => '8.5',
                        'isEol' => false,
                        'isLts' => false,
                        'isMaintained' => true,
                        'eolFrom' => '2029-12-31',
                        'latest' => ['name' => '8.5.13', 'date' => '2026-11-19'],
                    ],
                ],
            ],
        ]),
        'https://endoflife.date/api/v1/products/nodejs/*' => Http::response(['result' => ['releases' => []]]),
        'https://endoflife.date/api/v1/products/mysql/*' => Http::response(['result' => ['releases' => []]]),
        'https://endoflife.date/api/v1/products/mariadb/*' => Http::response(['result' => ['releases' => []]]),
        'https://endoflife.date/api/v1/products/postgresql/*' => Http::response(['result' => ['releases' => []]]),
        'https://endoflife.date/api/v1/products/redis/*' => Http::response(['result' => ['releases' => []]]),
        'https://endoflife.date/api/v1/products/nginx/*' => Http::response(['result' => ['releases' => []]]),
        'https://endoflife.date/api/v1/products/docker-engine/*' => Http::response(['result' => ['releases' => []]]),
    ]);

    $this->artisan('nocline:stack-eol:refresh')->assertSuccessful();

    $catalog = app(EndOfLifeCatalog::class)->catalog();

    expect($catalog)->not->toBeNull()
        ->and($catalog['products']['php']['releases'][0]['name'])->toBe('8.5');
});
