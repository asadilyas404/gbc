{{-- @php use App\CentralLogics\Helpers; @endphp --}}
{{-- @php($restaurant_data = Helpers::get_restaurant_data()) --}}
@foreach ($subcategories as $subCategory)
    <a href="javascript:void(0);"
       class="subcategory-item {{ request()->get('subcategory_id') == $subCategory->id ? 'selected' : '' }}"
       data-subcategory="{{ $subCategory->id }}">
        <div class="category-icon">
            <img src="{{ $subCategory['image_full_url'] }}" alt="{{ $subCategory->name }}">
        </div>
        <div class="category-name">{{ $subCategory->name }}</div>
    </a>
@endforeach
