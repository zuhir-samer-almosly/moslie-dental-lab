#!/bin/bash

echo "🐳 Starting Zoher Dental Lab with Docker..."

# Check if .env exists
if [ ! -f .env ]; then
    echo "📝 Creating .env file from .env.docker..."
    cp .env.docker .env
fi

# Check if APP_KEY is set
if ! grep -q "APP_KEY=base64:" .env; then
    echo "🔑 Generating application key..."
    docker-compose run --rm app php artisan key:generate
fi

# Build and start containers
echo "🏗️  Building containers (this may take a few minutes)..."
docker-compose up -d --build

# Wait for database to be ready
echo "⏳ Waiting for database to be ready..."
sleep 10

# Run migrations
echo "📊 Running database migrations..."
docker-compose exec app php artisan migrate --force

# Optimize
echo "⚡ Optimizing application..."
docker-compose exec app php artisan optimize

echo ""
echo "✅ Done! Application is running at:"
echo "🌐 http://localhost (via Caddy)"
echo "   or http://localhost:8080 (direct to app, if exposed)"
echo ""
echo "📝 To view logs:"
echo "   - Caddy:  docker-compose logs -f caddy"
echo "   - App:    docker-compose logs -f app"
echo "🛑 To stop: docker-compose down"
echo ""
echo "📖 For production setup with your domain, see: docker/CADDY_SETUP.md"
