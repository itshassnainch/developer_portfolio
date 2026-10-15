# Free PHP hosting deployment

The website needs PHP 8.2 or newer with DOM, mbstring, sessions, JSON and fileinfo. It writes content and uploads to disk. It does not need MySQL. GitHub Pages and static-only hosting cannot run this PHP admin panel.

## Prepare the upload

Create your local admin password first, then run:

```powershell
C:\xampp\php\php.exe tools\prepare-hosting.php
```

The command prepares a minimal upload directory inside `.deployment/`. Upload **the contents of that directory** into the hosting account's `htdocs` or `public_html` directory. Keep its `admin`, `cms` and `dist` directories together. A ZIP of the same contents can be extracted locally before uploading with a hosting file manager or FTP client.

The package includes your existing admin password hash in protected storage so you can use the same password online. It excludes inbox messages, backups, Git history, testing tools and the original unused reference pages. Keep the package private; never commit it or leave the ZIP on the public hosting account.

## InfinityFree account steps

1. Register at https://dash.infinityfree.com/register and verify your email.
2. Create a free hosting account and select an available free subdomain.
3. Open the account's File Manager and upload the prepared files inside `htdocs`. Include `.htaccess` files.
4. Configure the provider's free SSL certificate, then use the HTTPS address.
5. Open the domain. The website redirects to `/dist/`. Sign in at `/admin/` with your existing local admin password.

Your local and hosted content are separate after deployment. Changes made in the local admin are not automatically uploaded.

## Verify after upload

- Homepage, Skills, Projects and Contact load with the cyan theme.
- The current portrait and PDF resume open.
- `/admin/` allows login, editing, uploads and saving.
- A contact test appears in the hosted inbox.
- `/cms/data/auth.json`, `/cms/data/content.json` and `/cms/data/messages.json` return **403**.

On future updates, preserve the hosted `cms/data/` and `dist/uploads/` directories so saved content, credentials, messages and uploaded files are not overwritten. Start from a new backup before replacing hosted files.
