# Caddy Setup Guide

This Docker setup includes Caddy as a reverse proxy with automatic HTTPS.

## 🚀 Quick Start (Local Development)

```bash
# Start all services (Caddy will be available on port 80)
./docker-start.sh
```

Visit: **http://localhost**

## 🌐 Production Setup with Domain

### 1. Configure Your Domain

Update `docker/caddy/Caddyfile`:

```caddy
{
    email admin@yourdomain.com
}

yourdomain.com {
    reverse_proxy app:80
    encode gzip
}
```

Or copy the production template:
```bash
cp docker/caddy/Caddyfile.production docker/caddy/Caddyfile
# Then edit and replace 'yourdomain.com' with your actual domain
```

### 2. Update Environment

Edit `.env`:
```env
APP_URL=https://yourdomain.com
```

### 3. DNS Setup

Point your domain to your server's IP:
```
A Record: yourdomain.com → YOUR_SERVER_IP
```

### 4. Open Ports

Ensure these ports are open on your server:
- `80` (HTTP - for Let's Encrypt validation)
- `443` (HTTPS)
- `443/udp` (HTTP/3 - optional)

### 5. Start Services

```bash
docker-compose up -d
```

Caddy will automatically:
- Obtain SSL certificate from Let's Encrypt
- Set up HTTPS
- Handle certificate renewal

## 📝 Caddy Features

### Automatic HTTPS
Caddy automatically obtains and renews SSL certificates from Let's Encrypt.

### HTTP/2 & HTTP/3
Enabled by default for better performance.

### Security Headers
Pre-configured with security best practices.

### Gzip Compression
Automatic compression for faster page loads.

## 🔧 Configuration Options

### Custom Port Mapping

Edit `docker-compose.yml` or set environment variables:

```bash
# Use custom ports
HTTP_PORT=8080 HTTPS_PORT=8443 docker-compose up -d
```

### Multiple Domains

Add to `Caddyfile`:

```caddy
domain1.com {
    reverse_proxy app:80
}

domain2.com {
    reverse_proxy app:80
}
```

### Basic Authentication

Add to your domain block in `Caddyfile`:

```caddy
yourdomain.com {
    basicauth /admin/* {
        admin $2a$14$hashed_password
    }
    reverse_proxy app:80
}
```

Generate password:
```bash
docker run --rm caddy:2-alpine caddy hash-password --plaintext 'yourpassword'
```

### Rate Limiting

Add to `Caddyfile`:

```caddy
yourdomain.com {
    rate_limit {
        zone dynamic {
            key {remote_host}
            events 100
            window 1m
        }
    }
    reverse_proxy app:80
}
```

## 🔍 Viewing Logs

```bash
# Caddy logs
docker-compose logs -f caddy

# Application logs
docker-compose logs -f app
```

## 🔄 Reload Configuration

After changing `Caddyfile`:

```bash
docker-compose exec caddy caddy reload --config /etc/caddy/Caddyfile
```

Or restart the service:

```bash
docker-compose restart caddy
```

## 🛡️ Security Best Practices

1. **Keep Caddy Updated**
   ```bash
   docker-compose pull caddy
   docker-compose up -d caddy
   ```

2. **Use Strong Passwords**
   - Change default database passwords in `.env`
   - Use long, random APP_KEY

3. **Enable Firewall**
   ```bash
   # UFW example
   sudo ufw allow 80/tcp
   sudo ufw allow 443/tcp
   sudo ufw allow 443/udp
   sudo ufw enable
   ```

4. **Regular Backups**
   ```bash
   # Backup database
   docker-compose exec db mysqldump -u zoher -p zoher > backup.sql

   # Backup Caddy data (certificates)
   docker run --rm -v zoher-project_caddy-data:/data -v $(pwd):/backup alpine tar czf /backup/caddy-data-backup.tar.gz /data
   ```

## 🐛 Troubleshooting

### Certificate Issues

Check certificate status:
```bash
docker-compose exec caddy caddy list-certificates
```

View detailed logs:
```bash
docker-compose logs caddy | grep -i "certificate\|acme\|error"
```

### Port Already in Use

If port 80/443 is already in use:
```bash
# Check what's using the port
sudo lsof -i :80
sudo lsof -i :443

# Stop conflicting service or use different ports
HTTP_PORT=8080 HTTPS_PORT=8443 docker-compose up -d
```

### DNS Not Resolving

Test DNS:
```bash
nslookup yourdomain.com
dig yourdomain.com
```

## 📚 Additional Resources

- [Caddy Documentation](https://caddyserver.com/docs/)
- [Caddyfile Syntax](https://caddyserver.com/docs/caddyfile)
- [Caddy Docker Image](https://hub.docker.com/_/caddy)
- [Let's Encrypt Rate Limits](https://letsencrypt.org/docs/rate-limits/)

## 🔗 Using External Caddy

If you already have Caddy running outside Docker, you can skip the caddy service:

```bash
# Start only app, db, and redis
docker-compose up -d app db redis
```

Then configure your external Caddy:
```caddy
yourdomain.com {
    reverse_proxy localhost:8080
}
```

And expose the app port:
```yaml
# In docker-compose.yml, change app service:
ports:
  - "8080:80"
```
