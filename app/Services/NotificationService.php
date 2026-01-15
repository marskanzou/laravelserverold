<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\UserFcmToken;
use Google\Client;
use Google\Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationService {

    /**
     * Send FCM notification using HTTP v1 API.
     * Supports multilingual title and body.
     *
     * @param array $registrationIDs
     * @param string|array $title
     * @param string|array $message
     * @param string $type
     * @param array $customBodyFields
     * @return array
     */
    public static function sendFcmNotification(
        array $registrationIDs,
        string|array $title = '',
        string|array $message = '',
        string $type = 'default',
        array $customBodyFields = []
    ): array {
        try {
            // Validate project id
            $project_setting = Setting::select('value')->where('name', 'firebase_project_id')->first();
            if (empty($project_setting) || empty($project_setting->value)) {
                return [
                    'error' => true,
                    'message' => 'FCM configurations are not configured (firebase_project_id missing).'
                ];
            }

            $project_id = $project_setting->value;
            $url = 'https://fcm.googleapis.com/v1/projects/' . $project_id . '/messages:send';

            // Get access token
            $access_token_result = self::getAccessToken();
            if (!empty($access_token_result['error'])) {
                return $access_token_result;
            }
            $access_token = $access_token_result['data'];

            // Fetch device info and users who allow notifications
            $deviceInfo = UserFcmToken::with('user')
                ->select(['platform_type', 'fcm_token'])
                ->whereIn('fcm_token', $registrationIDs)
                ->whereHas('user', fn($q) => $q->where('notification', 1))
                ->get();

            if ($deviceInfo->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No valid device tokens found or users have notifications disabled.'
                ];
            }

            // Convert title and message to string if array (JSON encode)
            $titlePayload = is_array($title) ? json_encode($title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$title;
            $messagePayload = is_array($message) ? json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$message;

            // Standard payload data
            $dataWithTitle = array_merge($customBodyFields, [
                'title' => $titlePayload,
                'body'  => $messagePayload,
                'type'  => $type,
            ]);

            $results = [];

            foreach ($registrationIDs as $registrationID) {
                $platform = $deviceInfo->first(fn($q) => $q->fcm_token === $registrationID);
                if (!$platform) {
                    $results[$registrationID] = [
                        'error' => true,
                        'message' => 'Token not registered or user disabled notifications.'
                    ];
                    continue;
                }

                $flattenedData = self::convertToStringRecursively($dataWithTitle);

                $fcmMessage = [
                    'token' => $registrationID,
                    'data'  => $flattenedData,
                ];

                $androidConfig = [
                    'priority' => 'HIGH',
                    'notification' => [
                        'title' => $titlePayload,
                        'body'  => $messagePayload,
                        'sound' => 'default',
                    ],
                ];

                $apnsPayload = [
                    'headers' => ['apns-priority' => '10'],
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $titlePayload,
                                'body'  => $messagePayload,
                            ],
                            'sound' => 'default',
                            'content-available' => 1,
                        ],
                    ],
                ];

                $payload = [
                    'message' => array_merge($fcmMessage, [
                        'android' => $androidConfig,
                        'apns'    => $apnsPayload,
                    ])
                ];

                $platformType = $platform->platform_type ?? '';
                if (strtolower($platformType) === 'android') {
                    $payload['message']['notification'] = [
                        'title' => $titlePayload,
                        'body'  => $messagePayload,
                    ];
                }

                $encodedData = json_encode($payload);
                $headers = [
                    'Authorization: Bearer ' . $access_token,
                    'Content-Type: application/json',
                ];

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedData);
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if (curl_errno($ch)) {
                    $err = curl_error($ch);
                    Log::error('FCM curl error: ' . $err, ['token' => $registrationID]);
                    $results[$registrationID] = ['error' => true, 'message' => 'Curl error: ' . $err];
                } else {
                    $decoded = json_decode($response, true);
                    if ($httpCode >= 200 && $httpCode < 300) {
                        $results[$registrationID] = ['error' => false, 'message' => 'Success', 'response' => $decoded ?? $response, 'http_code' => $httpCode];
                    } else {
                        Log::warning('FCM send returned non-2xx', ['http_code' => $httpCode, 'response' => $decoded ?? $response]);
                        $results[$registrationID] = ['error' => true, 'message' => 'FCM responded with HTTP ' . $httpCode, 'response' => $decoded ?? $response];
                    }
                }
                curl_close($ch);
            }

            return ['error' => false, 'message' => 'Processed tokens', 'data' => $results];

        } catch (Throwable $th) {
            Log::error('NotificationService exception: ' . $th->getMessage());
            return ['error' => true, 'message' => 'Exception: ' . $th->getMessage()];
        }
    }

    public static function getAccessToken(): array {
        try {
            $file_setting = Setting::select('value')->where('name', 'service_file')->first();
            if (empty($file_setting) || empty($file_setting->value)) {
                return ['error' => true, 'message' => 'FCM service file setting (service_file) not found.'];
            }

            $file_path = base_path('public/storage/' . $file_setting->value);
            if (!file_exists($file_path)) {
                return ['error' => true, 'message' => 'FCM Service File not found at: ' . $file_path];
            }

            $client = new Client();
            $client->setAuthConfig($file_path);
            $client->setScopes(['https://www.googleapis.com/auth/firebase.messaging']);

            $tokenArray = $client->fetchAccessTokenWithAssertion();
            if (isset($tokenArray['access_token'])) {
                return ['error' => false, 'message' => 'Access token generated successfully', 'data' => $tokenArray['access_token']];
            }
            return ['error' => true, 'message' => 'Could not fetch access token', 'data' => $tokenArray];

        } catch (Exception $e) {
            Log::error('getAccessToken exception: ' . $e->getMessage());
            return ['error' => true, 'message' => 'Exception while generating access token: ' . $e->getMessage()];
        }
    }

    public static function convertToStringRecursively($data, &$flattenedArray = null): array {
        if ($flattenedArray === null) $flattenedArray = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $flattenedArray[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (is_null($value)) {
                $flattenedArray[$key] = '';
            } elseif (is_bool($value)) {
                $flattenedArray[$key] = $value ? '1' : '0';
            } else {
                $flattenedArray[$key] = (string)$value;
            }
        }
        return $flattenedArray;
    }
}
