<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WhatsappController extends Controller
{
    private $apiUrl;
    private $apiToken;

    public function __construct() {
        $this->apiUrl = 'http://localhost:3000/admin';
        $this->apiToken = env('API_TOKEN');
    }

    public function resetSession() {
        $res = Http::withHeaders(['Authorization'=>'Bearer '.$this->apiToken])
            ->post($this->apiUrl.'/reset-session');
        return redirect()->back()->with('status', $res->json('message'));
    }

    public function getQR() {
        $res = Http::withHeaders(['Authorization'=>'Bearer '.$this->apiToken])
            ->get($this->apiUrl.'/qr');
        return response()->json($res->json());
    }
}

