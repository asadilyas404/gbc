<?php

namespace Tests\Unit;

use App\Jobs\SyncBranchesRestaurantsJob;
use Illuminate\Config\Repository;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\TestCase;

class RestaurantTranslationSyncTest extends TestCase
{
    private $database;

    protected function setUp(): void
    {
        parent::setUp();
        $app = new Application(dirname(__DIR__, 2));
        $app->instance('config', new Repository([
            'constants' => ['branch_id' => 12],
            'services' => ['live_server' => ['url' => 'https://sync.example.test', 'token' => 'test-token']],
        ]));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        $capsule = new Manager($app);
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'oracle');
        DB::swap($capsule->getDatabaseManager());
        $this->database = DB::connection('oracle');
        $schema = $this->database->getSchemaBuilder();
        $schema->create('restaurants', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('name');
            $table->dateTime('updated_at')->nullable();
        });
        $schema->create('translations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('translationable_type');
            $table->integer('translationable_id');
            $table->string('locale');
            $table->string('key');
            $table->string('value');
            $table->timestamps();
            $table->unique(['translationable_type', 'translationable_id', 'locale', 'key'], 'translations_unique_trans');
        });

        Http::swap(new Factory());
        Log::swap(Mockery::mock()->shouldIgnoreMissing());
        Artisan::swap(Mockery::mock());
        Artisan::shouldReceive('call')->with('config:cache')->twice()->andReturn(0);
    }

    protected function tearDown(): void
    {
        $this->database->disconnect();
        Mockery::close();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Application::setInstance(null);
        parent::tearDown();
    }

    /** @dataProvider translationCases */
    public function testSyncUsesTranslationIdentityAndPreservesLocalIds($alreadyTranslated, $incomingIdUsed): void
    {
        $translation = [
            'id' => 1329,
            'translationable_type' => 'App\\Models\\Restaurant',
            'translationable_id' => 12,
            'locale' => 'ar',
            'key' => 'address',
            'value' => 'صحار',
            'created_at' => null,
            'updated_at' => null,
        ];
        if ($alreadyTranslated) {
            $this->database->table('translations')->insert(array_merge($translation, [
                'id' => 73, 'value' => 'Old address',
            ]));
        }
        if ($incomingIdUsed) {
            $this->database->table('translations')->insert(array_merge($translation, [
                'translationable_id' => 99, 'value' => 'Another restaurant',
            ]));
        }

        $data = array_fill_keys([
            'branches', 'restaurants', 'partners', 'banks', 'vendors',
            'pos_discount_types', 'unpaid_orders', 'business_settings',
        ], []);
        $data['restaurants'][] = [
            'restaurant' => ['id' => 12, 'name' => 'Sohar', 'updated_at' => '2026-11-16 10:00:00'],
            'translations' => [$translation],
        ];
        Http::fake([
            'https://sync.example.test/branches-restaurants/get-data*' => Http::response(['success' => true, 'data' => $data]),
            'https://sync.example.test/branches-restaurants/update-sync-state' => Http::response(['success' => true]),
        ]);

        $job = new SyncBranchesRestaurantsJob();
        $job->handle();
        $job->handle(); // Repeated syncs must update the same local row.

        $rows = $this->database->table('translations')->where('translationable_id', 12)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('صحار', $rows->first()->value);
        if ($alreadyTranslated) {
            $this->assertEquals(73, $rows->first()->id);
        }
        if ($incomingIdUsed) {
            $unrelated = $this->database->table('translations')->where('id', 1329)->first();
            $this->assertEquals(99, $unrelated->translationable_id);
            $this->assertSame('Another restaurant', $unrelated->value);
        }
        $this->assertEquals(1 + (int) $incomingIdUsed, $this->database->table('translations')->count());
        $this->assertSame('Sohar', $this->database->table('restaurants')->where('id', 12)->value('name'));
        Log::shouldNotHaveReceived('error');
        Http::assertSentCount(4);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://sync.example.test/branches-restaurants/update-sync-state'
                && isset($request['restaurants_last_synced_at']);
        });
    }

    public function translationCases(): array
    {
        return [
            'existing translation with different local ID' => [true, false],
            'existing translation and incoming ID belongs to another restaurant' => [true, true],
            'new translation' => [false, false],
            'new translation and incoming ID belongs to another restaurant' => [false, true],
        ];
    }
}
