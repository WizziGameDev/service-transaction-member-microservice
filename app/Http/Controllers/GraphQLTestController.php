<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GraphQLTestController extends Controller
{
    protected $memberGraphqlUrl = 'http://localhost:90/api/v1/members/graphql';
    protected $productGraphqlUrl = 'http://localhost:90/api/v1/products/graphql';

    public function test()
    {
        try {
            $query = <<<'GRAPHQL'
            query {
                products {
                    id
                    name
                    stock
                }
            }
            GRAPHQL;

            $response = Http::post($this->productGraphqlUrl, [
                'query' => $query,
            ]);

            if ($response->failed()) {
                return response()->json(['message' => 'Failed to connect to GraphQL', 'response' => $response->body()], 500);
            }

            return response()->json([
                'message' => 'GraphQL connected successfully',
                'data' => $response->json(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error', 'error' => $e->getMessage()], 500);
        }
    }
}
