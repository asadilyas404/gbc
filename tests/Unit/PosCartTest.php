<?php

namespace Tests\Unit;

use App\Http\Controllers\Vendor\POSController;
use App\Services\PosCartService;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Fluent;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class PosCartTest extends TestCase
{
    private $session;
    private $addons;

    protected function setUp(): void
    {
        $app = new Application(dirname(__DIR__, 2));
        $app->instance('config', new Repository(['app' => ['locale' => 'en'],
            'currency_symbol' => 'OMR', 'currency_symbol_position' => 'right', 'round_up_to_digit' => 3]));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        $translator = new Translator(new ArrayLoader(), 'en');
        $app->instance('translator', $translator);
        Validator::swap(new Factory($translator, $app));
        $this->session = new Store('pos-cart-test', new ArraySessionHandler(120));
        $app->instance('session', $this->session);
        $responses = Mockery::mock(ResponseFactory::class);
        $responses->shouldReceive('json')->andReturnUsing(function ($data, $status = 200) {
            return new JsonResponse($data, $status);
        });
        $app->instance(ResponseFactory::class, $responses);
        // Return IDs in a different order than the cart to test ID-based stock quantities.
        $this->addons = collect([
            new Fluent(['id' => 2, 'name' => 'Cheese', 'price' => 0.5, 'stock_type' => 'limited', 'addon_stock' => 100]),
            new Fluent(['id' => 1, 'name' => 'Sauce', 'price' => 0.25, 'stock_type' => 'limited', 'addon_stock' => 100]),
        ]);
        $selected = [];
        $addons = Mockery::mock('alias:App\\Models\\AddOn');
        $addons->shouldReceive('whereIn')->andReturnUsing(function ($column, $ids) use (&$selected, $addons) {
            $selected = $ids;
            return $addons;
        });
        $addons->shouldReceive('get')->andReturnUsing(function () use (&$selected) {
            return $this->addons->filter(function ($addon) use ($selected) { return in_array($addon->id, $selected); })->values();
        });
        $food = Mockery::mock('alias:App\\Models\\Food');
        $food->shouldReceive('select')->andReturnSelf();
        $food->shouldReceive('findOrFail')->with(10)->andReturn(new Fluent([
            'id' => 10, 'name' => 'Pizza', 'price' => 5, 'partner_price' => '[]',
            'variations' => '[]', 'discount' => 0, 'discount_type' => 'percent',
        ]));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Application::setInstance(null);
        parent::tearDown();
    }

    private function item(): array
    {
        return ['id' => 10, 'price' => 5, 'discount' => 0, 'quantity' => 1, 'addon_price' => 999,
            'add_ons' => [1], 'add_on_qtys' => [1], 'add_on_quantity_modes' => ['food'],
            'variations' => [['addons' => [['id' => 1, 'price' => 50, 'quantity' => 1, 'quantity_mode' => 'food'],
                ['id' => 2, 'price' => 100, 'quantity' => 1, 'quantity_mode' => 'food']]]]];
    }

    public function testBothAddonKindsFollowFoodQuantityAndCataloguePrices(): void
    {
        $service = new PosCartService();
        $item = $this->item();
        foreach ([3, 5, 2] as $quantity) {
            $item['quantity'] = $quantity;
            $item = $service->prepareItem($item);
            $this->assertSame([$quantity], $item['add_on_qtys']);
            $this->assertSame([$quantity, $quantity], array_column($item['variations'][0]['addons'], 'quantity'));
            $this->assertSame([0.25, 0.5], array_column($item['variations'][0]['addons'], 'price'));
            $this->assertSame((float) $quantity, $item['addon_price']);
            $this->assertSame([1 => 2 * $quantity, 2 => $quantity], $service->addonQuantities($item));
        }
    }

    public function testCartQuantityEndpointUpdatesAllAddonTotals(): void
    {
        $this->session->put('cart', collect([$this->item()]));
        foreach ([4, 2] as $quantity) {
            $request = Request::create('/pos/update-quantity', 'POST', ['key' => 0, 'quantity' => $quantity]);
            $request->setLaravelSession($this->session);
            $response = (new POSController())->updateQuantity($request);
            $this->assertSame([], $response->getData(true));
            $item = $this->session->get('cart')[0];
            $this->assertSame([$quantity], $item['add_on_qtys']);
            $this->assertSame([$quantity, $quantity], array_column($item['variations'][0]['addons'], 'quantity'));
            $this->assertSame((float) $quantity, $item['addon_price']);
        }
    }

    public function testInsufficientAddonStockLeavesCartUnchanged(): void
    {
        $this->addons[1]->addon_stock = 5;
        $original = [$this->item()];
        $this->session->put('cart', collect($original));
        // Sauce appears in two groups, so four foods need eight sauces.
        $request = Request::create('/pos/update-quantity', 'POST', ['key' => 0, 'quantity' => 4]);
        $request->setLaravelSession($this->session);
        $response = (new POSController())->updateQuantity($request);
        $this->assertSame('stock_out', $response->getData(true)['data']);
        $this->assertSame(1, $response->getData(true)['id']);
        $this->assertSame($original, $this->session->get('cart')->all());
    }

    public function testTwoFoodsCanHaveOneAddonAndCataloguePriceIsUsed(): void
    {
        $request = Request::create('/pos/add-to-cart', 'POST', [
            'id' => 10, 'quantity' => 2, 'addon_id' => [1], 'addon-quantity1' => 1,
            'addon-price1' => -100, 'product_discount' => 0, 'product_discount_type' => 'amount',
        ]);
        $request->setLaravelSession($this->session);
        $response = (new POSController())->addToCart($request);
        $this->assertIsArray($response->getData(true)['data']);
        $item = $this->session->get('cart')[0];
        $this->assertSame([1], $item['add_on_qtys']);
        $this->assertSame(['manual'], $item['add_on_quantity_modes']);
        $this->assertSame(0.25, $item['addon_price']);
    }

    public function testPricePreviewUsesSelectedAddonQuantityAndCataloguePrices(): void
    {
        $request = Request::create('/pos/variant-price', 'POST', [
            'id' => 10, 'quantity' => 2, 'addon_id' => [1], 'addon-quantity1' => 1,
            'addon-price1' => -100, 'product_discount' => 0, 'product_discount_type' => 'amount',
        ]);
        $request->setLaravelSession($this->session);
        $data = (new POSController())->variant_price($request)->getData(true);
        $this->assertArrayNotHasKey('error', $data);
        $this->assertSame(10.25, (float) $data['price']);
        $this->assertFalse($this->session->has('cart'));
    }

    public function testPricePreviewRejectsDiscountAboveUnitPrice(): void
    {
        $request = Request::create('/pos/variant-price', 'POST', [
            'id' => 10, 'quantity' => 3, 'product_discount' => 6, 'product_discount_type' => 'amount',
        ]);
        $request->setLaravelSession($this->session);
        $data = (new POSController())->variant_price($request)->getData(true);
        $this->assertSame('discount_error', $data['error']);
        $this->assertArrayNotHasKey('price', $data);
    }

    public function testManualAddonQuantitiesSurviveFoodQuantityChanges(): void
    {
        $item = $this->item();
        $item['quantity'] = 2;
        $item['add_on_quantity_modes'] = ['manual'];
        $item['variations'][0]['addons'][0]['quantity_mode'] = 'manual';
        $item['variations'][0]['addons'][1]['quantity_mode'] = 'manual';
        $this->session->put('cart', collect([$item]));
        foreach ([4, 2] as $quantity) {
            $request = Request::create('/pos/update-quantity', 'POST', ['key' => 0, 'quantity' => $quantity]);
            $request->setLaravelSession($this->session);
            $response = (new POSController())->updateQuantity($request);
            $this->assertSame([], $response->getData(true));
            $item = $this->session->get('cart')[0];
            $this->assertSame([1], $item['add_on_qtys']);
            $this->assertSame([1, 1], array_column($item['variations'][0]['addons'], 'quantity'));
            $this->assertSame(1.0, $item['addon_price']);
        }
    }

    public function testLegacyIndependentQuantitiesRemainIndependent(): void
    {
        $item = $this->item();
        $item['quantity'] = 2;
        unset($item['add_on_quantity_modes']);
        foreach ($item['variations'][0]['addons'] as &$addon) {
            unset($addon['quantity_mode']);
        }
        unset($addon);
        $item = (new PosCartService())->withQuantity($item, 4);
        $this->assertSame([1], $item['add_on_qtys']);
        $this->assertSame([1, 1], array_column($item['variations'][0]['addons'], 'quantity'));
    }

    /** @dataProvider invalidQuantities */
    public function testInvalidAddonQuantityCannotBeAdded($quantity): void
    {
        $request = Request::create('/pos/add-to-cart', 'POST', [
            'id' => 10, 'quantity' => 2, 'addon_id' => [1], 'addon-quantity1' => $quantity,
            'product_discount' => 0, 'product_discount_type' => 'amount',
        ]);
        $request->setLaravelSession($this->session);
        $data = (new POSController())->addToCart($request)->getData(true);
        $this->assertSame('discount_error', $data['data']);
        $this->assertFalse($this->session->has('cart'));
    }

    /** @dataProvider invalidQuantities */
    public function testInvalidQuantityLeavesCartUnchanged($quantity): void
    {
        $original = [$this->item()];
        $this->session->put('cart', collect($original));
        $request = Request::create('/pos/update-quantity', 'POST', ['key' => 0, 'quantity' => $quantity]);
        $request->setLaravelSession($this->session);
        $response = (new POSController())->updateQuantity($request);
        $this->assertSame('discount_error', $response->getData(true)['data']);
        $this->assertSame($original, $this->session->get('cart')->all());
    }

    public function invalidQuantities(): array
    {
        return [[0], [-1], [1.5], ['bad']];
    }
}
