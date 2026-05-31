# Deployment Guide for dental-lab.joory.chat

## Pre-Deployment Checklist

### 1. DNS Configuration
Ensure your DNS is pointing to your server:
```bash
# Test DNS resolution
nslookup dental-lab.joory.chat
dig dental-lab.joory.chat
```

Should point to your server's IP address.

### 2. Server Requirements
- Docker installed
- Docker Compose installed
- Ports 80, 443, and 443/udp open
- At least 1GB RAM

### 3. Firewall Configuration
```bash
# If using UFW
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 443/udp
sudo ufw enable
```

## Deployment Steps

### 1. Clone/Upload Project to Server
```bash
# On your server
cd /var/www
git clone <your-repo> zoher-project
cd zoher-project
```

### 2. Set Up Environment
```bash
# Copy environment file
cp .env.docker .env

# Generate application key
docker-compose run --rm app php artisan key:generate

# Set strong database password (edit .env)
nano .env
```

Update these values in `.env`:
```env
APP_ENV=production
APP_DEBUG=false
DB_PASSWORD=<strong-password>
DB_ROOT_PASSWORD=<strong-root-password>
```

### 3. Build and Start Services
```bash
# Build containers
docker-compose build

# Start services
docker-compose up -d

# Check services are running
docker-compose ps
```

### 4. Wait for SSL Certificate
Caddy will automatically obtain an SSL certificate from Let's Encrypt. This may take 1-2 minutes.

Monitor the process:
```bash
docker-compose logs -f caddy
```

Look for: `certificate obtained successfully`

### 5. Run Database Migrations
```bash
docker-compose exec app php artisan migrate --force
```

### 6. Optimize Application
```bash
docker-compose exec app php artisan optimize
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache
```

### 7. Set Permissions
```bash
docker-compose exec app chown -R www-data:www-data /var/www/html/storage
docker-compose exec app chmod -R 755 /var/www/html/storage
```

## Verify Deployment

1. Visit: **https://dental-lab.joory.chat**
2. Check SSL certificate (should show valid Let's Encrypt cert)
3. Register a test user account
4. Test all features:
   - Create dentist
   - Create order
   - Create payment
   - View invoice
   - Print invoice

## Post-Deployment

### Set Up Automatic Backups

Create backup script:
```bash
nano /usr/local/bin/backup-dental-lab.sh
```

```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/dental-lab"
mkdir -p $BACKUP_DIR

# Backup database
docker-compose -f /var/www/zoher-project/docker-compose.yml exec -T db \
    mysqldump -u zoher -p$DB_PASSWORD zoher > $BACKUP_DIR/db_$DATE.sql

# Backup storage folder
tar -czf $BACKUP_DIR/storage_$DATE.tar.gz -C /var/www/zoher-project storage

# Keep only last 7 days
find $BACKUP_DIR -name "*.sql" -mtime +7 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete
```

```bash
chmod +x /usr/local/bin/backup-dental-lab.sh
```

Add to crontab (daily at 2 AM):
```bash
crontab -e
```

Add:
```
0 2 * * * /usr/local/bin/backup-dental-lab.sh
```

### Monitoring

View logs:
```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f caddy
docker-compose logs -f app
docker-compose logs -f db
```

Check disk usage:
```bash
docker system df
df -h
```

### Updating Application

```bash
cd /var/www/zoher-project

# Pull latest code
git pull

# Rebuild containers
docker-compose build

# Restart services
docker-compose up -d

# Run migrations
docker-compose exec app php artisan migrate --force

# Clear and cache
docker-compose exec app php artisan optimize
```

## Troubleshooting

### SSL Certificate Issues

Check Caddy logs:
```bash
docker-compose logs caddy | grep -i "certificate\|acme\|error"
```

Common issues:
- DNS not pointing to server
- Ports 80/443 blocked
- Rate limit hit (wait 1 hour)

Test manually:
```bash
docker-compose exec caddy caddy list-certificates
```

### Application Not Loading

Check app logs:
```bash
docker-compose logs app | tail -50
```

Check if services are running:
```bash
docker-compose ps
```

Restart services:
```bash
docker-compose restart app
```

### Database Connection Issues

Test database connection:
```bash
docker-compose exec app php artisan migrate:status
```

Access database:
```bash
docker-compose exec db mysql -u zoher -p
```

### Permission Issues

Fix permissions:
```bash
docker-compose exec app chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
docker-compose exec app chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache
```

## Maintenance Commands

### Stop Services
```bash
docker-compose down
```

### Restart Services
```bash
docker-compose restart
```

### View Resource Usage
```bash
docker stats
```

### Clean Up
```bash
# Remove unused images
docker image prune -a

# Remove unused volumes (⚠️ careful!)
docker volume prune
```

## Security Recommendations

1. **Regular Updates**
   ```bash
   docker-compose pull
   docker-compose up -d
   ```

2. **Strong Passwords**
   - Change all default passwords
   - Use password manager

3. **Regular Backups**
   - Test backup restoration regularly
   - Store backups off-site

4. **Monitoring**
   - Set up uptime monitoring (UptimeRobot, etc.)
   - Monitor disk space
   - Check logs regularly

5. **Keep System Updated**
   ```bash
   sudo apt update && sudo apt upgrade
   ```

## Support

For issues or questions:
- Check logs: `docker-compose logs -f`
- Review documentation: `docker/README.md` and `docker/CADDY_SETUP.md`
- Check Laravel logs: `storage/logs/laravel.log`

## Success! 🎉

Your dental lab management system is now live at:
**https://dental-lab.joory.chat**

The system includes:
- ✅ Automatic HTTPS (Let's Encrypt)
- ✅ Arabic UI with RTL support
- ✅ Dentist management
- ✅ Order management with items
- ✅ Payment tracking
- ✅ Invoice generation and printing
- ✅ Dashboard with statistics
