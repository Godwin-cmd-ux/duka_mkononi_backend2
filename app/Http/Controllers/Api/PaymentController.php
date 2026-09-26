<?php

namespace App\Http\Controllers\Api;

use App\Models\Advertisement;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class PaymentController extends BaseController
{
    private function pesapalConfig(): array
    {
        $env = config('pesapal.env', env('PESAPAL_ENV', 'sandbox'));
        return [
            'baseUrl' => $env === 'live'
                ? 'https://pay.pesapal.com/v3'
                : 'https://cybqa.pesapal.com/pesapalv3',
            'consumerKey' => config('pesapal.consumer_key', env('PESAPAL_CONSUMER_KEY', '')),
            'consumerSecret' => config('pesapal.consumer_secret', env('PESAPAL_CONSUMER_SECRET', '')),
            'notificationId' => config('pesapal.notification_id', env('PESAPAL_NOTIFICATION_ID', '')),
            'callbackUrl' => config('pesapal.callback_url', env('PESAPAL_CALLBACK_URL', 'https://www.dukamkononi.com/api/payments/pesapal-callback')),
            'ipnUrl' => config('pesapal.ipn_url', env('PESAPAL_IPN_URL', 'https://www.dukamkononi.com/api/payments/pesapal-ipn')),
        ];
    }

    private function matangazoPrice(): float
    {
        return (float) (config('pesapal.matangazo_price', env('MATANGAZO_PRICE') ?: 3000));
    }

    private function matangazoDurationDays(): int
    {
        return (int) (config('pesapal.matangazo_duration_days', env('MATANGAZO_DURATION_DAYS') ?: 30));
    }

    private function updateLastSeen(string $userId): void
    {
        try {
            User::where('id', $userId)->update(['last_seen' => now()->toISOString(true)]);
        } catch (Throwable $e) {
        }
    }

    private function orderTrackingId(): string
    {
        $rand = '';
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        for ($i = 0; $i < 9; $i++) {
            $rand .= $chars[random_int(0, 35)];
        }
        return 'DUKA-' . (int) round(microtime(true) * 1000) . '-' . $rand;
    }

    private function getPesapalAccessToken()
    {
        $config = $this->pesapalConfig();
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($config['baseUrl'] . '/api/Auth/RequestToken', [
            'consumer_key' => $config['consumerKey'],
            'consumer_secret' => $config['consumerSecret'],
        ]);

        if (!$response->ok()) {
            throw new \Exception('Failed to get access token: ' . $response->status());
        }

        $data = $response->json();
        return $data['token'] ?? null;
    }

    private function submitPesapalOrder(array $orderData, $accessToken)
    {
        $config = $this->pesapalConfig();
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
        ])->post($config['baseUrl'] . '/api/Transactions/SubmitOrderRequest', $orderData);

        if (!$response->ok()) {
            throw new \Exception('Failed to submit order: ' . $response->status());
        }

        return $response->json();
    }

    private function getPesapalOrderStatus(string $orderTrackingId, $accessToken)
    {
        $config = $this->pesapalConfig();
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
        ])->get($config['baseUrl'] . '/api/Transactions/GetTransactionStatus?orderTrackingId=' . $orderTrackingId);

        if (!$response->ok()) {
            throw new \Exception('Failed to get order status: ' . $response->status());
        }

        return $response->json();
    }

    private function activateMatangazoAfterPayment($matangazoId, $orderTrackingId = null)
    {
        try {
            $matangazo = Advertisement::where('id', $matangazoId)->first();
            if (!$matangazo) {
                return null;
            }
            $now = now();
            $currentExpiry = $matangazo->expires_at ? new \DateTime($matangazo->expires_at) : null;
            $base = ($currentExpiry && $currentExpiry > $now) ? $currentExpiry : $now;
            $newExpiresAt = $base->modify('+' . $this->matangazoDurationDays() . ' days')->format('c');

            Advertisement::where('id', $matangazoId)->update([
                'payment_status' => 'completed',
                'is_free' => false,
                'is_active' => true,
                'order_tracking_id' => $orderTrackingId ?: null,
                'expires_at' => $newExpiresAt,
                'updated_at' => now()->toISOString(true),
            ]);

            return $newExpiresAt;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function requestIp(Request $request): ?string
    {
        return $request->header('x-forwarded-for', '') ?: $request->ip();
    }

    public function configStatus(Request $request): JsonResponse
    {
        $config = $this->pesapalConfig();
        return $this->json([
            'configured' => !!(($config['consumerKey'] && $config['consumerSecret']) ? true : false),
            'env' => config('pesapal.env', env('PESAPAL_ENV', 'sandbox')),
            'baseUrl' => $config['baseUrl'],
            'callbackUrl' => $config['callbackUrl'],
            'ipnUrl' => $config['ipnUrl'],
            'notificationIdSet' => !!$config['notificationId'],
        ]);
    }

    public function initiate(Request $request): JsonResponse
    {
        try {
            $userId = $this->userId($request);
            $amount = $request->input('amount');
            $description = $request->input('description');
            $matangazoId = $request->input('matangazo_id');
            $phone = $request->input('phone');
            $email = $request->input('email');
            $name = $request->input('name');

            $this->updateLastSeen($userId);

            $validatedAmount = $matangazoId ? $this->matangazoPrice() : (float) $amount;

            if (!$validatedAmount || $validatedAmount <= 0 || !$description) {
                $this->log($userId, 'PAYMENT_INITIATE_FAILED', '/api/payments/pesapal/initiate', [
                    'reason' => 'Missing or invalid amount/description',
                ], $this->ip($request), 'failed');
                return $this->error('Kiasi na maelezo ya malipo yanahitajika', 400);
            }

            if ($matangazoId) {
                $ownedMatangazo = Advertisement::where('id', $matangazoId)
                    ->where('user_id', $userId)
                    ->first();

                if (!$ownedMatangazo) {
                    $this->log($userId, 'PAYMENT_INITIATE_FAILED', '/api/payments/pesapal/initiate', [
                        'reason' => 'Matangazo not owned by user',
                        'matangazo_id' => $matangazoId,
                    ], $this->ip($request), 'failed');
                    return $this->error('Huna ruhusa ya kulipia matangazo haya', 403);
                }
            }

            $config = $this->pesapalConfig();
            if (!$config['consumerKey'] || !$config['consumerSecret']) {
                $this->log($userId, 'PAYMENT_INITIATE_FAILED', '/api/payments/pesapal/initiate', [
                    'reason' => 'PesaPal not configured',
                ], $this->ip($request), 'failed');
                return $this->json([
                    'error' => 'PesaPal haijasanidiwa. Tafadhali wasiliana na msimamizi.',
                    'code' => 'PESAPAL_NOT_CONFIGURED',
                ], 500);
            }

            $orderTrackingId = $this->orderTrackingId();
            $accessToken = $this->getPesapalAccessToken();
            $user = $this->user($request);

            $orderData = [
                'id' => $orderTrackingId,
                'currency' => 'TZS',
                'amount' => number_format($validatedAmount, 2, '.', ''),
                'description' => $description,
                'callback_url' => $config['callbackUrl'],
                'notification_id' => $config['notificationId'],
                'billing_address' => [
                    'email_address' => $email ?: ($user->email ?? ''),
                    'phone_number' => $phone ?: ($user->phone ?? '255000000000'),
                    'country_code' => 'TZ',
                    'first_name' => $name ?: ($user->full_name ?? 'Customer'),
                    'middle_name' => '',
                    'last_name' => '',
                    'line_1' => '',
                    'line_2' => '',
                    'city' => '',
                    'state' => '',
                    'postal_code' => '',
                    'zip_code' => '',
                ],
            ];

            $pesapalResponse = $this->submitPesapalOrder($orderData, $accessToken);

            if (empty($pesapalResponse['redirect_url'])) {
                throw new \Exception('PesaPal haikurudisha URL ya malipo');
            }

            $now = now()->toISOString(true);
            $paymentData = [
                'id' => (string) Str::uuid(),
                'order_tracking_id' => $orderTrackingId,
                'amount' => $validatedAmount,
                'status' => 'pending',
                'user_id' => $userId,
                // Persist the matangazo link (server.js did this; the port to
                // Laravel dropped it, so completed payments could never find
                // which matangazo to activate).
                'matangazo_id' => $matangazoId ?: null,
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $newPayment = Payment::create($paymentData);

            $this->log($userId, 'PAYMENT_INITIATED', '/api/payments/pesapal/initiate', [
                'order_tracking_id' => $orderTrackingId,
                'amount' => $amount,
                'matangazo_id' => $matangazoId,
            ], $this->ip($request), 'success');

            return $this->json([
                'success' => true,
                'message' => 'Malipo yameanzishwa kikamilifu!',
                'payment' => [
                    'id' => $newPayment->id,
                    'order_tracking_id' => $orderTrackingId,
                    'amount' => $validatedAmount,
                    'status' => 'pending',
                    'redirect_url' => $pesapalResponse['redirect_url'],
                ],
                'redirect_url' => $pesapalResponse['redirect_url'],
            ]);
        } catch (Throwable $error) {
            $this->log($this->userId($request), 'PAYMENT_INITIATE_ERROR', '/api/payments/pesapal/initiate', [
                'error' => $error->getMessage(),
            ], $this->ip($request), 'failed');

            return $this->json([
                'error' => 'Hitilafu katika kuanzisha malipo',
                'details' => $error->getMessage(),
            ], 500);
        }
    }

    public function pesapalStatus(Request $request, string $order_tracking_id): JsonResponse
    {
        try {
            $userId = $this->userId($request);

            $this->updateLastSeen($userId);

            if (!isset($order_tracking_id) || $order_tracking_id === '') {
                $this->log($userId, 'PAYMENT_STATUS_FAILED', '/api/payments/pesapal/status', [
                    'reason' => 'Missing order tracking ID',
                ], $this->ip($request), 'failed');
                return $this->error('Order tracking ID inahitajika', 400);
            }

            $payment = Payment::where('order_tracking_id', $order_tracking_id)->first();

            if (!$payment) {
                $this->log($userId, 'PAYMENT_STATUS_FAILED', '/api/payments/pesapal/status/' . $order_tracking_id, [
                    'reason' => 'Payment not found',
                    'order_tracking_id' => $order_tracking_id,
                ], $this->ip($request), 'failed');
                return $this->error('Malipo hayajapatikana', 404);
            }

            if ($this->role($request) !== 'admin' && $payment->user_id !== $userId) {
                $this->log($userId, 'PAYMENT_STATUS_FAILED', '/api/payments/pesapal/status/' . $order_tracking_id, [
                    'reason' => 'Unauthorized access',
                    'order_tracking_id' => $order_tracking_id,
                    'payment_owner' => $payment->user_id,
                ], $this->ip($request), 'failed');
                return $this->error('Huna ruhusa ya kuona malipo haya', 403);
            }

            if ($payment->status === 'completed') {
                $this->log($userId, 'PAYMENT_STATUS_CHECK', '/api/payments/pesapal/status/' . $order_tracking_id, [
                    'order_tracking_id' => $order_tracking_id,
                    'status' => 'completed',
                ], $this->ip($request), 'success');
                return $this->json([
                    'success' => true,
                    'payment' => $payment,
                    'message' => 'Malipo yamekamilika',
                ]);
            }

            try {
                $accessToken = $this->getPesapalAccessToken();
                $pesapalStatus = $this->getPesapalOrderStatus($order_tracking_id, $accessToken);

                $newStatus = $payment->status;
                $message = 'Malipo bado yanasubiri';

                if (isset($pesapalStatus['status_code']) && $pesapalStatus['status_code'] === '1') {
                    $newStatus = 'completed';
                    $message = 'Malipo yamekamilika kikamilifu!';

                    Payment::where('order_tracking_id', $order_tracking_id)->update([
                        'status' => 'completed',
                        'paid_at' => now()->toISOString(true),
                        'updated_at' => now()->toISOString(true),
                    ]);

                    if (!empty($payment->matangazo_id)) {
                        $this->activateMatangazoAfterPayment($payment->matangazo_id, $order_tracking_id);
                    }
                } elseif (isset($pesapalStatus['status_code']) && $pesapalStatus['status_code'] === '2') {
                    $newStatus = 'failed';
                    $message = 'Malipo yameshindikana';

                    Payment::where('order_tracking_id', $order_tracking_id)->update([
                        'status' => 'failed',
                        'updated_at' => now()->toISOString(true),
                    ]);
                }

                $updatedPayment = Payment::where('order_tracking_id', $order_tracking_id)->first();

                $this->log($userId, 'PAYMENT_STATUS_CHECK', '/api/payments/pesapal/status/' . $order_tracking_id, [
                    'order_tracking_id' => $order_tracking_id,
                    'old_status' => $payment->status,
                    'new_status' => $newStatus,
                    'pesapal_status' => $pesapalStatus['status_code'] ?? null,
                ], $this->ip($request), 'success');

                return $this->json([
                    'success' => true,
                    'payment' => $updatedPayment,
                    'pesapal_status' => $pesapalStatus,
                    'message' => $message,
                ]);
            } catch (Throwable $pesapalError) {
                $this->log($userId, 'PAYMENT_STATUS_CHECK_ERROR', '/api/payments/pesapal/status/' . $order_tracking_id, [
                    'order_tracking_id' => $order_tracking_id,
                    'error' => $pesapalError->getMessage(),
                ], $this->ip($request), 'failed');

                return $this->json([
                    'success' => true,
                    'payment' => $payment,
                    'message' => 'Imeshindikana kuangalia hali ya malipo kutoka PesaPal, lakini malipo yana hali ifuatayo: ' . $payment->status,
                ]);
            }
        } catch (Throwable $error) {
            $this->log($this->userId($request), 'PAYMENT_STATUS_CHECK_ERROR', '/api/payments/pesapal/status/' . $order_tracking_id, [
                'error' => $error->getMessage(),
            ], $this->ip($request), 'failed');

            return $this->json([
                'error' => 'Hitilafu katika kuangalia hali ya malipo',
                'details' => $error->getMessage(),
            ], 500);
        }
    }

    public function statusByTracking(Request $request, string $order_tracking_id): JsonResponse
    {
        try {
            $userId = $this->userId($request);

            $this->updateLastSeen($userId);

            $payment = Payment::where('order_tracking_id', $order_tracking_id)->first();

            if (!$payment) {
                $this->log($userId, 'PAYMENT_STATUS_FAILED', '/api/payments/status/' . $order_tracking_id, [
                    'reason' => 'Payment record not found',
                    'order_tracking_id' => $order_tracking_id,
                ], $this->ip($request), 'failed');
                return $this->error('Rekodi ya malipo haijapatikana', 404);
            }

            $this->log($userId, 'PAYMENT_STATUS_CHECK', '/api/payments/status/' . $order_tracking_id, [
                'order_tracking_id' => $order_tracking_id,
                'status' => $payment->status,
            ], $this->ip($request), 'success');

            return $this->json($payment);
        } catch (Throwable $error) {
            $this->log($this->userId($request), 'PAYMENT_STATUS_CHECK_ERROR', '/api/payments/status/' . $order_tracking_id, [
                'error' => $error->getMessage(),
            ], $this->ip($request), 'failed');
            return $this->dbError($error);
        }
    }

    public function ipn(Request $request): JsonResponse
    {
        $ip = $this->requestIp($request);

        try {
            $OrderTrackingId = $request->input('OrderTrackingId');
            $OrderNotificationType = $request->input('OrderNotificationType');
            $OrderMerchantReference = $request->input('OrderMerchantReference');
            $Status = $request->input('Status');

            if (!$OrderTrackingId) {
                $this->log(null, 'PESAPAL_IPN_FAILED', '/api/payments/pesapal-ipn', [
                    'reason' => 'Missing OrderTrackingId',
                ], $ip, 'failed');
                return $this->error('OrderTrackingId inahitajika', 400);
            }

            $this->log(null, 'PESAPAL_IPN_RECEIVED', '/api/payments/pesapal-ipn', [
                'OrderTrackingId' => $OrderTrackingId,
                'Status' => $Status,
            ], $ip, 'success');

            try {
                $paymentStatus = 'pending';
                if ($Status === 'COMPLETED') $paymentStatus = 'completed';
                elseif ($Status === 'FAILED') $paymentStatus = 'failed';
                elseif ($Status === 'PENDING') $paymentStatus = 'pending';

                $payment = Payment::where('order_tracking_id', $OrderTrackingId)->first();

                if (!$payment) {
                    return $this->json([
                        'status' => 'success',
                        'message' => 'IPN received successfully',
                        'OrderTrackingId' => $OrderTrackingId,
                    ], 200);
                }

                $updateData = [
                    'status' => $paymentStatus,
                    'updated_at' => now()->toISOString(true),
                ];

                if ($paymentStatus === 'completed') {
                    $updateData['paid_at'] = now()->toISOString(true);
                }

                Payment::where('order_tracking_id', $OrderTrackingId)->update($updateData);

                $this->log($payment->user_id, 'PAYMENT_STATUS_UPDATED_IPN', 'IPN_PROCESSING', [
                    'order_tracking_id' => $OrderTrackingId,
                    'old_status' => $payment->status,
                    'new_status' => $paymentStatus,
                    'source' => 'pesapal_ipn',
                ], $ip, 'success');

                if ($paymentStatus === 'completed' && !empty($payment->matangazo_id)) {
                    $this->activateMatangazoAfterPayment($payment->matangazo_id, $OrderTrackingId);
                }
            } catch (Throwable $asyncError) {
            }

            return $this->json([
                'status' => 'success',
                'message' => 'IPN received successfully',
                'OrderTrackingId' => $OrderTrackingId,
            ], 200);
        } catch (Throwable $error) {
            $this->log(null, 'PESAPAL_IPN_ERROR', '/api/payments/pesapal-ipn', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->json([
                'status' => 'error_but_acknowledged',
                'message' => 'IPN received but processing failed: ' . $error->getMessage(),
            ], 200);
        }
    }

    public function callback(Request $request): JsonResponse
    {
        $ip = $this->requestIp($request);

        try {
            $OrderTrackingId = $request->input('OrderTrackingId');
            $OrderMerchantReference = $request->input('OrderMerchantReference');
            $Status = $request->input('Status');

            if ($OrderTrackingId) {
                $paymentStatus = 'pending';
                if ($Status === 'COMPLETED') $paymentStatus = 'completed';
                elseif ($Status === 'FAILED') $paymentStatus = 'failed';

                Payment::where('order_tracking_id', $OrderTrackingId)->update([
                    'status' => $paymentStatus,
                    'paid_at' => $paymentStatus === 'completed' ? now()->toISOString(true) : null,
                    'updated_at' => now()->toISOString(true),
                ]);

                if ($Status === 'COMPLETED') {
                    $payment = Payment::where('order_tracking_id', $OrderTrackingId)->first();

                    if ($payment && !empty($payment->matangazo_id)) {
                        $this->activateMatangazoAfterPayment($payment->matangazo_id, $OrderTrackingId);
                    }

                    if ($payment && !empty($payment->user_id)) {
                        $this->log($payment->user_id, 'PAYMENT_CALLBACK', '/api/payments/pesapal-callback', [
                            'order_tracking_id' => $OrderTrackingId,
                            'status' => 'completed',
                            'source' => 'pesapal_callback',
                        ], $ip, 'success');
                    }
                }
            }

            $this->log(null, 'PESAPAL_CALLBACK_RECEIVED', '/api/payments/pesapal-callback', [
                'OrderTrackingId' => $OrderTrackingId ?: 'unknown',
                'Status' => $Status ?: 'unknown',
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'message' => 'Callback received successfully',
                'OrderTrackingId' => $OrderTrackingId ?: 'unknown',
                'Status' => $Status ?: 'unknown',
            ]);
        } catch (Throwable $error) {
            $this->log(null, 'PESAPAL_CALLBACK_ERROR', '/api/payments/pesapal-callback', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->json([
                'success' => false,
                'error' => 'Callback processing failed',
                'details' => $error->getMessage(),
            ], 500);
        }
    }

    public function my(Request $request): JsonResponse
    {
        try {
            $userId = $this->userId($request);

            $this->updateLastSeen($userId);

            $payments = Payment::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();

            $this->log($userId, 'PAYMENTS_VIEW_MY', '/api/payments/my', [
                'count' => count($payments),
            ], $this->ip($request), 'success');

            return $this->json($payments ?: []);
        } catch (Throwable $error) {
            $this->log($this->userId($request), 'PAYMENTS_VIEW_ERROR', '/api/payments/my', [
                'error' => $error->getMessage(),
            ], $this->ip($request), 'failed');
            return $this->dbError($error);
        }
    }

    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $paymentId = $id;
            $userId = $this->userId($request);

            $this->updateLastSeen($userId);

            $payment = Payment::where('id', $paymentId)->first();

            if (!$payment) {
                $this->log($userId, 'PAYMENT_VIEW_FAILED', '/api/payments/' . $paymentId, [
                    'reason' => 'Payment not found',
                    'payment_id' => $paymentId,
                ], $this->ip($request), 'failed');
                return $this->error('Malipo hayajapatikana', 404);
            }

            if ($this->role($request) !== 'admin' && $payment->user_id !== $userId) {
                $this->log($userId, 'PAYMENT_VIEW_FAILED', '/api/payments/' . $paymentId, [
                    'reason' => 'Unauthorized access',
                    'payment_id' => $paymentId,
                    'payment_owner' => $payment->user_id,
                ], $this->ip($request), 'failed');
                return $this->error('Huna ruhusa ya kuona malipo haya', 403);
            }

            $this->log($userId, 'PAYMENT_VIEW', '/api/payments/' . $paymentId, [
                'payment_id' => $paymentId,
                'status' => $payment->status,
                'amount' => $payment->amount,
            ], $this->ip($request), 'success');

            return $this->json($payment);
        } catch (Throwable $error) {
            $this->log($this->userId($request), 'PAYMENT_VIEW_ERROR', '/api/payments/' . $id, [
                'error' => $error->getMessage(),
                'payment_id' => $id,
            ], $this->ip($request), 'failed');
            return $this->dbError($error);
        }
    }

    public function record(Request $request): JsonResponse
    {
        try {
            $userId = $this->userId($request);
            $order_tracking_id = $request->input('order_tracking_id');
            $amount = $request->input('amount');
            $status = $request->input('status');
            $description = $request->input('description');
            $matangazo_id = $request->input('matangazo_id');

            $this->updateLastSeen($userId);

            $now = now()->toISOString(true);
            $paymentData = [
                'id' => (string) Str::uuid(),
                'order_tracking_id' => $order_tracking_id,
                'amount' => (float) $amount,
                'status' => $status ?: 'pending',
                'user_id' => $userId,
                'description' => $description ?: null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $newPayment = Payment::create($paymentData);

            $this->log($userId, 'PAYMENT_RECORDED', '/api/payments/record', [
                'order_tracking_id' => $order_tracking_id,
                'amount' => $amount,
                'status' => $status,
            ], $this->ip($request), 'success');

            return $this->json(['success' => true, 'payment' => $newPayment]);
        } catch (Throwable $error) {
            $this->log($this->userId($request), 'PAYMENT_RECORD_ERROR', '/api/payments/record', [
                'error' => $error->getMessage(),
            ], $this->ip($request), 'failed');
            return $this->dbError($error);
        }
    }

    public function simulate(Request $request): JsonResponse
    {
        $ip = $this->requestIp($request);

        try {
            $order_tracking_id = $request->input('order_tracking_id');
            $status = $request->input('status');

            if (!$order_tracking_id || !$status) {
                $this->log(null, 'PAYMENT_SIMULATE_FAILED', '/api/payments/simulate', [
                    'reason' => 'Missing order tracking ID or status',
                ], $ip, 'failed');
                return $this->error('Order tracking ID na status zinahitajika', 400);
            }

            $payment = Payment::where('order_tracking_id', $order_tracking_id)->first();

            if (!$payment) {
                $this->log(null, 'PAYMENT_SIMULATE_FAILED', '/api/payments/simulate', [
                    'reason' => 'Payment not found',
                    'order_tracking_id' => $order_tracking_id,
                ], $ip, 'failed');
                return $this->error('Malipo hayajapatikana', 404);
            }

            $updateData = [
                'status' => $status,
                'updated_at' => now()->toISOString(true),
            ];

            if ($status === 'completed') {
                $updateData['paid_at'] = now()->toISOString(true);
            }

            Payment::where('order_tracking_id', $order_tracking_id)->update($updateData);

            if ($status === 'completed' && !empty($payment->matangazo_id)) {
                Advertisement::where('id', $payment->matangazo_id)->update([
                    'payment_status' => 'completed',
                    'is_free' => false,
                    'updated_at' => now()->toISOString(true),
                ]);
            }

            $this->log($payment->user_id, 'PAYMENT_SIMULATED', '/api/payments/simulate', [
                'order_tracking_id' => $order_tracking_id,
                'old_status' => $payment->status,
                'new_status' => $status,
                'simulated' => true,
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'message' => 'Malipo yamesimuliwa kikamilifu! Status: ' . $status,
                'order_tracking_id' => $order_tracking_id,
                'status' => $status,
            ]);
        } catch (Throwable $error) {
            $this->log(null, 'PAYMENT_SIMULATE_ERROR', '/api/payments/simulate', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->json([
                'error' => 'Hitilafu katika kusimulia malipo',
                'details' => $error->getMessage(),
            ], 500);
        }
    }

    public function adminIndex(Request $request): JsonResponse
    {
        try {
            $userId = $this->userId($request);

            if ($this->role($request) !== 'admin') {
                $this->log($userId, 'ADMIN_UNAUTHORIZED', '/api/admin/payments', [
                    'reason' => 'Non-admin access attempt',
                ], $this->ip($request), 'failed');
                return $this->error('Unauthorized', 403);
            }

            $payments = Payment::orderBy('created_at', 'desc')->get()->toArray();

            $this->log($userId, 'ADMIN_PAYMENTS_VIEW', '/api/admin/payments', [
                'count' => count($payments),
            ], $this->ip($request), 'success');

            return $this->json($payments ?: []);
        } catch (Throwable $error) {
            $this->log($this->userId($request), 'ADMIN_PAYMENTS_VIEW_ERROR', '/api/admin/payments', [
                'error' => $error->getMessage(),
            ], $this->ip($request), 'failed');
            return $this->dbError($error);
        }
    }
}