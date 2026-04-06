# alma_inventory_docker

Alma Inventory API application that can be launched using Docker on Mac, Linux, or Windows.  
For background on the application, see the [Ex Libris Developer Blog post](https://developers.exlibrisgroup.com/blog/Shelf-Inventory-using-Alma-APIs).

---

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop) (v20.10+) or Docker Engine with the Compose plugin
- Git
- An Ex Libris Alma API key with **read-only** access to the **Bibs** and **Configuration** APIs

> **Note:** This application runs PHP 8.2 inside the container. No local PHP installation is required.

---

## Setup

1. **Clone the repository**

   ```bash
   git clone https://github.com/dlingley/alma_inventory_docker.git
   cd alma_inventory_docker
   ```

2. **Configure your Alma API key**

   Copy the example environment file and add your key:

   ```bash
   cp .env.example .env
   ```

   Open `.env` and replace `your_api_key_here` with your actual Alma Shelflist API key:

   ```
   ALMA_SHELFLIST_API_KEY=your_actual_key
   ```

   The key is picked up automatically by `key.php` at runtime.  
   Alternatively, you can edit `key.php` directly and hard-code the key — but using `.env` keeps secrets out of source control.

3. **Build and start the container**

   ```bash
   # Modern Docker CLI (v20.10+)
   docker compose up

   # Legacy standalone docker-compose
   docker-compose up
   ```

4. **Open the application**

   Navigate to [http://localhost:8080](http://localhost:8080) in your browser.  
   You should see your list of Alma libraries in the drop-down list.

5. **Customise `index.php`** to match your institution:
   - Update the `id="itemType"` `<select>` options to match the material types enabled in Alma.
   - Update the `id="policy"` `<select>` options to match the item policy types defined in Alma.

---

## Environment Variables

| Variable | Description |
|---|---|
| `ALMA_SHELFLIST_API_KEY` | Alma API key with read-only Bibs & Configuration access |

Values can be set in the `.env` file (recommended) or passed directly to Docker via the `environment:` key in `docker-compose.yml`.

---

## Troubleshooting

**Port 8080 is already in use**  
Change the host port in `docker-compose.yml`:
```yaml
ports:
  - 9090:80   # use any free port on the left
```
Then open `http://localhost:9090` instead.

**The drop-down list is empty / API errors**  
- Confirm your `ALMA_SHELFLIST_API_KEY` is correct and has read-only access to Bibs and Configuration.  
- Check the container logs: `docker compose logs app`

**Permission errors on the cache directory**  
The `cache/` directory must be writable by the `www-data` user. The Dockerfile sets this automatically. If you see permission errors after a bind-mount change, run:
```bash
docker compose exec app chown -R www-data:www-data /srv/app/cache
```

**Changes to PHP files are not reflected**  
The `docker-compose.yml` mounts the project directory into the container as a volume, so edits to PHP files take effect immediately without rebuilding. If you change the `Dockerfile` or add new dependencies, rebuild with:
```bash
docker compose up --build
```

---

## Development & Contributing

### Running the tests

The project uses [PHPUnit](https://phpunit.de/). Install dependencies and run the test suite:

```bash
composer install
./vendor/bin/phpunit
```

### Production deployment

The Docker setup is intended for local development and testing. For production, deploy the PHP source files to your institution's web server environment (Apache/Nginx + PHP 8.2+) and set the `ALMA_SHELFLIST_API_KEY` environment variable through your server's configuration.

---

## Docker configuration reference

The Docker setup is based on the [PHP official image](https://hub.docker.com/_/php) with Apache.
