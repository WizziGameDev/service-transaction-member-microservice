#!/bin/bash

# Cek Docker daemon aktif
echo "🔍 Checking Docker daemon..."
until docker info >/dev/null 2>&1; do
  echo "⌛ Waiting for Docker daemon to start..."
  sleep 3
done

echo "✅ Docker daemon is running."

# Jalankan docker-compose
echo "🚀 Starting Docker Compose..."
docker-compose up -d --build

# Tunggu MySQL ready
echo "⏳ Waiting for MySQL to be healthy..."
until docker exec mysql-transaction-member mysqladmin ping -h localhost --silent; do
    echo "⌛ Waiting for MySQL to be ready..."
    sleep 5
done

# Tunggu Laravel ready (optional, kita skip dulu karena seed gak butuh fpm ready)
sleep 10

# Seed database (opsional)
echo "🌱 Running Laravel seed..."
docker exec service-transaction-member php artisan migrate:fresh --seed

# Jalankan Queue Worker
echo "🚀 Starting Laravel queue worker..."
docker exec -d service-transaction-member php artisan queue:work --verbose --tries=5

echo "🎉 All services up, migration seeded, and queue worker running!"
