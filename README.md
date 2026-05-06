# Doofinder for WooCommerce

![Release](https://img.shields.io/github/v/release/doofinder/doofinder-woocommerce?style=flat-square)
![WordPress](https://img.shields.io/badge/WordPress-5.6%2B-21759b?style=flat-square)
![WooCommerce](https://img.shields.io/badge/WooCommerce-supported-96588a?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-7.0%20--%208.4-777bb4?style=flat-square)
![License](https://img.shields.io/github/license/doofinder/doofinder-woocommerce?style=flat-square)

**Transform your WooCommerce search into a conversion machine.** Join thousands of merchants using AI-powered search to increase sales and improve customer experience.

[🚀 Get Started for Free](https://www.doofinder.com/en/solutions/woocommerce) | [🖥️ Live Demo](https://woocommerce.doofinder.com/) | [📖 Full Documentation](https://support.doofinder.com/plugins/woocommerce/installation-guide/installation-steps-woocommerce.html)

---

## Why Doofinder?

Doofinder turns your basic search bar into an advanced discovery engine. Using AI-powered searchandising and recommendations, we drive measurable gains in conversion and product discovery.

### Key Features

* **AI Smart Search** — Understands intent and handles typos or synonyms effortlessly.
* **Searchandising** — Boost, hide, or pin products to run targeted campaigns.
* **Personalized Recommendations** — Intelligent cross-selling based on real customer behavior.
* **Visual & Voice Search** — Let your shoppers find products using images or voice.
* **Real-Time Analytics** — Insights into user behavior and geolocation data.
* **Multi-language & Multi-currency** — Support for 30+ languages and currencies, with WPML integration.
* **Auto-Indexing** — Your catalog stays in sync automatically as you scale.

---

## 🛠 Installation & Quick Start

**From the WordPress Plugin Directory**
In your WordPress admin, go to **Plugins → Add New**, search for *Doofinder for WooCommerce*, click **Install Now**, then **Activate**.

**From GitHub (latest release)**
Download the [latest release zip](https://github.com/doofinder/doofinder-woocommerce/releases) and install via **Plugins → Add New → Upload Plugin**, or unpack into `wp-content/plugins/`.

**Then**
Complete setup using our [step-by-step installation guide](https://support.doofinder.com/plugins/woocommerce/installation-guide/installation-steps-woocommerce.html).

**Requirements**

| | Supported versions |
| -- | -- |
| WordPress | 5.6+ |
| WooCommerce | All currently supported releases |
| PHP | 7.0 – 8.4 (see Compatibility Matrix below) |

---

## 👨‍💻 Development & Maintainer Guide

This repository is optimized for local development using a **Makefile** and **Docker**.

**`.env`** sits at the repo root and powers both your **Docker** stack and the **generated plugin source files** (what `doofinder-configure` produces from `templates/`). It ships with sensible defaults — set your `BASE_URL`, bump `PLUGIN_VERSION` for releases, then `make init`. Optional per-developer overrides go in **`.env.local`** (gitignored), which loads on top of `.env`.

> [!NOTE]
> `make doofinder-configure` regenerates `doofinder-for-woocommerce/doofinder-for-woocommerce.php`, `readme.txt`, and `includes/class-constants.php` from `templates/`. CI fails on drift — always run it after editing templates or `.env`, and commit the result.

### Environment and shop access

The root **`.env`** lists all variables with comments. For the **dev stack**, these are the ones you usually touch first:

| Variable | Role |
| -------- | ---- |
| `BASE_URL` | Shop URL as seen from the host (e.g. `http://localhost:9010` or your ngrok URL). |
| `WEB_SERVICE_PORT` | Host port for the WordPress container (default `9010`). |
| `MYSQL_HOST_PORT` | Host port for the MySQL container (default `3310`). |
| `WORDPRESS_IMAGE_TAG` / `WORDPRESS_VERSION` | Pin a specific WP image / core version. |
| `MYSQL_*` | Database credentials and database name. |
| `ADMIN_USER` / `ADMIN_PASSWORD` / `ADMIN_EMAIL` | Back-office login created on first run. |
| `APACHE_UID` / `APACHE_GID` | UID/GID Apache runs as inside the container (must own `./html`). |
| `WP_ENVIRONMENT_TYPE` | `wp-config.php` constant. Committed default `local`. |
| `FS_METHOD` | `wp-config.php` constant. Committed default `direct` (install plugins from the WP repo without FTP creds). |
| `PLUGIN_VERSION` | Plugin version baked into the main plugin file, `readme.txt`, and `class-constants.php` by `make doofinder-configure`. |
| `DOOFINDER_PLUGINS_URL_FORMAT` / `DOOFINDER_API_URL_FORMAT` | URL format strings (`%s` is replaced at runtime by the region prefix). Override in `.env.local` to point the plugin at your own ngrok stack. |

**Default access (Docker dev stack):** After **`make init`**, with the stock `.env` (`WEB_SERVICE_PORT=9010`, admin `admin` / `admin123`):

| | URL |
| -- | -- |
| Storefront | `http://localhost:9010/` |
| Back office | `http://localhost:9010/wp-admin` |

`make init` prints the usable links once the install is done.

**Use cases:**

- **First-time setup:** `make init` builds images, installs WordPress + WooCommerce, imports sample products, activates the Doofinder plugin, and runs `make doofinder-configure` first to make sure generated source files are fresh.
- **Bump the plugin version for a release:** edit `PLUGIN_VERSION` in `.env`, then `make doofinder-configure`, then commit.
- **Point the plugin at your local Doofinder backend:** put `DOOFINDER_PLUGINS_URL_FORMAT` / `DOOFINDER_API_URL_FORMAT` in `.env.local`, run `make doofinder-configure`, refresh the browser.
- **Start / stop the stack:** `make start`, `make stop`.
- **Activate / uninstall / reinstall the plugin:** `make doofinder-install`, `make doofinder-uninstall` (⚠️ destructive — runs `register_uninstall_hook`), `make doofinder-reinstall`.
- **DB snapshot:** `make db-backup` (optionally `make db-backup prefix=_name`); restore with `make db-restore file=backup.sql.gz`.
- **Clear cache:** `make cache-flush` (object cache + transients).
- **Shell in the web container:** `make dev-console`.
- **Tail container logs:** `make logs` (follows the `wordpress` service).
- **Start from scratch:** `make clean` (type `DELETE` when prompted) then `make init`.
- **Code-style check (containerized):** `make consistency` — runs PHPCS in a one-shot Composer container; no host PHP needed.
- **Multi-PHP compatibility lint:** `make lint-php-versions`.
- **Debug with Xdebug:** Xdebug 3.3 is built into the image (port 9003). Configure your IDE to listen for incoming connections.

---

## Compatibility Matrix

| WordPress | WooCommerce | PHP |
| --------- | ----------- | --- |
| 5.6+ | All currently supported releases | 7.0, 7.1, 7.2, 7.3, 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 |

---

## Support & Contributing

* **Need Help?** Visit our [Support Portal](https://support.doofinder.com/).
* **Found a Bug?** Please [contact Doofinder Support](https://support.doofinder.com/pages/contact-us) or file a [security report via Patchstack](https://patchstack.com/database/vdp/doofinder-for-woocommerce).
* **Want to contribute?** PRs are welcome! Before pushing, make sure PHP Code Sniffer passes — run `make consistency` (or `composer install && vendor/bin/phpcs` directly).

**If you find this plugin useful, please give us a ⭐ to support the project!**

## Try Doofinder / Learn more

Ready to improve your store search? [Get started with Doofinder for WooCommerce](https://www.doofinder.com/en/solutions/woocommerce).
