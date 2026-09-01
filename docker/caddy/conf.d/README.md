# Host-local Caddy site blocks

Anything in this directory matching `*.caddy` is imported by the main
`Caddyfile` and served by the same Caddy container. Those files are
**gitignored on purpose**: they describe what else happens to run on a
particular host, which is deployment detail rather than part of this app.

Use it for other sites sharing this box. A file looks like any site block:

```
other-app.example.com {
	reverse_proxy some-container:3000
}
```

The upstream name must be resolvable from the caddy container, so that
container has to share the `moslie-network` defined in `docker-compose.yml`.

Two things worth knowing:

- An empty directory is fine. Caddy logs `No files matching import glob
  pattern` and loads normally, so a fresh clone needs nothing here.
- `caddy validate` passing does **not** prove a proxy works — Caddy accepts an
  unresolvable upstream at load time and only fails per request. Check with
  `curl -sSL -o /dev/null -w '%{http_code}\n' https://your-host`.

Apply changes with `docker compose up -d caddy`.
