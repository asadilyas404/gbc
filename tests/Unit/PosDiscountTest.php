<?php

namespace Tests\Unit;

use App\Http\Controllers\Vendor\POSController;
use App\Services\PosDiscountService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Compilers\BladeCompiler;
use Mockery;
use PHPUnit\Framework\TestCase;

class PosDiscountTest extends TestCase
{
    private $database;
    private $service;
    private $app;
    private $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application(dirname(__DIR__, 2));
        $this->app->instance('config', new Repository(['app' => ['locale' => 'en']]));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);
        $translator = new Translator(new ArrayLoader(), 'en');
        $this->app->instance('translator', $translator);
        Validator::swap(new Factory($translator, $this->app));

        $capsule = new Manager($this->app);
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        DB::swap($capsule->getDatabaseManager());
        $this->database = $capsule->getConnection();
        Schema::create('business_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('value')->nullable();
        });
        Schema::create('tbl_pos_discount_types', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('name');
            $table->integer('status')->default(1);
            $table->decimal('default_discount', 15, 3);
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->bigInteger('id')->primary();
        });
        require_once dirname(__DIR__, 2).'/database/migrations/2026_11_17_000001_add_pos_order_discount_fields.php';
        (new \AddPosOrderDiscountFields())->up();
        $this->database->table('tbl_pos_discount_types')->insert([
            ['id' => 1, 'name' => 'Product', 'status' => 1, 'default_discount' => 10, 'is_order_discount' => 0, 'max_discount' => null],
            ['id' => 2, 'name' => 'Bill', 'status' => 1, 'default_discount' => 20, 'is_order_discount' => 1, 'max_discount' => 5],
            ['id' => 3, 'name' => 'Inactive bill', 'status' => 0, 'default_discount' => 30, 'is_order_discount' => 1, 'max_discount' => 5],
            ['id' => 4, 'name' => 'Inactive product', 'status' => 0, 'default_discount' => 10, 'is_order_discount' => 0, 'max_discount' => null],
        ]);
        $this->session = new Store('pos-discount-test', new ArraySessionHandler(120));
        $this->app->instance('session', $this->session);
        $redirect = Mockery::mock();
        $redirect->shouldReceive('back')->andReturn(new RedirectResponse('/pos'));
        $this->app->instance('redirect', $redirect);
        Toastr::swap(Mockery::mock()->shouldIgnoreMissing());
        $this->service = new PosDiscountService();
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

    public function testCataloguesSeparateScopesAndExcludeInactiveDiscounts(): void
    {
        $this->assertSame([1], $this->service->productDiscounts()->pluck('id')->all());
        $this->assertSame([2], $this->service->orderDiscounts()->pluck('id')->all());
        $this->service->validateProductSelection(1);
        $this->service->validateProductSelection(null);
    }

    /** @dataProvider invalidProductIds */
    public function testProductSelectionRejectsWrongScopeInactiveAndMissingDiscounts($id): void
    {
        $this->expectException(ValidationException::class);
        $this->service->validateProductSelection($id);
    }

    public function invalidProductIds(): array { return [[2], [3], [4], [999]]; }

    /** @dataProvider amountCases */
    public function testAmountUsesPercentageAndMaximum($price, $quantity, $addons, $maximum, $expected): void
    {
        $this->database->table('tbl_pos_discount_types')->where('id', 2)->update(['max_discount' => $maximum]);
        $cart = collect([$this->item(['price' => $price, 'quantity' => $quantity, 'addon_price' => $addons]),
            'order_discount_type_id' => 2, 'discount' => 99, 'discount_type' => 'amount', 'delivery_fee' => 100, 'tax' => 100]);
        $this->assertSame($expected, $this->service->amount($cart));
    }

    public function amountCases(): array
    {
        return [
            'under cap' => [10, 1, 0, 5, 2.0],
            'over cap' => [100, 1, 0, 5, 5.0],
            'equal cap' => [25, 1, 0, 5, 5.0],
            'quantity' => [10, 2, 0, 5, 4.0],
            'addons included' => [10, 1, 5, 5, 3.0],
            'zero maximum' => [100, 1, 0, 0, 0.0],
            'three decimal amount' => [1.234, 1, 0, 5, 0.247],
            'three decimal cap' => [100, 1, 0, 5.125, 5.125],
            'amount above 100' => [1000, 1, 0, 150, 150.0],
        ];
    }

    public function testCanceledItemsDoNotAffectBaseOrProductDiscountConflict(): void
    {
        $cart = collect([$this->item(), $this->item(['price' => 100, 'discount' => 30, 'is_deleted' => 'Y ']), 'order_discount_type_id' => 2]);
        $this->assertFalse($this->service->hasProductDiscount($cart));
        $this->assertSame(2.0, $this->service->amount($cart));
    }

    public function testProductDiscountBlocksOverallDiscount(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->amount(collect([$this->item(['discount' => 1]), 'order_discount_type_id' => 2]));
    }

    public function testDefaultFoodDiscountIsExcludedFromExtraDiscountBase(): void
    {
        $cart = collect([
            $this->item(['price' => 100, 'quantity' => 2, 'addon_price' => 5,
                'discount' => 20, 'discount_source' => 'food_default']),
            $this->item(['price' => 10, 'addon_price' => 5]), 'order_discount_type_id' => 2,
        ]);
        $this->assertTrue($this->service->hasProductDiscount($cart));
        $this->assertFalse($this->service->hasManualProductDiscount($cart));
        $this->assertSame(220.0, $this->service->subtotal($cart));
        $this->assertSame(15.0, $this->service->eligibleSubtotal($cart));
        $this->assertSame(3.0, $this->service->amount($cart));
        $cart[1] = $this->item(['price' => 100]);
        $this->assertSame(5.0, $this->service->amount($cart));
        $cart->forget(1);
        $this->assertSame(0.0, $this->service->amount($cart));
    }

    public function testExtraDiscountConfirmationRemovesDefaultDiscountsAndIncludesTheirFullSubtotal(): void
    {
        $this->session->put('cart', collect([
            $this->item(['discount' => 2, 'discount_source' => 'food_default']), $this->item(),
        ]));
        $this->update(['order_discount_type_id' => 2, 'confirm_default_discount_removal' => 1]);
        $cart = $this->session->get('cart');
        $this->assertEquals(2, $cart['order_discount_type_id']);
        $this->assertSame(4.0, $this->service->amount($cart));
        $this->assertSame(0, $cart[0]['discount']);
        $this->assertSame(0, $cart[0]['discountAmount']);
        $this->assertNull($cart[0]['pos_discount_type']);
        $this->assertSame('none', $cart[0]['discount_source']);
        Toastr::shouldNotHaveReceived('error');
    }

    public function testDefaultDiscountsCannotBeRemovedWithoutConfirmation(): void
    {
        $original = collect([$this->item(['discount' => 2, 'discount_source' => 'food_default'])]);
        $this->session->put('cart', $original);
        $this->update(['order_discount_type_id' => 2]);
        $this->assertSame($original, $this->session->get('cart'));
        Toastr::shouldHaveReceived('error')->once();
    }

    public function testConfirmedReplacementPreservesCanceledItemsAndCartMetadata(): void
    {
        $canceled = $this->item(['discount' => 2, 'discount_source' => 'food_default', 'is_deleted' => 'Y ']);
        $this->session->put('cart', collect([
            3 => $this->item(['discount' => 2, 'discount_source' => 'food_default', 'quantity' => 2, 'addon_price' => 3]),
            7 => $canceled, 'delivery_fee' => 9, 'tax' => 5,
        ]));
        $this->update(['order_discount_type_id' => 2, 'confirm_default_discount_removal' => 1]);
        $cart = $this->session->get('cart');
        $this->assertSame(0, $cart[3]['discount']);
        $this->assertSame($canceled, $cart[7]);
        $this->assertSame(9, $cart['delivery_fee']);
        $this->assertSame(5, $cart['tax']);
        $this->assertSame(4.6, $this->service->amount($cart));
    }

    public function testRejectedConfirmedReplacementLeavesAllDiscountsUntouched(): void
    {
        $original = collect([
            $this->item(['discount' => 2, 'discount_source' => 'food_default']),
            $this->item(['discount' => 1, 'discount_source' => 'manual']),
        ]);
        $this->session->put('cart', $original);
        $this->update(['order_discount_type_id' => 2, 'confirm_default_discount_removal' => 1]);
        $this->assertSame($original, $this->session->get('cart'));
        $this->assertSame(2, $original[0]['discount']);
        Toastr::shouldHaveReceived('error')->once();
    }

    public function testDefaultReportCategoryIsOnlyUsedWhenItIsAnActiveProductType(): void
    {
        $this->assertEquals(1, $this->service->defaultDiscountTypeId());
        $this->database->table('tbl_pos_discount_types')->where('id', 1)->update(['is_order_discount' => 1]);
        $this->assertNull($this->service->defaultDiscountTypeId());
    }

    /** @dataProvider restoredDiscountCases */
    public function testSavedSourceAndLegacyDefaultDiscountsRestoreCorrectly($food, $id, $amount, $source): void
    {
        $state = $this->service->restoreProductDiscount($food, $id, 10, $amount);
        $this->assertSame($source, $state['discount_source']);
        $this->assertSame($id, $state['pos_discount_type']);
    }

    public function restoredDiscountCases(): array
    {
        $native = ['discount' => 20, 'discount_type' => 'percent'];
        return [
            'legacy default id' => [$native, 1, 2, 'food_default'],
            'legacy no id' => [$native, null, 2, 'food_default'],
            'different manual id at same rate' => [$native, 5, 2, 'manual'],
            'different amount at legacy id' => [$native, 1, 1, 'manual'],
            'explicit manual source is authoritative' => [$native + ['pos_discount_source' => 'manual'], 1, 2, 'manual'],
            'saved default survives changed food definition' => [[
                'discount' => 0, 'discount_type' => 'percent', 'pos_discount_source' => 'food_default',
                'pos_discount_value' => 20, 'pos_discount_value_type' => 'percent',
            ], null, 2, 'food_default'],
            'no product discount' => [[], null, 0, 'none'],
        ];
    }

    /** @dataProvider invalidOverallIds */
    public function testOverallDiscountRejectsProductInactiveAndMissingIds($id): void
    {
        $this->expectException(ValidationException::class);
        $this->service->amount(collect([$this->item(), 'order_discount_type_id' => $id]));
    }

    public function invalidOverallIds(): array { return [[1], [3], [4], [999]]; }

    /** @dataProvider invalidDefinitions */
    public function testInvalidCatalogueValuesAreRejected($rate, $maximum): void
    {
        $this->database->table('tbl_pos_discount_types')->where('id', 2)->update(['default_discount' => $rate, 'max_discount' => $maximum]);
        $this->expectException(ValidationException::class);
        $this->service->amount(collect([$this->item(), 'order_discount_type_id' => 2]));
    }

    public function invalidDefinitions(): array { return [[-1, 5], [101, 5], [20, -1], [20, null], ['invalid', 5], [20, 'invalid']]; }

    public function testQuantityAndRemovalRecalculateAndCheckoutCanUseActualSubtotal(): void
    {
        $cart = collect([$this->item(), 'order_discount_type_id' => 2]);
        $this->assertSame(2.0, $this->service->amount($cart));
        $cart[0] = $this->item(['quantity' => 10]);
        $this->assertSame(5.0, $this->service->amount($cart));
        $this->assertSame(1.0, $this->service->amount($cart, 5));
        $cart[0] = $this->item(['is_deleted' => 'Y']);
        $this->assertSame(0.0, $this->service->amount($cart));
    }

    public function testApplyUsesCatalogueValuesAndIgnoresTamperedRequestAmounts(): void
    {
        $this->session->put('cart', collect([$this->item()]));
        $response = $this->update(['order_discount_type_id' => 2, 'discount' => 10000, 'type' => 'amount']);
        $this->assertSame(302, $response->getStatusCode());
        $cart = $this->session->get('cart');
        $this->assertEquals(2, $cart['order_discount_type_id']);
        $this->assertEquals(20, $cart['discount']);
        $this->assertSame('percent', $cart['discount_type']);
        $this->assertSame(2.0, $this->service->amount($cart));
    }

    /** @dataProvider rejectedApplications */
    public function testRejectedChangesLeaveSessionUntouched($payload, $productDiscount): void
    {
        $original = collect([$this->item(['discount' => $productDiscount]), 'delivery_fee' => 1]);
        $this->session->put('cart', $original);
        $this->update($payload);
        $this->assertSame($original, $this->session->get('cart'));
        $this->assertFalse(isset($original['order_discount_type_id']));
        Toastr::shouldHaveReceived('error')->once();
    }

    public function rejectedApplications(): array
    {
        return [[['order_discount_type_id' => 2], 1], [['order_discount_type_id' => 1], 0],
            [['order_discount_type_id' => 3], 0], [['order_discount_type_id' => 999], 0],
            [['order_discount_type_id' => -1], 0], [['order_discount_type_id' => 'abc'], 0],
            [['order_discount_type_id' => [2]], 0], [['discount' => 10, 'type' => 'amount'], 0]];
    }

    public function testEmptyCartCannotReceiveExtraDiscount(): void
    {
        $this->session->put('cart', collect(['tax' => 0]));
        $this->update(['order_discount_type_id' => 2]);
        $this->assertFalse($this->session->get('cart')->has('order_discount_type_id'));
        Toastr::shouldHaveReceived('error')->once();
    }

    public function testDiscountCanBeRemovedEvenFromConflictingLegacyCart(): void
    {
        $this->session->put('cart', collect([$this->item(['discount' => 1]), 'order_discount_type_id' => 2, 'discount' => 20]));
        $this->update(['order_discount_type_id' => null]);
        $cart = $this->session->get('cart');
        $this->assertNull($cart['order_discount_type_id']);
        $this->assertSame(0.0, $this->service->amount($cart));
        $this->assertSame(1, $cart[0]['discount']);
    }

    public function testLegacyDraftWithoutIdKeepsSavedAmount(): void
    {
        $cart = collect([$this->item(), 'discount' => 3.125, 'discount_type' => 'amount']);
        $this->assertSame(3.125, $this->service->amount($cart));
    }

    public function testLegacyAmountExceedingSubtotalIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->amount(collect([$this->item(), 'discount' => 11, 'discount_type' => 'amount']));
    }

    public function testMigrationPreservesExistingDiscountsAndSupportsRerun(): void
    {
        Schema::table('tbl_pos_discount_types', function (Blueprint $table) {
            $table->dropColumn(['is_order_discount', 'max_discount']);
        });
        $migration = new \AddPosOrderDiscountFields();
        $migration->up();
        $migration->up();
        $this->assertEquals(0, $this->database->table('tbl_pos_discount_types')->where('id', 1)->value('is_order_discount'));
        $this->assertNull($this->database->table('tbl_pos_discount_types')->where('id', 1)->value('max_discount'));
        $this->database->table('orders')->insert(['id' => 1, 'order_discount_type_id' => 2]);
        $this->assertEquals(2, $this->database->table('orders')->value('order_discount_type_id'));
        $migration->down();
        $this->assertFalse(Schema::hasColumn('orders', 'order_discount_type_id'));
        $this->assertTrue(Schema::hasColumn('tbl_pos_discount_types', 'is_order_discount'));
        $migration->up();
        $this->assertTrue(Schema::hasColumn('orders', 'order_discount_type_id'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @dataProvider productCartCases
     */
    public function testAddingOrEditingProductsEnforcesDiscountRules($id, $value, $type, $overall, $edit, $expectedError, $useFoodDefault = false): void
    {
        $responses = Mockery::mock(ResponseFactory::class);
        $responses->shouldReceive('json')->andReturnUsing(function ($data, $status = 200) { return new JsonResponse($data, $status); });
        $this->app->instance(ResponseFactory::class, $responses);
        $food = Mockery::mock('alias:App\\Models\\Food');
        $food->shouldReceive('findOrFail')->andReturn(new \Illuminate\Support\Fluent([
            'id' => 1, 'price' => 10, 'name' => 'Pizza', 'image' => null, 'image_full_url' => '',
            'maximum_cart_quantity' => 100, 'partner_price' => '[]', 'variations' => '[]',
            'discount' => 20, 'discount_type' => 'percent', 'stock_type' => 'unlimited',
        ]));
        $original = collect([$this->item()]);
        if ($edit && $useFoodDefault) {
            $original[0] = $this->item(['discount' => $type === 'percent' ? 10 * $value / 100 : $value,
                'discount_source' => 'food_default', 'discountAmount' => $value,
                'discountType' => $type, 'pos_discount_type' => 1]);
        }
        if ($overall) {
            $original['order_discount_type_id'] = 2;
            $original['discount'] = 20;
            $original['discount_type'] = 'percent';
        }
        $snapshot = $original->all();
        $this->session->put('cart', $original);
        $payload = ['id' => 1, 'quantity' => 1, 'pos_discount_type' => $id, 'product_discount' => $value, 'product_discount_type' => $type];
        if ($useFoodDefault) { $payload['use_food_default'] = '1'; }
        if ($edit) { $payload['cart_item_key'] = 0; }
        $request = Request::create('/pos/add-to-cart', 'POST', $payload);
        $request->setLaravelSession($this->session);
        $response = (new POSController())->addToCart($request);
        $data = $response->getData(true);
        if ($expectedError) {
            $this->assertSame('discount_error', $data['data']);
            $this->assertSame($snapshot, $this->session->get('cart')->all());
        } else {
            $this->assertNotSame('error', $data['data'], $data['message'] ?? '');
            $items = $this->session->get('cart')->filter(function ($item) { return is_array($item); })->values();
            $this->assertCount($edit ? 1 : 2, $items);
            $default = $useFoodDefault || ($id === null && (float) $value === 20.0);
            $suppressed = $default && $overall;
            $this->assertEquals($suppressed ? 0 : ($type === 'percent' ? 10 * $value / 100 : $value), $items->last()['discount']);
            $this->assertSame($suppressed ? 'none' : ($default ? 'food_default'
                : ($value > 0 ? 'manual' : 'none')), $items->last()['discount_source']);
            if ($suppressed) {
                $this->assertSame(0, $items->last()['discountAmount']);
                $this->assertNull($items->last()['pos_discount_type']);
            }
            if ($overall) {
                $this->assertEquals(2, $this->session->get('cart')['order_discount_type_id']);
                $this->assertSame($edit ? 2.0 : 4.0, $this->service->amount($this->session->get('cart')));
            }
        }
    }

    public function productCartCases(): array
    {
        return [
            'product discount' => [1, 10, 'percent', false, false, false],
            'overall id rejected in product form' => [2, 20, 'percent', false, false, true],
            'product discount blocked after overall' => [1, 10, 'percent', true, false, true],
            'product discount edit blocked after overall' => [1, 10, 'percent', true, true, true],
            'default food discount added after overall' => [null, 20, 'percent', true, false, false],
            'default food discount edit after overall' => [null, 20, 'percent', true, true, false],
            'default food discount without overall' => [null, 20, 'percent', false, false, false],
            'selected offer applies default discount with extra present' => [1, 20, 'percent', true, false, false, true],
            'selected offer retained when editing' => [1, 20, 'percent', true, true, false, true],
            'saved offer retains original rate when food default changes' => [1, 15, 'percent', true, true, false, true],
            'selected offer applies default discount without extra' => [1, 20, 'percent', false, false, false, true],
            'offer flag cannot disguise a manual rate' => [1, 10, 'percent', true, false, true, true],
            'same native percentage selected manually remains blocked' => [1, 20, 'percent', true, false, true],
            'undiscounted product allowed after overall' => [null, 0, 'percent', true, false, false],
            'explicit zero does not restore food default' => [null, 0, 'percent', false, false, false],
            'edit removes discount' => [null, 0, 'amount', false, true, false],
            'invalid percent' => [1, 101, 'percent', false, false, true],
            'negative discount' => [1, -1, 'amount', false, false, true],
            'amount exceeds unit price' => [1, 11, 'amount', false, false, true],
        ];
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @dataProvider draftLoadPaths
     */
    public function testDraftLoadRestoresDiscountIdAndRecalculatesAfterChanges($path, $native = false, $legacy = false, $withAddons = false): void
    {
        $responses = Mockery::mock(ResponseFactory::class);
        $responses->shouldReceive('json')->andReturnUsing(function ($data) { return new JsonResponse($data); });
        $this->app->instance(ResponseFactory::class, $responses);
        $redirect = Mockery::mock();
        $redirect->shouldReceive('route')->andReturn(new RedirectResponse('/pos'));
        $this->app->instance('redirect', $redirect);
        $order = new \Illuminate\Support\Fluent([
            'id' => 101, 'partner_id' => null, 'delivery_charge' => 0, 'total_tax_amount' => 0,
            'restaurant_discount_amount' => 2, 'order_discount_type_id' => 2,
            'pos_details' => new \Illuminate\Support\Fluent(['invoice_amount' => 8]),
            'details' => collect([new \Illuminate\Support\Fluent([
                'food_id' => 1, 'food_details' => json_encode($native ? array_merge([
                    'name' => 'Pizza', 'discount' => 20, 'discount_type' => 'percent',
                ], $legacy ? [] : ['pos_discount_source' => 'food_default',
                    'pos_discount_value' => 20, 'pos_discount_value_type' => 'percent']) : ['name' => 'Pizza']),
                'variation' => $withAddons ? json_encode([['addons' => [['id' => 8, 'price' => 0.5,
                    'quantity' => 1, 'quantity_mode' => 'manual']]]]) : '[]',
                'add_ons' => $withAddons ? json_encode([['id' => 7, 'price' => 0.25,
                    'quantity' => 1, 'quantity_mode' => 'manual']]) : '[]',
                'quantity' => $withAddons ? 2 : 1, 'price' => 10,
                'discount_on_food' => $native ? 2 : 0, 'total_add_on_price' => $withAddons ? 0.75 : 0,
                'pos_discount_type' => $legacy ? 1 : null,
                'is_deleted' => 'N', 'cancel_reason' => '', 'cooking_status' => '', 'cancel_text' => '',
            ])]),
        ]);
        $model = Mockery::mock('alias:App\\Models\\Order');
        $model->shouldReceive('with')->andReturnSelf();
        $model->shouldReceive('find', 'findOrFail')->with(101)->andReturn($order);
        $this->app['config']->set([
            'currency_symbol' => 'OMR',
            'currency_symbol_position' => 'right', 'round_up_to_digit' => 3,
        ]);
        if ($path === 'draft') {
            (new POSController())->loadDraftOrderToCart(101);
        } else {
            (new \App\Http\Controllers\Vendor\OrderController())->getPaymentData(101);
        }
        $cart = $this->session->get('cart');
        $this->assertNotNull($cart);
        $this->assertSame(2, $cart['order_discount_type_id']);
        $this->assertSame($withAddons ? 4.15 : ($native ? 0.0 : 2.0), $this->service->amount($cart));
        if ($withAddons) {
            $this->assertSame([1], $cart[0]['add_on_qtys']);
            $this->assertSame(['manual'], $cart[0]['add_on_quantity_modes']);
            $this->assertSame(1, $cart[0]['variations'][0]['addons'][0]['quantity']);
            $this->assertSame('manual', $cart[0]['variations'][0]['addons'][0]['quantity_mode']);
        }
        $this->assertSame($native ? 'food_default' : 'none', $cart[0]['discount_source']);
        if ($native) {
            $this->assertSame($legacy ? 1 : null, $cart[0]['pos_discount_type']);
            $this->assertEquals(20, $cart[0]['discountAmount']);
            $this->assertSame('percent', $cart[0]['discountType']);
            $cart->push($this->item());
            $this->assertSame(2.0, $this->service->amount($cart));
        }
        $item = $cart[0];
        $item['quantity'] = 10;
        $cart[0] = $item;
        $this->assertSame($native ? 2.0 : 5.0, $this->service->amount($cart));
    }

    public function draftLoadPaths(): array
    {
        return [['draft'], ['payment'], ['draft', true], ['payment', true],
            ['draft', true, true], ['payment', true, true],
            ['draft', false, false, true], ['payment', false, false, true]];
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testPaidOrdersCannotChangeExtraDiscount(): void
    {
        $model = Mockery::mock('alias:App\\Models\\Order');
        $model->shouldReceive('where')->andReturnSelf();
        $model->shouldReceive('exists')->andReturn(true);
        $original = collect([$this->item()]);
        $this->session->put('cart', $original);
        $this->session->put('editing_order_id', 101);
        $this->update(['order_discount_type_id' => 2]);
        $this->assertSame($original, $this->session->get('cart'));
        Toastr::shouldHaveReceived('error')->once();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @dataProvider invalidCheckouts
     */
    public function testCheckoutRevalidatesStaleCartBeforeAnyWrites($mode, $case, $message): void
    {
        $restaurant = new \Illuminate\Support\Fluent(['self_delivery_system' => 1, 'restaurant_model' => 'commission', 'tax' => 0]);
        $guard = Mockery::mock();
        $guard->shouldReceive('id')->andReturn(7);
        $guard->shouldReceive('check')->andReturn(true);
        $guard->shouldReceive('user')->andReturn((object) ['restaurant' => $restaurant]);
        $auth = Mockery::mock(AuthFactory::class);
        $auth->shouldReceive('guard')->andReturn($guard);
        $this->app->instance(AuthFactory::class, $auth);
        $shift = Mockery::mock('alias:App\\Models\\ShiftSession');
        $shift->shouldReceive('current', 'where')->andReturnSelf();
        $shift->shouldReceive('first')->andReturn((object) ['session_id' => 9]);

        $item = $this->item();
        $cart = collect([$item]);
        if ($case === 'conflict') {
            $cart[0] = $this->item(['discount' => 1]);
            $cart['order_discount_type_id'] = 2;
        } elseif ($case === 'inactive') {
            $cart['order_discount_type_id'] = 3;
        } elseif ($case === 'product scope') {
            $cart[0] = $this->item(['pos_discount_type' => 2]);
        } else {
            $extra = strpos($case, ' with extra') !== false;
            $case = str_replace(' with extra', '', $case);
            if ($extra) {
                $cart['discount'] = 20;
                $cart['discount_type'] = 'percent';
            }
            $overrides = [
                'unit discount' => ['quantity' => 3, 'discount' => 11],
                'negative price' => ['price' => -1],
                'negative quantity' => ['quantity' => -1],
                'zero quantity' => ['quantity' => 0],
                'negative addon' => ['addon_price' => -1],
                'nonfinite price' => ['price' => INF],
            ];
            if (isset($overrides[$case])) {
                $cart[0] = $this->item($overrides[$case]);
            } elseif ($case === 'negative fee') {
                $cart['delivery_fee'] = -100;
            } elseif ($case === 'negative tax') {
                $cart['tax'] = -100;
            } elseif ($case === 'excessive extra') {
                $cart['discount'] = 11;
                $cart['discount_type'] = 'amount';
            } elseif ($case === 'negative extra') {
                $cart['discount'] = -1;
            }
        }
        $this->session->put('cart', $cart);
        $this->session->put('editing_order_id', 101);
        $request = Request::create('/pos/order', 'POST', ['order_draft' => $mode]);
        $request->setLaravelSession($this->session);
        $redirect = new RedirectResponse('/pos');
        $redirect->setSession($this->session);
        $redirect->setRequest($request);
        $redirector = Mockery::mock();
        $redirector->shouldReceive('back')->andReturn($redirect);
        $this->app->instance('redirect', $redirector);
        $this->database->enableQueryLog();
        $response = (new POSController())->place_order($request);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(0, $this->database->transactionLevel());
        foreach ($this->database->getQueryLog() as $query) {
            $this->assertDoesNotMatchRegularExpression('/^(insert|update|delete) /i', $query['query']);
        }
        Toastr::shouldHaveReceived('error')->once()->with(Mockery::on(function ($error) use ($message) {
            return strpos($error, $message) !== false;
        }));
    }

    public function invalidCheckouts(): array
    {
        $cases = [
            ['draft', 'conflict', 'cannot be combined'], ['final', 'conflict', 'cannot be combined'],
            ['draft', 'inactive', 'active overall-bill discount'], ['final', 'inactive', 'active overall-bill discount'],
            ['draft', 'product scope', 'active per-product discount'],
        ];
        foreach (['draft', 'final'] as $mode) {
            foreach (['unit discount' => 'exceed the product price', 'negative price' => 'nonnegative',
                'negative quantity' => 'nonnegative', 'zero quantity' => 'positive whole number',
                'negative addon' => 'nonnegative', 'nonfinite price' => 'nonnegative',
                'negative fee' => 'nonnegative', 'negative tax' => 'nonnegative'] as $case => $message) {
                $cases[] = [$mode, $case, $message];
                $cases[] = [$mode, $case . ' with extra', $message];
            }
            $cases[] = [$mode, 'excessive extra', 'exceed the order subtotal'];
            $cases[] = [$mode, 'negative extra', 'invalid extra discount'];
        }
        return $cases;
    }

    /** @dataProvider offerSelectionCases */
    public function testProductModalsShowTheApplicableDiscount($view, $native, $extra = false, $none = false): void
    {
        if ($extra) {
            $this->session->put('cart', collect(['order_discount_type_id' => 2]));
        }
        $compiler = new BladeCompiler(new \Illuminate\Filesystem\Filesystem(), sys_get_temp_dir());
        $template = file_get_contents(dirname(__DIR__, 2).'/resources/views/vendor-views/pos/'.$view.'.blade.php');
        $setupEnd = strpos($template, '@endphp') + strlen('@endphp');
        $start = strpos($template, '<input type="hidden" name="use_food_default"');
        $end = strpos($template, '</select>', $start) + strlen('</select>');
        $end = strpos($template, '<div class="form-group col-md-3 mb-1">', $end);
        $end = strpos($template, '</div>', $end) + strlen('</div>');
        $fragment = substr($template, 0, $setupEnd).substr($template, $start, $end - $start);
        $product = new \Illuminate\Support\Fluent(['discount' => 20, 'discount_type' => 'percent']);
        $discounts = collect([
            (object) ['id' => 1, 'name' => 'Offer', 'default_discount' => 10],
            (object) ['id' => 5, 'name' => 'Staff', 'default_discount' => 15],
        ]);
        $cart_item = ['discount_source' => $native ? 'food_default' : 'manual',
            'discountAmount' => $native ? 20 : 15, 'discountType' => 'percent',
            'pos_discount_type' => $native ? 1 : 5];
        if ($none) {
            $cart_item = ['discount_source' => 'none', 'discount' => 0, 'discountAmount' => 0,
                'discountType' => 'percent', 'pos_discount_type' => null];
        }
        $__env = new \Illuminate\View\Factory(new \Illuminate\View\Engines\EngineResolver(),
            new \Illuminate\View\FileViewFinder(new \Illuminate\Filesystem\Filesystem(), []),
            new \Illuminate\Events\Dispatcher($this->app));
        ob_start();
        try {
            eval('?>'.$compiler->compileString($fragment));
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $dom = new \DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);
        $selected = $xpath->query('//select[@id="pos_discount_type"]/option[@selected]');
        $this->assertSame(1, $selected->length);
        $this->assertSame($extra || $none ? '' : ($native ? '1' : '5'), $selected->item(0)->getAttribute('value'));
        $this->assertSame($extra || $none ? 'No product discount' : ($native ? 'Offer' : 'Staff'), trim($selected->item(0)->textContent));
        $this->assertSame($extra || $none ? '0' : ($native ? '20' : '15'), $selected->item(0)->getAttribute('data-value'));
        $this->assertSame(!$extra && !$none && $native ? '1' : '0', $xpath->query('//input[@name="use_food_default"]')->item(0)->getAttribute('value'));
        $this->assertSame($extra || $none ? '0' : ($native ? '20' : '15'), $xpath->query('//input[@name="product_discount"]')->item(0)->getAttribute('value'));
        $this->assertSame($extra, $xpath->query('//select[@id="pos_discount_type"]')->item(0)->hasAttribute('disabled'));
        $this->assertSame(1, $xpath->query('//select[@id="pos_discount_type"]/option[@value="1"]')->length);
    }

    public function offerSelectionCases(): array
    {
        return [['_quick-view-data', true], ['_quick-view-cart-item', true], ['_quick-view-cart-item', false],
            ['_quick-view-data', true, true], ['_quick-view-cart-item', true, true],
            ['_quick-view-cart-item', false, false, true]];
    }

    public function testDisabledExtraDiscountButtonAndErrorStaySilentWithASavedDiscount(): void
    {
        $this->database->table('business_settings')->insert(['key' => PosDiscountService::ENABLED_SETTING, 'value' => '0']);
        $extraDiscountEnabled = $this->service->isEnabled();
        $this->assertFalse($extraDiscountEnabled);
        $extraDiscountError = 'Extra Discount is disabled in Business Setup.';
        $compiler = new BladeCompiler(new \Illuminate\Filesystem\Filesystem(), sys_get_temp_dir());
        $template = file_get_contents(dirname(__DIR__, 2).'/resources/views/vendor-views/pos/_cart.blade.php');
        $start = strrpos(substr($template, 0, strpos($template, 'aria-label="Edit extra discount"')), '<button');
        $end = strpos($template, '</button>', $start) + strlen('</button>');
        $errorStart = strpos($template, '@if($extraDiscountEnabled && $extraDiscountError)');
        $errorEnd = strpos($template, '@endif', $errorStart) + strlen('@endif');
        ob_start();
        try {
            eval('?>'.$compiler->compileString(substr($template, $start, $end - $start)
                .substr($template, $errorStart, $errorEnd - $errorStart)));
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $dom = new \DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $this->assertTrue($dom->getElementsByTagName('button')->item(0)->hasAttribute('disabled'));
        $this->assertStringNotContainsString($extraDiscountError, $html);
        $this->assertStringNotContainsString('role="alert"', $html);
    }

    public function testChangedBladeTemplatesCompile(): void
    {
        $compiler = new BladeCompiler(new \Illuminate\Filesystem\Filesystem(), sys_get_temp_dir());
        foreach (['_cart', 'index-new', '_quick-view-data', '_quick-view-cart-item'] as $view) {
            $template = file_get_contents(dirname(__DIR__, 2).'/resources/views/vendor-views/pos/'.$view.'.blade.php');
            $this->assertNotEmpty(token_get_all($compiler->compileString($template), TOKEN_PARSE));
        }
    }

    private function item(array $overrides = []): array
    {
        return array_merge(['id' => 1, 'price' => 10, 'quantity' => 1, 'addon_price' => 0, 'discount' => 0, 'is_deleted' => 'N'], $overrides);
    }

    private function update(array $payload)
    {
        $request = Request::create('/pos/discount', 'POST', $payload);
        $request->setLaravelSession($this->session);
        return (new POSController())->update_discount($request);
    }
}
