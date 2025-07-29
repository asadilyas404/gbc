<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Translation;
use App\CentralLogics\Helpers;
use Brian2694\Toastr\Facades\Toastr;

class CategoryController extends Controller
{
    function index(Request $request)
    {
        $key = explode(' ', $request['search']) ?? null;
        $categories = Category::with('childes')->where(['position' => 0])->latest()
            ->when(isset($key), function ($query) use ($key) {
                $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                });
            })

            ->paginate(config('default_pagination'));

        dd($categories);

        return view('vendor-views.category.index', compact('categories'));
    }

    public function get_all(Request $request)
    {
        $data = Category::where('name', 'like', '%' . $request->q . '%')->limit(8)->get([DB::raw('id, CONCAT(name, " (", if(position = 0, "' . translate('messages.main') . '", "' . translate('messages.sub') . '"),")") as text')]);
        if (isset($request->all)) {
            $data[] = (object) ['id' => 'all', 'text' => 'All'];
        }
        return response()->json($data);
    }

    function sub_index(Request $request)
    {
        $key = explode(' ', $request['search']) ?? null;
        $categories = Category::with(['parent'])->where(['position' => 1])
            ->when(isset($key), function ($query) use ($key) {
                $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                });
            })
            ->latest()->paginate(config('default_pagination'));
        return view('vendor-views.category.sub-index', compact('categories'));
    }

    function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:categories|max:100',
            'image' => 'nullable|max:2048',
            'name.0' => 'required',

        ], [
            'name.required' => translate('messages.Name is required!'),
            'name.0.required' => translate('default_name_is_required'),
        ]);

        $maxId = Category::max('id') ?? 0;
        $newId = $maxId + 1;
        $category = new Category();
        $category->id = $newId;
        $category->name = $request->name[array_search('default', $request->lang)];
        $category->image = $request->hasFile('image')
            ? Helpers::upload('category/', 'png', $request->file('image'))
            : 'def.png';
        $category->parent_id = $request->parent_id ?? 0;
        $category->position = $request->position;
        $category->save();  // ID will be set by DB trigger

        $data = [];
        $default_lang = str_replace('_', '-', app()->getLocale());

        $maxTranslationId = DB::table('translations')->max('id') ?? 0;

        foreach ($request->lang as $index => $key) {
            $maxTranslationId++;

            if ($default_lang == $key && !($request->name[$index])) {
                if ($key != 'default') {
                    array_push($data, array(
                        'id' => $maxTranslationId,
                        'translationable_type' => 'App\Models\Category',
                        'translationable_id' => $category->id,
                        'locale' => $key,
                        'key' => 'name',
                        'value' => $category->name,
                    ));
                }
            } else {
                if ($request->name[$index] && $key != 'default') {
                    array_push($data, array(
                        'id' => $maxTranslationId,
                        'translationable_type' => 'App\Models\Category',
                        'translationable_id' => $category->id,
                        'locale' => $key,
                        'key' => 'name',
                        'value' => $request->name[$index],
                    ));
                }
            }
        }

        if (count($data)) {
            Translation::insert($data);
        }

        if ($category->parent_id == 0) {
            Toastr::success(translate('messages.category_added_successfully'));
        } else {
            Toastr::success(translate('messages.sub_category_added_successfully'));
        }

        return back();
    }

    public function edit($id)
    {
        $category = Category::withoutGlobalScope('translate')->findOrFail($id);
        return view('vendor-views.category.edit', compact('category'));
    }

    public function status(Request $request)
    {
        $category = Category::find($request->id);
        $category->status = $request->status;
        $category->save();
        if ($category->parent_id == 0) {
            Toastr::success(translate('messages.category_status_updated'));
        } else {
            Toastr::success(translate('messages.sub_category_status_updated'));
        }
        return back();
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|max:100|unique:categories,name,' . $id,
            'image' => 'nullable|max:2048',
            'name.0' => 'required',
        ], [
            'name.0.required' => translate('default_name_is_required'),
        ]);

        $category = Category::find($id);
        $slug = Str::slug($request->name[array_search('default', $request->lang)]);
        $category->slug = $category->slug ? $category->slug : "{$slug}{$category->id}";
        $category->name = $request->name[array_search('default', $request->lang)];
        $category->image = $request->hasFile('image')
            ? Helpers::update('category/', $category->image, 'png', $request->file('image'))
            : $category->image;

        $category->is_pushed = 'N';
        $category->save();

        $default_lang = str_replace('_', '-', app()->getLocale());

        foreach ($request->lang as $index => $key) {

            if ($default_lang == $key && !($request->name[$index])) {
                if (isset($category->name) && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\Category',
                            'translationable_id' => $category->id,
                            'locale' => $key,
                            'key' => 'name'
                        ],
                        ['value' => $category->name]
                    );
                }
            } else {

                if ($request->name[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\Category',
                            'translationable_id' => $category->id,
                            'locale' => $key,
                            'key' => 'name'
                        ],
                        ['value' => $request->name[$index]]
                    );
                }
            }
        }
        if ($category->parent_id == 0) {
            Toastr::success(translate('messages.category_updated_successfully'));
        } else {
            Toastr::success(translate('messages.sub_category_updated_successfully'));
        }
        return back();
    }

    public function delete(Request $request)
    {
        $category = Category::findOrFail($request->id);
        if ($category && $category->childes && $category->childes->count() == 0) {
            if ($category->translations()) {
                $category->translations()->delete();
            }

            $category->delete();

            if ($category->parent_id == 0) {
                Toastr::success(translate('messages.Category removed!'));
            } else {
                Toastr::success(translate('messages.Sub Category removed!'));
            }
        } else {
            Toastr::warning(translate('messages.remove_sub_categories_first'));
        }
        return back();
    }

    public function update_priority(Category $category, Request $request)
    {
        $priority = $request->priority ?? 0;
        $category->priority = $priority;
        $category->save();
        Toastr::success(translate('messages.category_priority_updated successfully'));
        return back();
    }

}
