<?php

namespace Tests\Unit;

use App\Http\Controllers\Vendor\POSController;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class PosCanceledOrderTest extends TestCase
{
    private $logPath;
    private $previousLog;

    protected function tearDown(): void
    {
        Mockery::close();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Application::setInstance(null);
        ini_set('error_log', $this->previousLog);
        unlink($this->logPath);
        parent::tearDown();
    }

    /** @dataProvider orderCases */
    public function testSavingUsesItemCancellationState($editing, $mode, $flags, $expectedStatus, $expectedPayment): void
    {
        $this->logPath = tempnam(sys_get_temp_dir(), 'pos-test-');
        $this->previousLog = ini_set('error_log', $this->logPath);
        $app = new Application(dirname(__DIR__, 2));
        $app->instance('config', new Repository(['app' => ['locale' => 'en']]));
        $app->instance('translator', new Translator(new ArrayLoader(), 'en'));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        $guard = Mockery::mock()->shouldReceive('id')->andReturn(7)->getMock();
        $auth = Mockery::mock(AuthFactory::class);
        $auth->shouldReceive('guard')->andReturn($guard);
        $app->instance('auth', $auth);
        $app->instance(AuthFactory::class, $auth);
        $responses = Mockery::mock(ResponseFactory::class);
        $responses->shouldReceive('json')->andReturnUsing(function ($data) {
            return new JsonResponse($data);
        });
        $app->instance(ResponseFactory::class, $responses);
        $events = Mockery::mock(\Illuminate\Contracts\Events\Dispatcher::class);
        $events->shouldReceive('dispatch');
        $app->instance('events', $events);
        $app->instance(\Illuminate\Contracts\Events\Dispatcher::class, $events);

        $shift = Mockery::mock('alias:App\\Models\\ShiftSession');
        $shift->shouldReceive('current', 'where')->andReturnSelf();
        $shift->shouldReceive('first')->andReturn((object) ['session_id' => 9]);
        $restaurant = new PosCanceledOrderFixture([
            'id' => 1, 'zone_id' => 1, 'tax' => 0, 'self_delivery_system' => 1,
            'restaurant_model' => 'commission', 'restaurant_sub' => null,
            'name' => 'Test restaurant', 'status' => 1, 'delivery_time' => '10-20-min',
        ]);
        $guard->shouldReceive('check')->andReturn(true);
        $guard->shouldReceive('user')->andReturn((object) ['restaurant' => $restaurant]);

        $database = Mockery::mock();
        DB::swap($database);
        $database->shouldReceive('beginTransaction', 'commit')->once();
        $branch = Mockery::mock();
        $branch->shouldReceive('where')->andReturnSelf();
        $branch->shouldReceive('first')->andReturn((object) ['orders_date' => date('Y-m-d')]);
        $database->shouldReceive('table')->with('tbl_soft_branch')->andReturn($branch);
        $settings = Mockery::mock('alias:App\\Models\\BusinessSetting');
        $settings->shouldReceive('where')->andReturnSelf();
        $settings->shouldReceive('first')->andReturn((object) ['value' => 0]);

        $order = Mockery::mock('overload:App\\Models\\Order');
        $order->order_status = 'cooking';
        $order->printed = 1;
        $order->details = collect([]);
        $order->shouldReceive('find')->with(101)->andReturn($order);
        $order->shouldReceive('whereDate')->andReturnSelf();
        $order->shouldReceive('where')->andReturnSelf();
        $order->shouldReceive('exists')->andReturn(false);
        $order->shouldReceive('count')->andReturn(0);
        $order->shouldReceive('save')->atLeast()->once()->andReturn(true);
        $order->shouldReceive('load')->andReturnSelf();
        if ($editing) {
            $order->id = 101;
        }
        $details = Mockery::mock('alias:App\\Models\\OrderDetail');
        $details->shouldReceive('where')->andReturnSelf();
        $details->shouldReceive('get')->andReturn(collect([]));
        $details->shouldReceive('delete')->times($editing ? 1 : 0);
        $details->shouldReceive('insert')->once()->with(Mockery::on(function ($rows) use ($flags) {
            return array_column($rows, 'is_deleted') === $flags;
        }))->andReturn(true);
        $additional = Mockery::mock('alias:App\\Models\\PosOrderAdditionalDtl');
        $additional->shouldReceive('firstOrNew')->andReturnSelf();
        $additional->shouldReceive('save')->once();
        $kitchen = Mockery::mock('alias:App\\Models\\KitchenOrderStatusLog');
        $kitchen->shouldReceive('create')->times($editing ? 0 : 1);
        $printer = Mockery::mock('overload:App\\Http\\Controllers\\PrintController');
        $printer->shouldReceive('printOrderKitchen', 'printOrder')->andReturn(null);
        $toastr = Mockery::mock();
        $toastr->shouldReceive('success')->once();
        \Brian2694\Toastr\Facades\Toastr::swap($toastr);

        $food = Mockery::mock('alias:App\\Models\\Food');
        $food->shouldReceive('find')->andReturnUsing(function () use ($restaurant) {
            $product = new PosCanceledOrderFixture([
                'category_ids' => '[]', 'add_ons' => '[]', 'variations' => '[]', 'tax_type' => 'percent',
                'restaurant' => $restaurant,
            ]);
            return $product;
        });
        $addons = Mockery::mock('alias:App\\Models\\AddOn');
        $addons->shouldReceive('whereIn', 'active')->andReturnSelf();
        $addons->shouldReceive('get')->andReturn(collect([]));

        $cart = [];
        foreach ($flags as $flag) {
            $cart[] = [
                'id' => 1, 'quantity' => 1, 'price' => 10, 'discount' => 0,
                'is_deleted' => $flag, 'variations' => [], 'add_ons' => [], 'add_on_qtys' => [],
            ];
        }
        if (!$flags) {
            $cart['tax'] = 0; // Cart metadata alone must not count as canceled items.
        }
        $session = new Store('pos-test', new ArraySessionHandler(120));
        $session->put('cart', collect($cart));
        if ($editing) {
            $session->put('editing_order_id', 101);
        }
        $app->instance('session', $session);
        $request = Request::create('/restaurant-panel/pos/order', 'POST', [
            'order_draft' => $mode, 'cash_paid' => 20,
        ], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $request->setLaravelSession($session);
        $app->instance('request', $request);

        $response = (new POSController())->place_order($request);

        $this->assertTrue($response->getData(true)['success']);
        $savedId = $response->getData(true)['order_id'];
        $saved = null;
        foreach (Mockery::getContainer()->getMocks() as $mock) {
            if ($mock instanceof \App\Models\Order && isset($mock->id) && $mock->id == $savedId) {
                $saved = $mock;
            }
        }
        $this->assertNotNull($saved);
        $this->assertSame($expectedStatus, $saved->order_status);
        $this->assertSame($expectedPayment, $saved->payment_status);
    }

    public function orderCases(): array
    {
        $cases = [];
        foreach ([false, true] as $editing) {
            foreach (['draft', 'final'] as $mode) {
                $label = ($editing ? 'update' : 'create') . ' ' . $mode;
                $cases[$label . ' canceled'] = [$editing, $mode, ['Y', 'Y'], 'canceled', 'paid'];
                $cases[$label . ' mixed'] = [$editing, $mode, ['Y', 'N'], 'cooking', $mode === 'draft' ? 'unpaid' : 'paid'];
            }
        }
        $cases['no item rows'] = [true, 'draft', [], 'cooking', 'unpaid'];
        return $cases;
    }
}

class PosCanceledOrderFixture extends \Illuminate\Support\Fluent
{
    public function load($relation) { return $this; }
    public function increment($column, $amount = 1) {}
    public function rating() { return $this; }
    public function first() { return null; }
}
