@php
    $selected_subcategory = session('current_sub_category_id', null);
@endphp
@foreach ($subcategories as $subCategory)
    <a href="javascript:void(0);"
       class="subcategory-item {{ $selected_subcategory == $subCategory->id ? 'selected' : '' }}"
       data-subcategory="{{ $subCategory->id }}">
        <div class="category-icon">
            <img src="{{ $subCategory['image_full_url'] }}" alt="{{ $subCategory->name }}">
        </div>
        <div class="category-name">{{ $subCategory->name }}</div>
    </a>
@endforeach
