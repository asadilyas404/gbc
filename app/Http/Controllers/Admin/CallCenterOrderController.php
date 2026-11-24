<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\CallCenterZone;
use App\Models\Order;
use App\Models\OrderRequest;
use App\Models\OrderRequestDetail;
use App\Models\SaleCustomer;
use App\Models\TblSoftBranch;
use App\Services\CallCenter;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CallCenterOrderController extends Controller
{
    public function waiting(Request $request)
    {
        $this->assertAgent();
        $status = $request->query('status', 'pending');
        if (!in_array($status, ['pending', 'accepted', 'rejected'], true)) {
            $status = 'pending';
        }
        $q = trim(str_replace(['%', '_'], '', (string) $request->query('q', '')));
        $since = now()->subDay();

        $pending = OrderRequest::query()->where('status', 'pending')->count();
        $rejected = OrderRequest::query()->where('status', 'rejected')->count();
        $accepted = OrderRequest::query()
            ->where('status', 'accepted')
            ->where('accepted_at', '>=', $since)
            ->count();

        $requests = OrderRequest::with(['details', 'branch'])->where('status', $status);
        if ($status === 'accepted') {
            $requests->where('accepted_at', '>=', $since);
        }
        if ($q !== '') {
            $like = '%' . mb_strtoupper($q) . '%';
            $requests->where(function ($inner) use ($like, $q) {
                $inner->where(DB::raw('UPPER(customer_name)'), 'like', $like)
                    ->orWhere(DB::raw('UPPER(customer_phone)'), 'like', $like)
                    ->orWhere(DB::raw('UPPER(zone_name)'), 'like', $like)
                    ->orWhereHas('branch', function ($branch) use ($like) {
                        $branch->where(DB::raw('UPPER(branch_name)'), 'like', $like);
                    });
                if (ctype_digit($q)) {
                    $inner->orWhere('id', (int) $q);
                }
            });
        }

        $waiting = $requests->orderByDesc('id')->paginate(8)->appends([
            'status' => $status,
            'q' => $q,
        ]);
        $branches = TblSoftBranch::query()->orderBy('branch_name')->get();

        return response()->json([
            'html' => view('admin-views.call-center._waiting', [
                'waiting' => $waiting,
                'branches' => $branches,
                'routePrefix' => $this->routePrefix(),
                'status' => $status,
                'q' => $q,
                'counts' => [
                    'pending' => $pending,
                    'accepted' => $accepted,
                    'rejected' => $rejected,
                ],
            ])->render(),
            'total' => $pending + $rejected,
            'pending' => $pending,
            'accepted' => $accepted,
            'rejected' => $rejected,
        ]);
    }

    public function customers(Request $request)
    {
        $this->assertAgent();
        $key = trim((string) $request->query('q', ''));
        if ($key === '') {
            return response()->json([]);
        }

        $rows = SaleCustomer::query()
            ->where(function ($query) use ($key) {
                $query->orWhere(DB::raw('UPPER(customer_name)'), 'like', strtoupper("{$key}%"))
                    ->orWhere(DB::raw('UPPER(customer_mobile_no)'), 'like', strtoupper("{$key}%"));
            })
            ->orderBy('customer_code', 'desc')
            ->limit(8)
            ->get([
                'customer_id',
                'customer_name',
                'customer_mobile_no',
                'customer_address',
                'customer_delivery_address',
            ]);

        return response()->json($rows->map(function ($customer) {
            $location = $this->lastLocation($customer);

            return [
                'id' => $customer->customer_id,
                'name' => $customer->customer_name,
                'phone' => $customer->customer_mobile_no,
                'address' => $location['address'],
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
            ];
        }));
    }

    public function store(Request $request)
    {
        $this->assertAgent();
        $lines = $this->posCartLines();
        if (count($lines) < 1) {
            Toastr::error('Add at least one item.');
            return back()->withInput();
        }

        $data = $this->validateRequest($request);
        $zone = $this->zoneForBranch((int) $data['zone_id'], (int) $data['restaurant_id']);
        $quote = CallCenter::quote((int) $zone->delivery_minutes);
        $customerId = $this->rememberCustomer($data['customer_name'], $data['customer_phone'], $data['address']);

        DB::transaction(function () use ($data, $zone, $quote, $lines, $customerId) {
            $orderRequest = OrderRequest::create([
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_id' => $customerId,
                'address' => $data['address'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'restaurant_id' => $data['restaurant_id'],
                'zone_id' => $zone->id,
                'zone_name' => $zone->name,
                'preparation_minutes' => $quote['preparation_minutes'],
                'zone_delivery_minutes' => $quote['zone_delivery_minutes'],
                'handover_minutes' => $quote['handover_minutes'],
                'customer_eta_minutes' => $quote['customer_eta_minutes'],
                'driver_return_minutes' => $quote['driver_return_minutes'],
                'delivery_fee' => $data['delivery_fee'],
                'delivery_type' => $data['delivery_type'],
                'status' => 'pending',
                'agent_user_id' => $this->agentId(),
            ]);

            foreach ($lines as $line) {
                OrderRequestDetail::create([
                    'order_request_id' => $orderRequest->id,
                    'food_id' => $line['food_id'],
                    'food_name' => $line['food_name'],
                    'quantity' => $line['quantity'],
                    'price' => round((float) $line['unit_price'] - (float) $line['discount'], 3),
                    'variation' => json_encode($line['variations']),
                    'add_ons' => json_encode([
                        'add_ons' => $line['add_ons'],
                        'add_on_qtys' => $line['add_on_qtys'],
                        'addon_price' => $line['addon_price'],
                        'discount' => $line['discount'],
                        'unit_price' => $line['unit_price'],
                        'variation_option_ids' => $line['variation_option_ids'],
                        'partner_id' => $line['partner_id'],
                        'summary' => $line['summary'],
                    ]),
                    'notes' => $line['notes'],
                ]);
            }
        });

        session()->forget('cart');
        session()->forget('address');
        session()->forget('editing_order_id');
        Toastr::success('Request saved. Tell the customer ' . $quote['customer_eta_minutes'] . ' minutes.');

        return redirect()->route('vendor.pos.index.new')->with('call_center_saved', true);
    }

    public function cancel($id)
    {
        $this->assertAgent();
        $orderRequest = $this->openRequest($id);
        $orderRequest->update([
            'status' => 'cancelled',
            'reject_reason' => null,
        ]);

        Toastr::success('Request cancelled.');
        return back();
    }

    public function reassign(Request $request, $id)
    {
        $this->assertAgent();
        $orderRequest = $this->openRequest($id);
        $data = $request->validate([
            'restaurant_id' => 'required|integer',
            'zone_id' => 'required|integer',
            'delivery_fee' => 'required|numeric|min:0|max:999',
        ]);
        $zone = $this->zoneForBranch((int) $data['zone_id'], (int) $data['restaurant_id']);
        $quote = CallCenter::quote((int) $zone->delivery_minutes);

        $orderRequest->update([
            'restaurant_id' => $data['restaurant_id'],
            'zone_id' => $zone->id,
            'zone_name' => $zone->name,
            'preparation_minutes' => $quote['preparation_minutes'],
            'zone_delivery_minutes' => $quote['zone_delivery_minutes'],
            'handover_minutes' => $quote['handover_minutes'],
            'customer_eta_minutes' => $quote['customer_eta_minutes'],
            'driver_return_minutes' => $quote['driver_return_minutes'],
            'delivery_fee' => $data['delivery_fee'],
            'status' => 'pending',
            'reject_reason' => null,
            'rejected_at' => null,
            'accepted_at' => null,
        ]);

        Toastr::success('Request moved. Tell the customer ' . $quote['customer_eta_minutes'] . ' minutes.');
        return back();
    }

    private function assertAgent(): void
    {
        if (!CallCenter::isAgent()) {
            abort(403, 'Call center access required.');
        }
        if (!$this->tablesReady()) {
            abort(503, 'Call center tables are not installed.');
        }
    }

    /**
     * Map the live POS cart into frozen request lines.
     */
    private function posCartLines(): array
    {
        $cart = session('cart', []);
        $lines = [];

        foreach ($cart as $item) {
            if (!is_array($item) || !isset($item['id'])) {
                continue;
            }
            if (trim((string) ($item['is_deleted'] ?? 'N')) === 'Y') {
                continue;
            }

            $variations = $item['variations'] ?? [];
            $addonItems = [];
            foreach ($variations as $variation) {
                foreach ($variation['addons'] ?? [] as $addon) {
                    if (!empty($addon['name']) || !empty($addon['id'])) {
                        $addonItems[] = $addon;
                    }
                }
            }

            $lines[] = [
                'food_id' => $item['id'],
                'food_name' => $item['name'] ?? 'Item',
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'unit_price' => (float) ($item['price'] ?? 0),
                'discount' => (float) ($item['discount'] ?? 0),
                'variations' => $variations,
                'add_ons' => $item['add_ons'] ?? [],
                'add_on_qtys' => $item['add_on_qtys'] ?? [],
                'addon_price' => (float) ($item['addon_price'] ?? 0),
                'variation_option_ids' => $item['variation_option_ids'] ?? '',
                'partner_id' => $item['partner_id'] ?? null,
                'notes' => (string) ($item['details'] ?? ''),
                'summary' => $this->summary($variations, $addonItems, (string) ($item['details'] ?? '')),
            ];
        }

        return $lines;
    }

    private function summary(array $variations, array $addonItems, string $notes): string
    {
        $parts = [];
        foreach ($variations as $variation) {
            $labels = $variation['values']['label'] ?? [];
            if ($labels) {
                $parts[] = ($variation['name'] ?? 'Option') . ': ' . implode(', ', (array) $labels);
            }
        }
        foreach ($addonItems as $addon) {
            if (($addon['name'] ?? '') !== '') {
                $parts[] = $addon['name'] . ' x' . (int) ($addon['quantity'] ?? 1);
            }
        }
        if ($notes !== '') {
            $parts[] = $notes;
        }

        return implode(' · ', $parts);
    }

    private function validateRequest(Request $request): array
    {
        $rules = [
            'customer_name' => 'required|string|max:191',
            'customer_phone' => 'required|string|max:30',
            'address' => 'required|string|max:500',
            'restaurant_id' => 'required|integer',
            'zone_id' => 'required|integer',
            'delivery_fee' => 'required|numeric|min:0|max:999',
            'delivery_type' => 'required|in:take_away,dine_in,delivery',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ];

        $mapKey = optional(BusinessSetting::where('key', 'map_api_key')->first())->value;
        if ($mapKey) {
            $rules['latitude'] = 'required|numeric';
            $rules['longitude'] = 'required|numeric';
        }

        $data = $request->validate($rules, [
            'latitude.required' => 'Pin the customer on the map.',
            'longitude.required' => 'Pin the customer on the map.',
        ]);
        $data['customer_name'] = trim($data['customer_name']);
        $data['customer_phone'] = trim($data['customer_phone']);
        $data['address'] = trim($data['address']);
        $data['latitude'] = $data['latitude'] !== null && $data['latitude'] !== '' ? $data['latitude'] : null;
        $data['longitude'] = $data['longitude'] !== null && $data['longitude'] !== '' ? $data['longitude'] : null;

        if (!TblSoftBranch::query()->where('branch_id', $data['restaurant_id'])->exists()) {
            throw ValidationException::withMessages(['restaurant_id' => 'Branch not found.']);
        }

        return $data;
    }

    private function zoneForBranch(int $zoneId, int $restaurantId): CallCenterZone
    {
        $zone = CallCenterZone::query()
            ->where('id', $zoneId)
            ->where('restaurant_id', $restaurantId)
            ->first();

        if (!$zone) {
            throw ValidationException::withMessages([
                'zone_id' => 'Choose a zone that belongs to the selected branch.',
            ]);
        }

        return $zone;
    }

    private function openRequest($id): OrderRequest
    {
        $orderRequest = OrderRequest::query()->findOrFail($id);
        if (!in_array($orderRequest->status, ['pending', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This request can no longer be changed.',
            ]);
        }

        return $orderRequest;
    }

    private function rememberCustomer(string $name, string $phone, ?string $address): ?int
    {
        $branchId = (int) (config('constants.branch_id') ?: Helpers::get_restaurant_id());
        $customer = SaleCustomer::query()->where('customer_mobile_no', $phone)->first();

        if (!$customer) {
            if ($branchId < 1) {
                return null;
            }

            $customer = SaleCustomer::create([
                'customer_code' => SaleCustomer::generateCustomerCode(),
                'customer_type' => '19148225011030',
                'customer_name' => $name,
                'customer_mobile_no' => $phone,
                'business_id' => 1,
                'company_id' => 1,
                'branch_id' => $branchId,
                'customer_id' => SaleCustomer::generateCustomerId($branchId),
            ]);
        }

        $fields = [
            'customer_name' => $name,
            'customer_address' => $address,
            'customer_delivery_address' => $address,
            'customer_whatapp_no' => $phone,
            'is_pushed' => 'N',
            'updated_at' => now(),
        ];
        if ($this->agentId()) {
            $fields['customer_user_id'] = $this->agentId();
        }

        DB::table('tbl_sale_customer')->where('customer_id', $customer->customer_id)->update($fields);

        return (int) $customer->customer_id;
    }

    private function lastLocation(SaleCustomer $customer): array
    {
        $empty = ['address' => null, 'latitude' => null, 'longitude' => null];
        $savedAddress = $customer->customer_delivery_address ?: $customer->customer_address;

        $previous = OrderRequest::query()
            ->where('customer_phone', $customer->customer_mobile_no)
            ->whereNotNull('address')
            ->orderByDesc('id')
            ->first(['address', 'latitude', 'longitude']);

        if ($savedAddress) {
            return [
                'address' => $savedAddress,
                'latitude' => $previous->latitude ?? null,
                'longitude' => $previous->longitude ?? null,
            ];
        }

        if ($previous && $previous->address) {
            return [
                'address' => $previous->address,
                'latitude' => $previous->latitude,
                'longitude' => $previous->longitude,
            ];
        }

        $order = Order::query()
            ->where('user_id', $customer->customer_id)
            ->whereNotNull('delivery_address')
            ->orderByDesc('id')
            ->first(['delivery_address']);

        if (!$order) {
            return $empty;
        }

        $raw = $order->delivery_address;
        if (is_object($raw) && method_exists($raw, 'load')) {
            $raw = $raw->load();
        } elseif (is_resource($raw)) {
            $raw = stream_get_contents($raw);
        }
        $address = json_decode((string) $raw, true);
        if (!is_array($address) || empty($address['address'])) {
            return $empty;
        }

        return [
            'address' => $address['address'],
            'latitude' => $address['latitude'] ?? null,
            'longitude' => $address['longitude'] ?? null,
        ];
    }

    private function agentId(): ?int
    {
        $id = auth('vendor_employee')->id() ?: auth('vendor')->id() ?: auth('admin')->id();

        return $id ? (int) $id : null;
    }

    private function routePrefix(): string
    {
        return 'vendor.call-center';
    }

    private function tablesReady(): bool
    {
        try {
            $row = DB::selectOne("SELECT COUNT(*) AS total FROM user_tables WHERE table_name = 'ORDER_REQUESTS'");
            $values = array_change_key_case((array) $row, CASE_LOWER);

            return (int) ($values['total'] ?? 0) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
