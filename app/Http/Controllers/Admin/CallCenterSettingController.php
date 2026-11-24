<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallCenterZone;
use App\Models\TblSoftBranch;
use App\Services\CallCenter;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CallCenterSettingController extends Controller
{
    public function index()
    {
        $installed = $this->tablesReady();
        $branches = TblSoftBranch::query()->orderBy('branch_name')->get();
        $zones = $installed
            ? CallCenterZone::with('branch')->orderBy('restaurant_id')->orderBy('name')->get()
            : collect();

        $routePrefix = auth('admin')->check() ? 'admin.call-center' : 'vendor.call-center';

        return view('admin-views.call-center.settings', [
            'installed' => $installed,
            'branches' => $branches,
            'zones' => $zones,
            'preparationMinutes' => $installed ? CallCenter::preparationMinutes() : 20,
            'handoverMinutes' => $installed ? CallCenter::handoverMinutes() : 5,
            'routePrefix' => $routePrefix,
        ]);
    }

    public function updateTimes(Request $request)
    {
        $data = $request->validate([
            'preparation_minutes' => 'required|integer|min:0|max:180',
            'handover_minutes' => 'required|integer|min:0|max:60',
        ]);

        $this->putSetting('preparation_minutes', $data['preparation_minutes']);
        $this->putSetting('handover_minutes', $data['handover_minutes']);

        Toastr::success('Delivery time settings saved.');
        return back();
    }

    public function storeZone(Request $request)
    {
        $data = $this->validateZone($request);
        $this->assertUniqueName($data['restaurant_id'], $data['name']);

        CallCenterZone::create($data);

        Toastr::success('Delivery zone added.');
        return back();
    }

    public function updateZone(Request $request, $id)
    {
        $zone = CallCenterZone::query()->findOrFail($id);
        $data = $this->validateZone($request);
        $this->assertUniqueName($data['restaurant_id'], $data['name'], $zone->id);

        $zone->update($data);

        Toastr::success('Delivery zone updated.');
        return back();
    }

    public function destroyZone($id)
    {
        CallCenterZone::query()->findOrFail($id)->delete();

        Toastr::success('Delivery zone removed.');
        return back();
    }

    private function validateZone(Request $request): array
    {
        $data = $request->validate([
            'restaurant_id' => 'required|integer',
            'name' => 'required|string|max:150',
            'delivery_minutes' => 'required|integer|min:0|max:180',
        ]);

        $branchExists = TblSoftBranch::query()->where('branch_id', $data['restaurant_id'])->exists();
        if (!$branchExists) {
            throw ValidationException::withMessages([
                'restaurant_id' => 'Branch not found.',
            ]);
        }

        $data['name'] = trim($data['name']);

        return $data;
    }

    private function assertUniqueName(int $restaurantId, string $name, $ignoreId = null): void
    {
        $query = CallCenterZone::query()
            ->where('restaurant_id', $restaurantId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => 'This branch already has a zone with that name.',
            ]);
        }
    }

    private function putSetting(string $key, $value): void
    {
        DB::table('call_center_settings')->updateOrInsert(
            ['setting_key' => $key],
            ['setting_value' => (string) $value, 'updated_at' => now()]
        );
    }

    private function tablesReady(): bool
    {
        try {
            $row = DB::selectOne(
                "SELECT COUNT(*) AS total FROM user_tables WHERE table_name = 'CALL_CENTER_SETTINGS'"
            );
            $values = array_change_key_case((array) $row, CASE_LOWER);

            return (int) ($values['total'] ?? 0) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
