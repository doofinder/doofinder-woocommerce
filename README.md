# doofinder-wordpress
Integrate Doofinder in your WordPress site with (almost) no effort.

## 👨‍💻 Development & Maintainer Guide

This repository is optimized for local development using a **Makefile** and **Docker**.

**`.env`** sits at the repo root and powers the **Docker** stack and the WordPress installer (`docker/install-wordpress.sh`). It ships with sensible defaults — skim it, adjust if needed, then `make init`. Optional per-developer overrides go in **`.env.local`**, which loads on top of `.env`. See `.env.local.example` for the kinds of values worth overriding (host ports, `LOCAL_DOMAIN` for ngrok testing, etc.). `.env.local` is gitignored.

### Environment and shop access

| Variable | Role |
| -------- | ---- |
| `WEB_SERVICE_PORT` | Host port for the WordPress container (default `9010`). |
| `MYSQL_HOST_PORT` | Host port for the MySQL container (default `3310`). |
| `LOCAL_DOMAIN` | Site URL passed to `wp core install` on first run. |
| `WORDPRESS_IMAGE_TAG` | `wordpress:<tag>` image used by the stack. |
| `WORDPRESS_VERSION` | Optional. If set, forces a specific WP core version. |
| `MYSQL_*` | Database credentials and database name. |
| `ADMIN_USER` / `ADMIN_PASSWORD` / `ADMIN_EMAIL` | Back-office login created on first run. |
| `APACHE_UID` / `APACHE_GID` | UID/GID Apache runs as inside the container — must match the host owner of `./html`. |
| `WP_ENVIRONMENT_TYPE` | Defines `WP_ENVIRONMENT_TYPE` in `wp-config.php`. Committed default `local`; the plugin only honors per-dev host overrides when this is `local`. |
| `FS_METHOD` | Defines `FS_METHOD`. Committed default `direct` (lets you install plugins from the WP repo without FTP credentials). |
| `DF_PLUGINS_HOST` / `DF_API_HOST` | Optional. Point the plugin at your local dooplugins / doomanager (e.g. ngrok URLs). Leave empty to use production. Override in `.env.local`. |

**Default access (Docker dev stack):** After **`make init`**, with the stock `.env` (`WEB_SERVICE_PORT=9010`, admin `admin` / `admin123`):

| | URL |
| -- | -- |
| Storefront | `http://localhost:9010/` |
| Back office | `http://localhost:9010/wp-admin` |

`make init` prints the usable links once the install is done; if you change `WEB_SERVICE_PORT`, adjust accordingly.

**Use cases:**

- **First-time setup:** Run `make init` once to build images, install WordPress, install/activate WooCommerce, import sample products, and activate the Doofinder plugin.
- **Start / stop the stack:** `make start`, `make stop`.
- **Activate the plugin:** `make doofinder-install`.
- **Uninstall the plugin:** `make doofinder-uninstall`. ⚠️ Destructive — runs `register_uninstall_hook` and removes plugin tables/options.
- **Reinstall the plugin:** `make doofinder-reinstall`.
- **DB snapshot:** `make db-backup` (optionally `make db-backup prefix=_name`). Restore with `make db-restore file=backup.sql.gz`.
- **Clear cache:** `make cache-flush` (object cache + transients).
- **Shell in the web container:** `make dev-console`.
- **Tail container logs:** `make logs` (follows the `wordpress` service).
- **Start from scratch:** Run `make clean` to drop Docker volumes and `./html`; type `DELETE` when prompted, then run `make init` for a fresh WordPress.
- **Code-style check (containerized):** `make consistency`. Runs phpcs in a one-shot composer container — no host PHP needed.
- **Multi-PHP compatibility lint:** `make lint-php-versions`.
- **Debug with Xdebug:** Xdebug 3.3 is built into the image (port 9003). Configure your IDE to listen for incoming connections.

### Code style before pushing changes

Before pushing, run:

```
make consistency
```

This runs PHPCS (PHP Code Sniffer) against the WooCommerce/WordPress coding standards inside a one-shot Composer container — no host PHP/Composer is required.

If you want the auto-fix variant (`phpcbf`), open a shell in the container with `make dev-console` and run `./vendor/bin/phpcbf` directly, after a `composer install`.

## PHP compatibility

This plugin has been thoroughly tested and confirmed to be compatible with the following PHP versions:

✅ Supported PHP Versions:

- PHP 7.0
- PHP 7.1
- PHP 7.2
- PHP 7.3
- PHP 7.4
- PHP 8.0
- PHP 8.1
- PHP 8.2
- PHP 8.3
- PHP 8.4
