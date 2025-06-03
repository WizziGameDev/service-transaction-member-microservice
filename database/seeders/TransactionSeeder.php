<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use App\Models\Transaction;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $members = [
            [
                'id' => 1, 'slug' => 'john-doe', 'name' => 'John Doe',
                'email' => 'john.doe@example.com', 'phone' => '081234567890'
            ],
            [
                'id' => 2, 'slug' => 'jane-doe', 'name' => 'Jane Doe',
                'email' => 'jane.doe@example.com', 'phone' => '089876543210'
            ],
            [
                'id' => 3, 'slug' => 'andi-pratama', 'name' => 'Andi Pratama',
                'email' => 'andi.pratama@example.com', 'phone' => '082345678901'
            ],
            [
                'id' => 4, 'slug' => 'sari-wulandari', 'name' => 'Sari Wulandari',
                'email' => 'sari.wulandari@example.com', 'phone' => '083456789012'
            ],
            [
                'id' => 5, 'slug' => 'budi-santoso', 'name' => 'Budi Santoso',
                'email' => 'budi.santoso@example.com', 'phone' => '081122334455'
            ],
        ];

        $products = [
            ['id' => 1, 'slug' => 'pupuk-organik', 'name' => 'Pupuk Organik', 'description' => 'Pupuk organik ramah lingkungan', 'price' => 35000, 'stock' => 80, 'unit' => 'kg'],
            ['id' => 2, 'slug' => 'ayam-kampung', 'name' => 'Ayam Kampung', 'description' => 'Ayam kampung segar', 'price' => 80000, 'stock' => 30, 'unit' => 'ekor'],
            ['id' => 3, 'slug' => 'telur-ayam', 'name' => 'Telur Ayam', 'description' => 'Telur ayam segar', 'price' => 23000, 'stock' => 100, 'unit' => 'kg'],
            ['id' => 4, 'slug' => 'beras-organik', 'name' => 'Beras Organik', 'description' => 'Beras organik bebas pestisida', 'price' => 18000, 'stock' => 50, 'unit' => 'kg'],
            ['id' => 5, 'slug' => 'sayur-bayam', 'name' => 'Sayur Bayam', 'description' => 'Sayur bayam segar', 'price' => 5000, 'stock' => 40, 'unit' => 'ikat'],
        ];

        $statuses = ['pending', 'paid', 'shipped', 'completed'];

        for ($i = 1; $i <= 10; $i++) {
            $member = $members[array_rand($members)];
            $status = $statuses[array_rand($statuses)];
            $transaction = Transaction::create([
                'transaction_code' => 'TRX-' . Str::upper(Str::random(8)),
                'member_id' => $member['id'],
                'member_name' => $member['name'],
                'total_price' => 0,
                'transaction_date' => Carbon::now()->subDays(rand(0, 30))->format('Y-m-d'),
                'status' => $status,
            ]);

            $total = 0;
            $itemCount = rand(2, 4);

            for ($j = 0; $j < $itemCount; $j++) {
                $product = $products[array_rand($products)];
                $quantity = rand(1, 5);
                $subtotal = $product['price'] * $quantity;

                $transaction->items()->create([
                    'product_id' => $product['id'],
                    'product_name' => $product['name'],
                    'price' => $product['price'],
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                ]);

                $total += $subtotal;
            }

            $transaction->update(['total_price' => $total]);
        }
    }
}
