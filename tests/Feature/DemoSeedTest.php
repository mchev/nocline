<?php

use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Support\WordPressSiteMeta;
use App\Modules\Hosts\Models\Host;
use App\Modules\Hosts\Support\HostMetaPresenter;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Artisan;

it('seeds a rich demo dataset with providers stack eol and object storage', function () {
    Artisan::call('nocline:demo:seed');

    $dbHost = Host::query()->where('name', 'acme-db-01')->firstOrFail();
    $presentation = HostMetaPresenter::forHost($dbHost);

    expect($presentation['provider'])->toBe('ovh')
        ->and($presentation['provider_region'])->toBe('gra')
        ->and($presentation['stack'])->toHaveKey('php', '8.1.33')
        ->and($presentation['stack_warnings'])->not->toBeEmpty();

    expect(Check::query()->where('name', DemoSeeder::CHECK_SHOP)->exists())->toBeTrue()
        ->and(Check::query()->where('name', DemoSeeder::CHECK_SHOP_HEALTH)->exists())->toBeTrue()
        ->and(WordPressSiteMeta::version(Check::query()->where('name', 'blog.acme.com')->firstOrFail()))->toBe('6.6.2');

    $objectStorage = Check::query()->whereJsonContains('tags', 'object-storage')->count();

    expect($objectStorage)->toBeGreaterThanOrEqual(2);

    expect(file_exists(storage_path('app/'.DemoSeeder::MANIFEST)))->toBeTrue();

    $manifest = json_decode(
        file_get_contents(storage_path('app/'.DemoSeeder::MANIFEST)),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($manifest['screenshot']['check_blog_id'])->toBeInt()
        ->and($manifest['screenshot']['host_db_id'])->toBeInt();
});
