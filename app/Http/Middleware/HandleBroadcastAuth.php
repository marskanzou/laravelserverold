<?php

// namespace App\Http\Middleware;

// use Closure;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Log;

// class HandleBroadcastAuth
// {
//     public function handle(Request $request, Closure $next)
//     {
//         try {
//             Log::info('Broadcast auth middleware start', [
//                 'user' => $request->user() ? $request->user()->id : 'none',
//                 'channel' => $request->channel_name,
//                 'socket_id' => $request->socket_id
//             ]);

//             $response = $next($request);
            
//             Log::info('Broadcast auth middleware success', [
//                 'response' => $response->getContent()
//             ]);
            
//             return $response;
            
//         } catch (\Exception $e) {
//             Log::error('Broadcast middleware error', [
//                 'message' => $e->getMessage(),
//                 'file' => $e->getFile(),
//                 'line' => $e->getLine()
//             ]);
            
//             throw $e;
//         }
//     }
// }