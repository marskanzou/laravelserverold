<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Item;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class BannerController extends Controller
{
    public function index()
    {
        $categories = Category::all();
        $items = Item::all();
        return view('banner.index', compact('categories', 'items'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:2048',
            'title' => 'required|string|max:255',
            'status' => 'required|boolean',
        ]);

        $banner = new Banner();
        $banner->title = $request->title;
        $banner->item_id = $request->item ?: null;
        $banner->category_id = $request->category_id ?: null;
        $banner->third_party_link = $request->link ?: null;
        $banner->status = $request->status;

        if ($request->hasFile('image')) {
            $banner->image = $request->file('image')->store('banners', 'public');
        }

        $banner->save();

        return redirect()->back()->with('success', 'Banner created successfully.');
    }

    public function update(Request $request, Banner $banner)
    {
        $request->validate([
            'image' => 'nullable|image|max:2048',
            'title' => 'required|string|max:255',
            'status' => 'required|boolean',
        ]);

        $banner->title = $request->title;
        $banner->item_id = $request->item ?: null;
        $banner->category_id = $request->category_id ?: null;
        $banner->third_party_link = $request->link ?: null;
        $banner->status = $request->status;

        if ($request->hasFile('image')) {
            if ($banner->image && Storage::disk('public')->exists($banner->image)) {
                Storage::disk('public')->delete($banner->image);
            }
            $banner->image = $request->file('image')->store('banners', 'public');
        }

        $banner->save();

        return redirect()->back()->with('success', 'Banner updated successfully.');
    }

    public function destroy(Banner $banner)
    {
        if ($banner->image && Storage::disk('public')->exists($banner->image)) {
            Storage::disk('public')->delete($banner->image);
        }
        $banner->delete();

        return response()->json(['success' => true, 'message' => 'Banner deleted successfully.']);
    }

    public function list(Request $request)
    {
        $query = Banner::with(['item', 'category']);

        if ($search = $request->get('search')) {
            $query->where('title', 'like', "%$search%");
        }

        $total = $query->count();

        $limit = $request->get('limit', 10);
        $offset = $request->get('offset', 0);
        $sort = $request->get('sort', 'id');
        $order = $request->get('order', 'desc');

        $banners = $query->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $rows = $banners->map(function ($banner) {
            // 🔥 إضافة عمود الأكشن مثل السلايدر
            $operate = '';
            if (Auth::user()->can('banner-edit')) {
                $operate .= '<a href="' . route('banner.edit', $banner->id) . '" class="btn btn-sm btn-primary me-1">
                                <i class="bi bi-pencil"></i>
                             </a>';
            }
            if (Auth::user()->can('banner-delete')) {
                $operate .= '<button class="btn btn-sm btn-danger delete-banner" data-id="' . $banner->id . '">
                                <i class="bi bi-trash"></i>
                             </button>';
            }

            return [
                'id' => $banner->id,
                'title' => $banner->title,
                'image' => $banner->image ? asset('storage/' . $banner->image) : null,
                'model_type' => $banner->item_id ? 'Item' : ($banner->category_id ? 'Category' : 'Third Party Link'),
                'model' => [
                    'id' => $banner->item->id ?? ($banner->category->id ?? null),
                    'name' => $banner->item->name ?? ($banner->category->name ?? ''),
                ],
                'third_party_link' => $banner->third_party_link ?? '',
                'status' => $banner->status,
                'operate' => $operate, // ✅ أهم إضافة
            ];
        });

        return response()->json([
            'total' => $total,
            'rows' => $rows,
        ]);
    }
}


