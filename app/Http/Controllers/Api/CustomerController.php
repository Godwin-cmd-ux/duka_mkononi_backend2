<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends BaseController
{
    public function my(Request $request)
    {
        try {
            $userId = $this->userId($request);

            $this->touchLastSeen($userId);

            $customers = Customer::where('seller_id', $userId)
                ->orderBy('total_purchases', 'desc')
                ->get()
                ->toArray();

            $this->log($userId, 'CUSTOMERS_VIEW', '/api/customers/my', [
                'count' => count($customers),
            ], $this->ip($request), 'success');

            return $this->json($customers);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'CUSTOMERS_VIEW_ERROR', '/api/customers/my', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }

    public function store(Request $request)
    {
        try {
            $userId = $this->userId($request);
            $name = $request->input('name');
            $phone = $request->input('phone');
            $email = $request->input('email');

            $this->touchLastSeen($userId);

            if (!$name) {
                $this->log($userId, 'CUSTOMER_CREATE_FAILED', '/api/customers', [
                    'reason' => 'Name required',
                ], $this->ip($request), 'failed');

                return $this->json(['error' => 'Jina la mteja linahitajika'], 400);
            }

            $newCustomerId = Str::uuid()->toString();
            $customerData = [
                'id' => $newCustomerId,
                'seller_id' => $userId,
                'name' => $name,
                'phone' => $phone ?: null,
                'email' => $email ?: null,
                'created_at' => $this->isoNow(),
                'updated_at' => $this->isoNow(),
            ];

            Customer::create($customerData);

            $newCustomer = Customer::where('id', $newCustomerId)->first()->toArray();

            $this->log($userId, 'CUSTOMER_CREATE', '/api/customers', [
                'customer_id' => $newCustomer['id'],
                'name' => $newCustomer['name'],
            ], $this->ip($request), 'success');

            return $this->json([
                'message' => 'Mteja ameongezwa kikamilifu!',
                'customer' => $newCustomer,
            ], 201);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'CUSTOMER_CREATE_ERROR', '/api/customers', ['error' => $e->getMessage()], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $customerId = $id;
            $userId = $this->userId($request);
            $name = $request->input('name');
            $phone = $request->input('phone');
            $email = $request->input('email');

            $this->touchLastSeen($userId);

            $customer = Customer::where('id', $customerId)->select(['seller_id'])->first();

            if (!$customer) {
                $this->log($userId, 'CUSTOMER_UPDATE_FAILED', '/api/customers/:id', [
                    'reason' => 'Customer not found',
                    'customer_id' => $customerId,
                ], $this->ip($request), 'failed');

                return $this->json(['error' => 'Mteja hajapatikana'], 404);
            }

            if ($customer->seller_id !== $userId) {
                $this->log($userId, 'CUSTOMER_UPDATE_FAILED', '/api/customers/:id', [
                    'reason' => 'Unauthorized access',
                    'customer_id' => $customerId,
                    'owner_id' => $customer->seller_id,
                ], $this->ip($request), 'failed');

                return $this->json([
                    'error' => 'Huna ruhusa ya kuhariri mteja huyu',
                ], 403);
            }

            $updateData = [
                'name' => $name,
                'phone' => $phone ?: null,
                'email' => $email ?: null,
                'updated_at' => $this->isoNow(),
            ];

            Customer::where('id', $customerId)->update($updateData);

            $updatedCustomer = Customer::where('id', $customerId)->first()->toArray();

            $this->log($userId, 'CUSTOMER_UPDATE', '/api/customers/:id', [
                'customer_id' => $customerId,
                'fields_updated' => array_keys($updateData),
            ], $this->ip($request), 'success');

            return $this->json([
                'message' => 'Mteja amesasishwa kikamilifu!',
                'customer' => $updatedCustomer,
            ]);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'CUSTOMER_UPDATE_ERROR', '/api/customers/:id', [
                'error' => $e->getMessage(),
                'customer_id' => $id,
            ], $this->ip($request), 'failed');
            return $this->dbError($e);
        }
    }
}