<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;

class MapController extends Controller
{
    public function getPlaceDetails(Request $request)
    {
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');

        $client = new Client();

        $apiKey = env('GOOGLE_MAPS_API_KEY');

        // Google Maps Reverse Geocoding API URL
        $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$latitude},{$longitude}&key={$apiKey}";

        try {
            $response = $client->get($url);
            $data = json_decode($response->getBody(), true);

            if ($data['status'] === 'OK') {
                // ترجع بيانات العنوان أو أي بيانات تحتاجها
                return response()->json([
                    'status' => 'success',
                    'address' => $data['results'][0]['formatted_address'] ?? 'No address found',
                    'raw' => $data
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Google Maps API error: ' . $data['status'],
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Request failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
