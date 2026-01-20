<?php

namespace App\Http\Controllers\Vendor;

use App\Models\AddOn;
use App\Models\OrderDetail;
use App\Models\Translation;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;

class AddOnController extends Controller
{
    public function index(Request $request)
    {
        $key = explode(' ', $request['search']) ?? null;
        $addons = AddOn::orderBy('name')
        ->when(isset($key) , function($query) use($key){
            $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('name', 'like', "%{$value}%");
                }
            });
        })
        ->paginate(config('default_pagination'));
        return view('vendor-views.addon.index', compact('addons'));
    }

    public function store(Request $request)
    {
        if(!Helpers::get_restaurant_data()->food_section)
        {
            Toastr::warning(translate('messages.permission_denied'));
            return back();
        }
        $request->validate([
            'name' => 'required|array',
            'name.*' => 'max:191',
            'price' => 'required|numeric|between:0,999999999999.99',
        ],[
            'name.required' => translate('messages.Name is required!'),
        ]);

        $maxId = AddOn::max('id') ?? 0;
        $newId = $maxId + 1;
        $addon = new AddOn();
        $addon->id = $newId;
        $addon->name = $request->name[array_search('default', $request->lang)];
        $addon->price = $request->price;
        $addon->restaurant_id = \App\CentralLogics\Helpers::get_restaurant_id();
        $addon->stock_type = $request->stock_type ?? 'unlimited';
        $addon->addon_stock = $request->stock_type != 'unlimited' ?  $request->addon_stock : 0;
        $addon->save();
        Helpers::add_or_update_translations($request,'name' ,'name' , 'AddOn' , $addon->id, $addon->name);

        Toastr::success(translate('messages.addon_added_successfully'));
        return back();
    }

    public function edit($id)
    {
        if(!Helpers::get_restaurant_data()->food_section)
        {
            Toastr::warning(translate('messages.permission_denied'));
            return back();
        }
        $addon = AddOn::withoutGlobalScope('translate')->findOrFail($id);
        return view('vendor-views.addon.edit', compact('addon'));
    }

    public function update(Request $request, $id)
    {
        if(!Helpers::get_restaurant_data()->food_section)
        {
            Toastr::warning(translate('messages.permission_denied'));
            return back();
        }
        $request->validate([
            'name' => 'required|array',
            'name.*' => 'max:191',
            'price' => 'required|numeric|between:0,999999999999.99',
        ], [
            'name.required' => translate('messages.Name is required!'),
        ]);

        $addon = AddOn::find($id);
        $addon->name = $request->name[array_search('default', $request->lang)];
        $addon->price = $request->price;
        $addon->stock_type = $request->stock_type ?? 'unlimited' ;
        $addon->addon_stock = $request->stock_type != 'unlimited' ?  $request->addon_stock : 0;
        $addon->sell_count = 0;
        $addon->is_pushed = 'N';
        $addon->save();
        Helpers::add_or_update_translations( $request,'name' ,'name' , 'AddOn' , $addon->id, $addon->name);
        Toastr::success(translate('messages.addon_updated_successfully'));
        return redirect(route('vendor.addon.add-new'));
    }

    public function delete(Request $request)
    {
        if (!Helpers::get_restaurant_data()->food_section) {
            Toastr::warning(translate('messages.permission_denied'));
            return back();
        }

        $addon = AddOn::find($request->id);
        if (!$addon) {
            Toastr::error(translate('messages.addon_not_found'));
            return back();
        }

        $id = (string) $addon->id; // your JSON shows ids often stored as strings

        $isUsed = OrderDetail::whereRaw(
            "EXISTS (
                SELECT 1
                FROM JSON_TABLE(
                    variation,
                    '$[*].addons[*]'
                    COLUMNS (
                        addon_id VARCHAR2(50) PATH '$.id'
                    )
                ) jt
                WHERE jt.addon_id = ?
            )",
            [$id]
        )->exists();

        if ($isUsed) {
            Toastr::warning(translate('messages.addon_is_used_in_orders'));
            return back();
        }

        $addon->delete();
        Toastr::success(translate('messages.addon_deleted_successfully'));
        return back();
    }
}
