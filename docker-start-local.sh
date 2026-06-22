#!/bin/bash

echo "🐳 Starting Zoher Dental Lab with Docker (Local/WSL)..."

# Check if .env exists
if [ ! -f .env ]; then
    echo "📝 Creating .env file from .env.docker..."
    cp .env.docker .env
fi

# Check if APP_KEY is set
if ! grep -q "APP_KEY=base64:" .env; then
    echo "🔑 Generating application key..."
    docker-compose -f docker-compose.local.yml run --rm app php artisan key:generate
fi

# Build and start containers
echo "🏗️  Building containers (this may take a few minutes)..."
docker-compose -f docker-compose.local.yml up -d --build

# Wait for database to be ready
echo "⏳ Waiting for database to be ready..."
sleep 10

# Run migrations
echo "📊 Running database migrations..."
docker-compose -f docker-compose.local.yml exec app php artisan migrate --force

# Ensure local admin user exists with known password
echo "👤 Ensuring local admin user..."
docker-compose -f docker-compose.local.yml exec app php -r "
    require '/opt/dental-lab/moslie-dental-lab/vendor/autoload.php';
    \\\$app = require_once '/opt/dental-lab/moslie-dental-lab/bootstrap/app.php';
    \\\$kernel = \\\$app->make(Illuminate\Contracts\Console\Kernel::class);
    \\\$kernel->bootstrap();
    \\\$user = App\Models\User::firstOrNew(['email' => 'zohermoslie0@gmail.com']);
    \\\$user->name = 'Zoher Moslie';
    \\\$user->password = password_hash('password', PASSWORD_BCRYPT);
    \\\$user->email_verified_at = now();
    \\\$user->save();
    echo '✅ Admin user ready: zohermoslie0@gmail.com / password' . PHP_EOL;
"

# Optimize
echo "⚡ Optimizing application..."
docker-compose -f docker-compose.local.yml exec app php artisan optimize

echo ""
echo "✅ Done! Application is running locally at:"
echo "🌐 http://localhost:8080 (direct to app)"
echo ""
echo "📝 To view logs:"
echo "   - App:    docker-compose -f docker-compose.local.yml logs -f app"
echo "🛑 To stop: docker-compose -f docker-compose.local.yml down"
echo ""
