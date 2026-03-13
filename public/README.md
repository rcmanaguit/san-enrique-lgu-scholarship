# Public Web Root

This folder can be used as the Apache document root for the project.

What it contains:
- `index.php`: front controller that routes public requests directly to `app/pages`
- `files.php`: serves files from the project `uploads/` directory
- `.htaccess`: rewrite and hardening rules for the public web root
- `assets/`: synced copy of the root `assets/` directory

Notes:
- The repository root is no longer a public web root.
- Root-level `*.php`, `shared/*.php`, and `admin-only/*.php` wrappers were removed.
- Public URLs like `/login.php`, `/shared/dashboard.php`, and `/admin-only/logs.php` are handled by `public/index.php`.

To refresh static assets after changes:

```powershell
php scripts/sync-public-assets.php
```

Recommended Apache setup:
- Point the virtual host `DocumentRoot` to this `public/` folder
- Keep the project root and `app/` folder outside direct web access
