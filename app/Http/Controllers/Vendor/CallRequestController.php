<?php

namespace App\Http\Controllers\Vendor;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderRequest;
use App\Services\CallCenter;
use App\Services\PosCartService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CallRequestController extends Controller
{
    public function index(Request $request)
    {
        $this->assertBranchInbox();

        $status = $request->query('status', 'pending');
        if (!in_array($status, ['pending', 'accepted', 'rejected'], true)) {
            $status = 'pending';
        }

        $branchId = Helpers::get_restaurant_id();
        $requests = OrderRequest::query()
            ->with(['details', 'branch', 'order'])
            ->where('restaurant_id', $branchId)
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get();

        $counts = [
            'pending' => OrderRequest::query()
                ->where('restaurant_id', $branchId)
                ->where('status', 'pending')
                ->count(),
            'accepted' => OrderRequest::query()
                ->where('restaurant_id', $branchId)
                ->where('status', 'accepted')
                ->count(),
            'rejected' => OrderRequest::query()
                ->where('restaurant_id', $branchId)
                ->where('status', 'rejected')
                ->count(),
        ];

        return view('vendor-views.call-requests.index', [
            'requests' => $requests,
            'status' => $status,
            'counts' => $counts,
        ]);
    }

    public function show($id)
    {
        $this->assertBranchInbox();

        $orderRequest = $this->branchRequest($id);

        return redirect()->route('vendor.call-requests.index', [
            'status' => in_array($orderRequest->status, ['pending', 'accepted', 'rejected'], true)
                ? $orderRequest->status
                : 'pending',
            'open' => $orderRequest->id,
        ]);
    }

    public function accept($id)
    {
        $this->assertBranchInbox();

        $orderRequest = $this->branchRequest($id);
        $orderRequest->load('details');

        if ($orderRequest->status !== 'pending' || $orderRequest->order_id) {
            Toastr::error('This request can no longer be accepted.');

            return back();
        }

        try {
            [$cartItems, $partnerId] = $this->cartFromRequest($orderRequest);
        } catch (ValidationException $exception) {
            Toastr::error(collect($exception->errors())->flatten()->first());

            return redirect()->route('vendor.call-requests.index', [
                'status' => 'pending',
                'open' => $orderRequest->id,
            ]);
        }

        if (count($cartItems) < 1) {
            Toastr::error('This request has no items.');

            return redirect()->route('vendor.call-requests.index', [
                'status' => 'pending',
                'open' => $orderRequest->id,
            ]);
        }

        $claimed = OrderRequest::query()
            ->whereKey($orderRequest->id)
            ->where('restaurant_id', Helpers::get_restaurant_id())
            ->where('status', 'pending')
            ->whereNull('order_id')
            ->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'reject_reason' => null,
                'rejected_at' => null,
                'updated_at' => now(),
            ]);

        if (!$claimed) {
            Toastr::error('This request can no longer be accepted.');

            return back();
        }

        $previousLastOrder = session('last_order');
        $savedCart = session()->get('cart');
        $savedAddress = session()->get('address');
        $savedEditing = session()->get('editing_order_id');
        $savedPartner = session()->get('current_partner_id');

        session()->forget('editing_order_id');
        session()->put('cart', collect($cartItems)->all());
        session()->put('address', [
            'contact_person_name' => $orderRequest->customer_name,
            'contact_person_number' => $orderRequest->customer_phone,
            'address_type' => 'delivery',
            'address' => $orderRequest->address,
            'floor' => null,
            'road' => null,
            'house' => null,
            'delivery_fee' => (float) $orderRequest->delivery_fee,
            'distance' => 0,
            'longitude' => (string) ($orderRequest->longitude ?? ''),
            'latitude' => (string) ($orderRequest->latitude ?? ''),
            'customer_eta_minutes' => (int) $orderRequest->customer_eta_minutes,
        ]);
        if ($partnerId) {
            session()->put('current_partner_id', $partnerId);
        } else {
            session()->forget('current_partner_id');
        }

        // Reuse the live panel request/session so place_order runs exactly as POS does.
        $placeRequest = request();
        $placeRequest->merge([
            'order_draft' => 'draft',
            'delivery_type' => $orderRequest->delivery_type ?: 'delivery',
            'customer_name' => $orderRequest->customer_name,
            'phone' => $orderRequest->customer_phone,
            'user_id' => $orderRequest->customer_id,
            'partner_id' => $partnerId,
            'order_notes' => 'Quoted time: ' . (int) $orderRequest->customer_eta_minutes . ' minutes',
        ]);

        app(POSController::class)->place_order($placeRequest);

        $orderId = session('last_order');
        $created = $orderId && (string) $orderId !== (string) $previousLastOrder;

        if (!$created) {
            OrderRequest::query()->whereKey($orderRequest->id)->update([
                'status' => 'pending',
                'accepted_at' => null,
                'order_id' => null,
                'updated_at' => now(),
            ]);

            $this->restoreSessionCart($savedCart, $savedAddress, $savedEditing, $savedPartner);

            return redirect()->route('vendor.call-requests.index', [
                'status' => 'pending',
                'open' => $orderRequest->id,
            ]);
        }

        $order = Order::query()->find($orderId);
        if ($order) {
            $order->call_center_order = 'Y';
            if ((int) $orderRequest->customer_eta_minutes > 0) {
                $order->processing_time = (int) $orderRequest->customer_eta_minutes;
            }
            $order->is_pushed = 'N';
            $order->save();
        }

        OrderRequest::query()->whereKey($orderRequest->id)->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'order_id' => $orderId,
            'reject_reason' => null,
            'rejected_at' => null,
            'updated_at' => now(),
        ]);

        $this->restoreSessionCart($savedCart, $savedAddress, $savedEditing, $savedPartner);

        Toastr::success('Request accepted. Order sent to the kitchen.');

        return redirect()->route('vendor.call-requests.index', ['status' => 'accepted']);
    }

    public function reject(Request $request, $id)
    {
        $this->assertBranchInbox();

        $data = $request->validate([
            'reject_reason' => 'required|string|max:500',
        ]);

        $orderRequest = $this->branchRequest($id);
        if ($orderRequest->status !== 'pending') {
            Toastr::error('This request can no longer be rejected.');

            return back();
        }

        $orderRequest->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'reject_reason' => $data['reject_reason'],
            'accepted_at' => null,
        ]);

        Toastr::success('Request rejected.');

        return redirect()->route('vendor.call-requests.index', ['status' => 'rejected']);
    }

    private function assertBranchInbox(): void
    {
        if (!CallCenter::enabled() || CallCenter::isAgent()) {
            abort(403);
        }

        $canSee = Helpers::employee_module_permission_check('kitchen_orders')
            || Helpers::employee_module_permission_check('order');

        if (!$canSee) {
            abort(403);
        }
    }

    private function branchRequest($id): OrderRequest
    {
        $orderRequest = OrderRequest::query()
            ->where('restaurant_id', Helpers::get_restaurant_id())
            ->whereKey($id)
            ->first();

        if (!$orderRequest) {
            abort(404);
        }

        return $orderRequest;
    }

    /**
     * Rebuild a POS cart from the frozen request lines so place_order can run unchanged.
     *
     * @return array{0: array<int, array>, 1: mixed}
     */
    private function cartFromRequest(OrderRequest $orderRequest): array
    {
        $cartService = new PosCartService();
        $cartItems = [];
        $partnerId = null;

        foreach ($orderRequest->details as $detail) {
            $extras = $detail->extras();
            $variations = $this->decodeJson($detail->variation);
            $discount = (float) ($extras['discount'] ?? 0);
            $unitPrice = array_key_exists('unit_price', $extras)
                ? (float) $extras['unit_price']
                : ((float) $detail->price + $discount);

            $item = [
                'id' => $detail->food_id,
                'name' => $detail->food_name,
                'quantity' => max(1, (int) $detail->quantity),
                'price' => $unitPrice,
                'variation_price' => 0,
                'variant' => '',
                'variations' => $variations,
                'variation_option_ids' => $extras['variation_option_ids'] ?? '',
                'add_ons' => $extras['add_ons'] ?? [],
                'add_on_qtys' => $extras['add_on_qtys'] ?? [],
                'addon_price' => (float) ($extras['addon_price'] ?? 0),
                'discount' => $discount,
                'discountAmount' => $discount,
                'discountType' => 'amount',
                'discount_source' => $discount > 0 ? 'manual' : 'none',
                'details' => $detail->notes,
                'partner_id' => $extras['partner_id'] ?? null,
                'is_deleted' => 'N',
                'is_printed' => 0,
                'options_changed' => 0,
                'cancel_reason' => '',
                'cooking_status' => '',
                'cancel_text' => '',
                'payment_status' => 'unpaid',
                'pos_discount_type' => null,
            ];

            $cartItems[] = $cartService->prepareItem($item);

            if ($partnerId === null && !empty($extras['partner_id'])) {
                $partnerId = $extras['partner_id'];
            }
        }

        return [$cartItems, $partnerId];
    }

    private function decodeJson($raw): array
    {
        if (is_object($raw) && method_exists($raw, 'load')) {
            $raw = $raw->load();
        } elseif (is_resource($raw)) {
            $raw = stream_get_contents($raw);
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function restoreSessionCart($cart, $address, $editingOrderId, $partnerId): void
    {
        // place_order calls session_write_close() after a successful commit.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if ($cart !== null) {
            session()->put('cart', $cart);
        } else {
            session()->forget('cart');
        }

        if ($address !== null) {
            session()->put('address', $address);
        } else {
            session()->forget('address');
        }

        if ($editingOrderId !== null) {
            session()->put('editing_order_id', $editingOrderId);
        } else {
            session()->forget('editing_order_id');
        }

        if ($partnerId !== null) {
            session()->put('current_partner_id', $partnerId);
        } else {
            session()->forget('current_partner_id');
        }
    }
}
