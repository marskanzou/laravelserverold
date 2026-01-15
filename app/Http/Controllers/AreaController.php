<?php
namespace App\Http\Controllers;

use App\Models\Area;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    public function index(Request $request)
    {
        $query = Area::query();

        if ($request->has('filter')) {
            $query->filter($request->filter);
        }

        if ($request->has('search')) {
            $query->search($request->search);
        }

        return $query->with(['city', 'state', 'country'])->get();
 
    }


public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'city_id' => 'required|exists:cities,id',
        'state_id' => 'required|exists:states,id',
        'country_id' => 'required|exists:countries,id',
        'latitude' => 'required|numeric|between:-90,90',
        'longitude' => 'required|numeric|between:-180,180',
    ]);

    $area = Area::create($validated);

    return response()->json([
        'message' => 'تم إنشاء المنطقة بنجاح.',
        'data' => $area
    ], 201);
}


}
