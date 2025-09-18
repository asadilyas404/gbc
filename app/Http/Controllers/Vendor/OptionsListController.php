<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\OptionsList;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\Translation;

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
        ], [
            'name.required' => translate('messages.Name is required!'),
        ]);

        $maxId = OptionsList::max('id') ?? 0;
        $newId = $maxId + 1;
        $option = new OptionsList();
        $option->id = $newId;
        $option->name = $request->name[array_search('default', $request->lang)];
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
            'name' => 'required|max:191',
        ], [
            'name.required' => translate('messages.Name is required!'),
        ]);

        $option = OptionsList::find($id);
        $option->name = $request->name[array_search('default', $request->lang)];
        $option->save();
        Helpers::add_or_update_translations($request, 'name', 'name', 'OptionsList', $option->id, $option->name);
        Toastr::success(translate('messages.option_updated_successfully'));
        return redirect(route('vendor.options-list.add-new'));
    }

    public function delete(Request $request)
    {
        $option = OptionsList::findOrFail($request->id);
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
