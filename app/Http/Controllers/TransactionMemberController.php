<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\TransactionMember;
use App\Jobs\SendNotificationJob;

class TransactionMemberController extends Controller
{
    protected $memberGraphqlUrl;
    protected $productGraphqlUrl;

    public function __construct()
    {
        $this->memberGraphqlUrl = env('MEMBER_GRAPHQL_URL', 'http://traefik:90/api/v1/members/graphql');
        $this->productGraphqlUrl = env('PRODUCT_GRAPHQL_URL', 'http://traefik:90/api/v1/products/graphql');
    }
    // GET /transactions-member (list all)
    public function index()
    {
        // Get all transaction
        $transactions = TransactionMember::with('items')->get();

        return response()->json([
            'status' => 200,
            'message' => 'Transactions retrieved successfully',
            'data' => $transactions,
            'errors' => null
        ], 200);
    }

    // GET /transactions-member/{id} (get single)
    public function show($id)
    {
        // Get transaction by ID
        $transaction = TransactionMember::with('items')->find($id);

        if (!$transaction) {
            return response()->json([
                'status' => 404,
                'message' => 'Transaction not found',
                'data' => null,
                'errors' => null
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Transaction retrieved successfully',
            'data' => $transaction,
            'errors' => null
        ], 200);
    }

    // POST /transactions-member (create)
    public function store(Request $request)
    {
        // Check validation
        $validator = validator($request->all(), [
            'member_id' => 'required|integer',
            'transaction_date' => 'required|date',
            'status' => 'required|in:pending,completed,cancelled',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        // Catch validation
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation errors',
                'data' => null,
                'errors' => collect($validator->errors())->map(function ($v, $k) {
                    return ['field' => $k, 'message' => $v[0]];
                })->values()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Fetch member
            $memberData = $this->fetchMember($request->member_id);
            if (!$memberData) {
                return $this->notFoundResponse('member_id', 'Member not found');
            }

            $totalPrice = 0;
            $transactionItemsData = [];

            foreach ($request->items as $item) {
                // Fetch product
                $productData = $this->fetchProduct($item['product_id']);
                if (!$productData) {
                    DB::rollBack();
                    return $this->notFoundResponse('product_id', "Product ID {$item['product_id']} not found");
                }

                if ($productData['stock'] < $item['quantity']) {
                    DB::rollBack();
                    return $this->errorResponse('stock', "Insufficient stock for product {$productData['name']}", 400);
                }

                // Price for member
                $price = $productData['priceForMember'];
                if ($price === null) {
                    DB::rollBack();
                    return $this->errorResponse('priceForMember', "Price for member not set for product {$productData['name']}", 400);
                }

                // Update stock
                $this->updateProductStock($productData['id'], $productData['stock'] - $item['quantity']);

                $subtotal = $price * $item['quantity'];
                $totalPrice += $subtotal;

                $transactionItemsData[] = [
                    'product_id' => $productData['id'],
                    'product_name' => $productData['name'],
                    'price' => $price,
                    'quantity' => $item['quantity'],
                    'subtotal' => $subtotal,
                ];
            }

            // Create transaction
            $transaction = TransactionMember::create([
                'transaction_code' => 'TRX-MEMBER-' . Str::upper(Str::random(8)),
                'member_id' => $memberData['id'],
                'member_name' => $memberData['name'],
                'total_price' => $totalPrice,
                'transaction_date' => $request->transaction_date,
                'status' => $request->status,
            ]);

            foreach ($transactionItemsData as $itemData) {
                $transaction->items()->create($itemData);
            }

            DB::commit();

            // Send notification
            ProcessNotification::dispatch([
                'type' => 'transaction_created',
                'transaction_id' => $transaction->id,
                'transaction_code' => $transaction->transaction_code,
                'member_id' => $transaction->member_id,
                'member_name' => $transaction->member_name,
                'phone' => $memberData['phoneNumber'] ?? null,
                'status' => $transaction->status,
                'products' => collect($transactionItemsData)->map(function ($item) {
                    return [
                        'product_id' => $item['product_id'],
                        'product_name' => $item['product_name'],
                        'quantity' => $item['quantity'],
                        'subtotal' => $item['subtotal'],
                    ];
                })->toArray(),
                'message' => 'Transaction created successfully',
            ]);

            return response()->json([
                'status' => 201,
                'message' => 'Transaction created successfully',
                'data' => $transaction->load('items'),
                'errors' => null
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('sql', 'Failed to save transaction: ' . $e->getMessage(), 500);
        }
    }

    // PUT /transactions-member/{id} (update)
    public function update(Request $request, $id)
    {
        // Check transaction
        $transaction = TransactionMember::with('items')->find($id);
        if (!$transaction) {
            return $this->notFoundResponse('transaction_id', 'Transaction not found');
        }

        // Check validation
        $validator = validator($request->all(), [
            'member_id' => 'sometimes|integer',
            'transaction_date' => 'sometimes|date',
            'status' => 'sometimes|in:pending,completed,cancelled',
            'items' => 'sometimes|array|min:1',
            'items.*.product_id' => 'required_with:items|integer',
            'items.*.quantity' => 'required_with:items|integer|min:1',
        ]);

        // Catch validation
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation errors',
                'data' => null,
                'errors' => collect($validator->errors())->map(function ($v, $k) {
                    return ['field' => $k, 'message' => $v[0]];
                })->values()
            ], 422);
        }

        DB::beginTransaction();
        try {
            if ($request->has('member_id')) {
                // Check member
                $memberData = $this->fetchMember($request->member_id);
                if (!$memberData) {
                    DB::rollBack();
                    return $this->notFoundResponse('member_id', 'Member not found');
                }
                $transaction->member_id = $memberData['id'];
                $transaction->member_name = $memberData['name'];
            }

            if ($request->has('transaction_date')) {
                $transaction->transaction_date = $request->transaction_date;
            }

            $totalPrice = 0;
            if ($request->has('items')) {
                // Check stock
                foreach ($transaction->items as $oldItem) {
                    $productData = $this->fetchProduct($oldItem->product_id);
                    if ($productData) {
                        $this->updateProductStock($oldItem->product_id, $productData['stock'] + $oldItem->quantity);
                    }
                }

                $transaction->items()->delete();

                foreach ($request->items as $item) {
                    // Check product
                    $productData = $this->fetchProduct($item['product_id']);
                    if (!$productData) {
                        DB::rollBack();
                        return $this->notFoundResponse('product_id', "Product ID {$item['product_id']} not found");
                    }

                    if ($productData['stock'] < $item['quantity']) {
                        DB::rollBack();
                        return $this->errorResponse('stock', "Insufficient stock for product {$productData['name']}", 400);
                    }

                    // Price for member
                    $price = $productData['priceForMember'];
                    if ($price === null) {
                        DB::rollBack();
                        return $this->errorResponse('priceForMember', "Price for member not set for product {$productData['name']}", 400);
                    }

                    // Update stock
                    $this->updateProductStock($productData['id'], $productData['stock'] - $item['quantity']);

                    $subtotal = $price * $item['quantity'];
                    $totalPrice += $subtotal;

                    // Update item/product
                    $transaction->items()->create([
                        'product_id' => $productData['id'],
                        'product_name' => $productData['name'],
                        'price' => $price,
                        'quantity' => $item['quantity'],
                        'subtotal' => $subtotal,
                    ]);
                }

                $transaction->total_price = $totalPrice;
            }

            $transaction->save();
            $transaction->load('items');

            $memberData = $this->fetchMember($transaction->member_id);

            DB::commit();

            // Send notification
            ProcessNotification::dispatch([
                'type' => 'transaction_updated',
                'transaction_id' => $transaction->id,
                'transaction_code' => $transaction->transaction_code,
                'member_id' => $transaction->member_id,
                'member_name' => $transaction->member_name,
                'phone' => $memberData['phoneNumber'] ?? null,
                'status' => $transaction->status,
                'products' => $transaction->items->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'subtotal' => $item->subtotal,
                    ];
                })->toArray(),
                'message' => 'Transaction updated successfully',
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Transaction updated successfully',
                'data' => $transaction,
                'errors' => null
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('sql', 'Failed to update transaction: ' . $e->getMessage(), 500);
        }
    }

