# mcptrax deploy — 167.172.167.107 (paylaşımlı droplet, Frankfurt)

Topoloji (Proposial ile aynı desen):

```
Cloudflare (Full SSL) → host nginx :443 (mcptrax.com, self-signed origin cert)
  ├── /api/paddle/webhook → 127.0.0.1:8082 (doğrudan Laravel)
  └── /               → 127.0.0.1:3200 (mcptrax-nuxt container, node:22-alpine, --network host)
                          └── /api/mt/* proxy → 127.0.0.1:8082 (internal nginx vhost)
                                                  └── fastcgi → 127.0.0.1:9002 (mcptrax-php container, --network host)
```

- Kod: `/var/www/mcptrax/mcptrax.com` (git, deploy key: `~/.ssh/mcptrax`)
- DB: host MySQL 5.7, şema `mcptrax` (DB_COLLATION=utf8mb4_unicode_ci ŞART — 5.7'de 0900 collation yok)
- Kuyruk: systemd `mcptrax-probes.service` + `mcptrax-default.service` (docker exec)
- Zamanlayıcı: root crontab `* * * * * docker exec ... php artisan schedule:run`
- Nuxt build lokalde yapılır, `.output/` rsync ile taşınır (sunucuda npm yok)

## Kullanılan portlar (bu droplet'te ÇAKIŞMA!)
- 9000 blt-php83, 9001 proposial-php, **9002 mcptrax-php**
- 3100 proposial-nuxt, **3200 mcptrax-nuxt**
- 8081 proposial-api.internal, **8082 mcptrax-api.internal**

## Güncelleme deploy'u
```bash
# lokal
cd ~/Documents/mcpmonitor && git push
cd frontend && npm run build && rsync -az --delete -e "ssh -i ~/.ssh/filezilla_key" .output/ root@167.172.167.107:/var/www/mcptrax/mcptrax.com/frontend/.output/
# sunucu
ssh -i ~/.ssh/filezilla_key root@167.172.167.107 '
  cd /var/www/mcptrax/mcptrax.com && git pull -q &&
  docker exec -u www-data -w /var/www/mcptrax/mcptrax.com/backend mcptrax-php sh -c \
    "php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache" &&
  docker restart mcptrax-nuxt'
```
