# Alma Inventory Scanner (`alma_inventory_docker`)

Containerized PHP 8.2 web application for Alma inventory verification, call number shelf-order checking, and report generation with **SAML 2.0 Authentication**, **Superadmin User Management**, **Run History Tracking**, and **Persistent Cache Storage**.

For background on the original application, see the [Ex Libris Developer Blog post](https://developers.exlibrisgroup.com/blog/Shelf-Inventory-using-Alma-APIs).

---

## Key Features

- 🔒 **SAML 2.0 Single Sign-On**: Unified OpenAthens / Shibboleth authentication with signed assertions, reverse-proxy TLS termination support, and Single Logout (SLO).
- ⚙️ **Superadmin User Access Management**: Dynamic access control with `:admin` role tagging in `allowed_users.txt`. Superadmins can add, remove, and manage allowed users dynamically through an interactive UI modal without restarting pods.
- 📜 **Run History & File Archiving**: Tracks every inventory run with date/time, user identifier, library, location, total barcodes, and issue counts. Archived input `.xlsx` files and output `.csv` reports are stored persistently in `/srv/app/cache/` for on-demand user downloads.
- 💾 **Superadmin Cache Manager & 30-Day Auto-Rotation**: Barcode XML responses from Alma are cached locally for 30 days (`Monthly` TTL). Built-in Cache Manager allows Superadmins to monitor PVC disk usage and perform one-click pruning of expired entries (>30 days) or old report archives (>90 days).
- ⚡ **Parallel Alma API Barcode Fetching**: Uses `curl_multi` batching to query Alma APIs in parallel while preserving exact shelf-scan sequence order.
- 📚 **Dewey & LC Call Number Normalization**: Parses and normalizes complex Dewey Decimal and Library of Congress call numbers (including volume subparts like `t.2:bk.1` and cutter suffixes) for accurate shelf-order sorting.

---

## Architecture & Storage

```
/srv/app/cache/ (Mounted to Kubernetes PVC: lib-inventory-cache-pvc)
├── barcodes/            # Cached Alma API XML responses (30-day TTL auto-cleanup)
├── output/              # Generated inventory report CSV files
├── uploads/             # Archived input .xlsx spreadsheets for history downloads
├── upload/              # Temporary staging area during active processing
├── allowed_users.json   # Writable overlay for Superadmin user management edits
└── run_history.json     # System-wide run history metadata log
```

---

## Environment Variables

| Variable | Description | Default / Example |
|---|---|---|
| `ALMA_SHELFLIST_API_KEY` | Alma API key (read-only Bibs & Config access) | Secret in K8s / `.env` locally |
| `SAML_SP_ENTITY_ID` | Unified SAML Service Provider Entity ID | `https://inventory.lib.purdue.edu/saml/metadata` |
| `SAML_SP_ACS_URL` | Assertion Consumer Service (ACS) URL | `https://dev-inventory.lib.purdue.edu/saml/acs` |
| `SAML_IDP_ENTITY_ID` | Identity Provider Entity ID | `https://idp.purdue.edu/entity` |
| `SAML_IDP_SSO_URL` | Identity Provider SSO Login URL | `https://login.openathens.net/saml/2/sso/purdue.edu` |
| `SAML_IDP_CERT_PATH` | Path to IdP X.509 Certificate | `/etc/saml/idp.crt` |
| `ALLOWED_USERS_FILE` | Path to seed allowed users list | `/etc/saml/allowed_users.txt` |
| `HTTP_PROXY` / `HTTPS_PROXY` | Egress proxy configuration for Alma API calls | `http://proxy.itap.purdue.edu:3128` |

---

## Local Development Setup

1. **Clone the repository**:
   ```bash
   git clone https://github.com/dlingley/alma_inventory_docker.git
   cd alma_inventory_docker
   ```

2. **Configure your environment**:
   ```bash
   cp .env.example .env
   ```
   Add your Alma Shelflist API key to `.env`:
   ```env
   ALMA_SHELFLIST_API_KEY=your_actual_key_here
   ```

3. **Start the local Docker environment**:
   ```bash
   docker compose up --build
   ```

4. **Access the application**:
   - HTTP: [http://localhost:8080](http://localhost:8080)
   - HTTPS (Local SSL): [https://localhost:8443](https://localhost:8443)

---

## Kubernetes & Deployment Workflow

Deployment is fully automated via GitHub Actions (`.github/workflows/deploy.yml`):

### NonProd Deployment (`dev-inventory.lib.purdue.edu`)
Pushing code to the `master` branch triggers the automated build and rollout to the `lib-inventory-nonprod` namespace:
```bash
git checkout master
git push origin master
```

### Production Deployment (`inventory.lib.purdue.edu`)
Pushing code to the `production` branch triggers the automated build and rollout to the `lib-inventory-prod` namespace:
```bash
# Force-update production branch to match master and push to deploy
git checkout -B production
git rebase master
git push origin production -f
git checkout master
```

---

## Superadmin Configuration

Superadmins are designated by appending `:admin` to their email in `allowed_users.txt` or via the Kubernetes ConfigMap:

```text
dlingley@purdue.edu:admin
flipscom@purdue.edu
mtriehle@purdue.edu
```

### Updating Allowed Users ConfigMap in Kubernetes

```bash
cat <<'EOF' > /tmp/allowed_users.txt
dlingley@purdue.edu:admin
flipscom@purdue.edu
mtriehle@purdue.edu
EOF

# NonProd:
kubectl create configmap lib-inventory-allowed-users \
  --namespace=lib-inventory-nonprod \
  --from-file=allowed_users.txt=/tmp/allowed_users.txt \
  --dry-run=client -o yaml | kubectl apply -f -

# Prod:
kubectl create configmap lib-inventory-allowed-users \
  --namespace=lib-inventory-prod \
  --from-file=allowed_users.txt=/tmp/allowed_users.txt \
  --dry-run=client -o yaml | kubectl apply -f -
```

---

## Testing & Validation

A standalone regression test suite validates the Dewey and LC call number sorting algorithms:

```bash
docker compose exec app php test_sort.php
```

If you modify `SortCallNumber.php`, always run `test_sort.php` to verify there are no regressions in volume subpart parsing (e.g. `t.2:bk.1`) or cutter sorting logic.