    // PATCH /transactions-member/{id}/{status} (patch)
    public function updateStatus($id, $status)
    {
        // Validation Status
        if (!in_array($status, ['pending', 'completed', 'cancelled'])) {
            return $this->errorResponse('status', 'Status must be one of: pending, completed, cancelled', 422);
        }

        DB::beginTransaction();
        try {
            // Check Transaction
            $transaction = TransactionMember::with('items')->find($id);
            if (!$transaction) {
                DB::rollBack();
                return $this->notFoundResponse('transaction_id', 'Transaction not found');
            }

            $memberData = $this->fetchMember($transaction->member_id);

            $transaction->status = $status;
            $transaction->save();

            // Send notification
            ProcessNotification::dispatch([
                'type' => 'transaction_status_updated',
                'transaction_id' => $transaction->id,
                'transaction_code' => $transaction->transaction_code,
                'member_id' => $transaction->member_id,
                'member_name' => $transaction->member_name,
                'phone' => $memberData['phoneNumber'] ?? null,
                'status' => $transaction->status,
                'products' => $transaction->items->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'subtotal' => $item->subtotal,
                    ];
                })->toArray(),
                'message' => 'Transaction status updated successfully',
            ]);

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Transaction status updated successfully',
                'data' => $transaction,
                'errors' => null
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('sql', 'Failed to update status: ' . $e->getMessage(), 500);
        }
    }

    // DELETE /transactions-member/{id} (delete)
    public function destroy($id)
    {
        // Check Transaction
        $transaction = TransactionMember::with('items')->find($id);
        if (!$transaction) {
            return $this->notFoundResponse('transaction_id', 'Transaction not found');
        }

        // Take Item before delete
        $items = $transaction->items->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'subtotal' => $item->subtotal,
            ];
        });

        DB::beginTransaction();
        try {
            foreach ($transaction->items as $item) {
                $productData = $this->fetchProduct($item->product_id);
                if ($productData) {
                    $this->updateProductStock($item->product_id, $productData['stock'] + $item->quantity);
                }
            }

            $transaction->items()->delete();
            $transaction->delete();

            $memberData = $this->fetchMember($transaction->member_id);

            DB::commit();

            // Send notification
            ProcessNotification::dispatch([
                'type' => 'transaction_deleted',
                'transaction_id' => $transaction->id,
                'member_id' => $transaction->member_id,
                'transaction_code' => $transaction->transaction_code,
                'member_name' => $transaction->member_name,
                'phone' => $memberData['phoneNumber'] ?? null,
                'products' => $items->toArray(),
                'message' => 'Transaction deleted successfully',
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Transaction deleted successfully',
                'data' => null,
                'errors' => null
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('sql', 'Failed to delete transaction: ' . $e->getMessage(), 500);
        }
    }

    // Fetch Member
    private function fetchMember($id)
    {
        $query = <<<'GRAPHQL'
        query getMemberById($id: Int!) {
            memberById(id: $id) {
                id
                name
                phoneNumber
            }
        }
        GRAPHQL;

        $response = Http::post($this->memberGraphqlUrl, ['query' => $query, 'variables' => ['id' => $id]]);
        return $response->successful() ? $response['data']['memberById'] ?? null : null;
    }

    // Fetch Product
    private function fetchProduct($id)
    {
        $query = <<<'GRAPHQL'
        query getProductById($id: Int!) {
            productById(id: $id) {
                id
                name
                priceForMember
                stock
            }
        }
        GRAPHQL;

        $response = Http::post($this->productGraphqlUrl, ['query' => $query, 'variables' => ['id' => $id]]);
        return $response->successful() ? $response['data']['productById'] ?? null : null;
    }

    // Update Product Stock
    private function updateProductStock($id, $stock)
    {
        $mutation = <<<'GRAPHQL'
        mutation updateStock($id: Int!, $stock: Int!) {
            updateStockProduct(id: $id, stock: $stock)
        }
        GRAPHQL;

        $response = Http::post($this->productGraphqlUrl, ['query' => $mutation, 'variables' => ['id' => $id, 'stock' => $stock]]);
        if ($response->failed()) {
            throw new \Exception("Failed to update stock for product ID $id");
        }
    }

    // Helper: Error Responses
    private function errorResponse($field, $message, $status)
    {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => null,
            'errors' => [['field' => $field, 'message' => $message]]
        ], $status);
    }

    private function notFoundResponse($field, $message)
    {
        return $this->errorResponse($field, $message, 404);
    }
}
