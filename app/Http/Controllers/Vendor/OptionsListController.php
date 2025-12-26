<?php

namespace App\Http\Controllers\Vendor;

use App\Models\OptionsList;
use App\Models\Translation;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;

class OptionsListController extends Controller
{
    public function index(Request $request)
    {
        $key = $request->filled('search')
            ? explode(' ', strtolower($request->input('search')))
            : null;

        $options = OptionsList::orderBy('name')
            ->when($key, function ($query) use ($key) {
                $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->whereRaw('LOWER(name) LIKE ?', ["%{$value}%"]);
                    }
                });
            })
            ->paginate(100);
        return view('vendor-views.options-list.index', compact('options'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|array',
            'name.*' => 'max:191',
            'price' => 'nullable|numeric|min:0|max:999999999999.99',
        ], [
            'name.required' => translate('messages.Name is required!'),
        ]);

        $maxId = OptionsList::max('id') ?? 0;
        $newId = $maxId + 1;
        $option = new OptionsList();
        $option->id = $newId;
        $option->name = $request->name[array_search('default', $request->lang)];
        $option->price = $request->price ?? 0;
        $option->save();
        Helpers::add_or_update_translations($request, 'name', 'name', 'OptionsList', $option->id, $option->name);

        Toastr::success(translate('messages.option_added_successfully'));
        return back();
    }

    public function edit($id)
    {
        $option = OptionsList::withoutGlobalScope('translate')->findOrFail($id);
        return view('vendor-views.options-list.edit', compact('option'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|array',
            'name.*' => 'max:191',
            'price' => 'nullable|numeric|min:0|max:999999999999.99',
        ], [
            'name.required' => translate('messages.Name is required!'),
        ]);

        $option = OptionsList::find($id);
        $option->name = $request->name[array_search('default', $request->lang)];
        $option->price = $request->price ?? 0;
        $option->save();
        Helpers::add_or_update_translations($request, 'name', 'name', 'OptionsList', $option->id, $option->name);
        Toastr::success(translate('messages.option_updated_successfully'));
        return redirect(route('vendor.options-list.add-new'));
    }

    public function delete(Request $request)
    {
        $option = OptionsList::findOrFail($request->id);
        
        if(DB::table('VW_REST_OPTIONS_ORDERS')->where('options_list_id', $request->id)->exists()){
            Toastr::error(translate('messages.option_cannot_be_deleted_because_it_is_associated_with_orders'));
            return back();
        }

        $option->delete();
        Toastr::success(translate('messages.option_deleted_successfully'));
        return back();
    }

    public function status(Request $request)
    {
        $option = OptionsList::find($request->id);
        $option->status = $request->status;
        $option->save();
        Toastr::success(translate('messages.option_status_updated_successfully'));
        return back();
    }
}
