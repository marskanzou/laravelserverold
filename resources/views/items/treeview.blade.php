@foreach ($categories as $category)
    <div class="category">
        <label>
            <input type="radio" name="selected_category" value="{{ $category->id }}"
                @if($selected_category == $category->id) checked @endif
                @if($category->subcategories->isNotEmpty()) disabled @endif>
            {{ $category->name }}
        </label>
        @if ($category->subcategories->isNotEmpty())
            <i class="fas toggle-button" style="font-size: 24px">&#xf0da;</i>
            <div class="subcategories" style="display: none;">
                @include('items.treeview', ['categories' => $category->subcategories, 'selected_category' => $selected_category])
            </div>
        @endif
    </div>
@endforeach
