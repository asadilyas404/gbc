<?php

namespace Tests\Unit;

use App\Http\Controllers\Vendor\FoodController;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\Query\Expression;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class FoodPartnerPriceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = new Application(dirname(__DIR__, 2));
        $app->instance('config', new Repository(['app' => ['locale' => 'en']]));
        $translator = new Translator(new ArrayLoader(), 'en');
        $app->instance('translator', $translator);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        Validator::swap(new Factory($translator, $app));

        $guard = Mockery::mock();
        $guard->shouldReceive('check')->andReturn(true);
        $guard->shouldReceive('user')->andReturn((object) ['restaurant' => (object) [
            'id' => 1, 'food_section' => 1, 'restaurant_model' => 'commission',
        ]]);
        $auth = Mockery::mock(AuthFactory::class);
        $auth->shouldReceive('guard')->with('vendor_employee')->andReturn($guard);
        $app->instance(AuthFactory::class, $auth);

        $responses = Mockery::mock(ResponseFactory::class);
        $responses->shouldReceive('json')->andReturnUsing(function ($data, $status = 200) {
            return new JsonResponse($data, $status);
        });
        $app->instance(ResponseFactory::class, $responses);
        DB::swap(Mockery::mock());
        DB::shouldReceive('raw')->andReturnUsing(function ($value) {
            return new Expression($value);
        });
        Log::swap(Mockery::mock()->shouldIgnoreMissing());
        if (!class_exists('Log', false)) {
            class_alias(Log::class, 'Log');
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Application::setInstance(null);
        parent::tearDown();
    }

    /** @dataProvider savePaths */
    public function testLegacyFormPriceIsSavedAlongsideCanonicalZeroAndBlankPrices($method): void
    {
        $this->mockFood();

        // PHP preserves quotes within bracketed form names as part of the key.
        parse_str("partner_price[56211426112147]['price']=2.2&partner_price[56211426112147][enable]=on"
            . '&partner_price[2][price]=0&partner_price[3][price]=', $payload);
        $payload['partner_price'][3]['price'] = null; // ConvertEmptyStringsToNull middleware.
        $payload['partner_price'][3]["'price'"] = '9.9'; // Canonical null takes precedence.
        $response = $this->save($method, $payload['partner_price']);

        $this->assertSame([], $response->getData(true));
        $this->assertSame([
            ['partner_id' => 56211426112147, 'price' => '2.2', 'enable' => 'on'],
            ['partner_id' => 2, 'price' => '0', 'enable' => 'off'],
            ['partner_id' => 3, 'price' => null, 'enable' => 'off'],
        ], $this->savedPartnerPrices());
    }

    /** @dataProvider optionalPartnerPrices */
    public function testPartnerPricesAreOptional($method, $prices): void
    {
        $this->mockFood();
        $this->assertSame([], $this->save($method, $prices)->getData(true));
        $this->assertSame([], $this->savedPartnerPrices());
    }

    /** @dataProvider invalidPartnerPrices */
    public function testInvalidPartnerPricesReturnValidationErrorsBeforePersistence($method, $prices, $code): void
    {
        $food = Mockery::mock('overload:App\\Models\\Food');
        $food->shouldNotReceive('max', 'find', 'save');
        $response = $this->save($method, $prices);
        $this->assertContains($code, array_column($response->getData(true)['errors'], 'code'));
    }

    public function savePaths(): array
    {
        return [['store'], ['update']];
    }

    public function optionalPartnerPrices(): array
    {
        return [['store', null], ['store', []], ['update', null], ['update', []]];
    }

    public function invalidPartnerPrices(): array
    {
        $cases = [];
        foreach (['store', 'update'] as $method) {
            foreach ([
                'missing price' => [[1 => ['enable' => 'on']], 'partner_price.1.price'],
                'nonnumeric price' => [[1 => ['price' => 'invalid']], 'partner_price.1.price'],
                'negative price' => [[1 => ['price' => '-1']], 'partner_price.1.price'],
                'scalar row' => [[1 => '2.2'], 'partner_price.1'],
                'scalar collection' => ['2.2', 'partner_price'],
            ] as $name => $case) {
                $cases[$method . ' ' . $name] = [$method, $case[0], $case[1]];
            }
        }
        return $cases;
    }

    private function save($method, $prices): JsonResponse
    {
        $request = Request::create('/vendor/food/' . $method, 'POST', [
            'name' => ['Broasted Chicken (2Pcs)'],
            'lang' => ['default'],
            'description' => [null],
            'category_id' => 17,
            'sub_category_id' => 18,
            'price' => '2.2',
            'discount' => 0,
            'discount_type' => 'percent',
            'veg' => 0,
            'available_time_starts' => '00:00',
            'available_time_ends' => '23:59',
            'stock_type' => 'unlimited',
            'partner_price' => $prices,
        ]);
        if ($prices === null) {
            $request->request->remove('partner_price');
        }
        $controller = new FoodController();
        return $method === 'store' ? $controller->store($request) : $controller->update($request, 1);
    }

    private function mockFood(): void
    {
        $food = Mockery::mock('overload:App\\Models\\Food');
        $food->id = 1;
        $food->slug = 'broasted-chicken';
        $food->image = 'def.png';
        $food->shouldReceive('max')->with('id')->andReturn(0);
        $food->shouldReceive('find')->with(1)->andReturn($food);
        $food->shouldReceive('save')->once()->andReturn(true);
        $relation = Mockery::mock();
        $relation->shouldReceive('sync')->with([])->times(3);
        $food->shouldReceive('tags', 'nutritions', 'allergies')->andReturn($relation);
    }

    private function savedPartnerPrices(): array
    {
        foreach (Mockery::getContainer()->getMocks() as $mock) {
            if ($mock instanceof \App\Models\Food && isset($mock->partner_price)) {
                return json_decode($mock->partner_price, true);
            }
        }
        $this->fail('No food model received partner prices.');
    }
}
