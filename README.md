# Portfolio with local admin

The portfolio and its admin panel run on XAMPP with PHP 8.2. No MySQL setup is required: content is stored persistently in protected JSON files.

- Website: **http://localhost/developer_portfolio/**
- Admin: **http://localhost/developer_portfolio/admin/**

Open Admin on this computer and choose your own password on the first visit. Then edit the website through the panel and click **Save changes**. Photo and resume uploads apply immediately.

## What you can manage

- Profile, email, phone, WhatsApp and social accounts.
- Home intro, highlighted heading, stats, scrolling technologies and call to action.
- About text, bullet points and developer card.
- Skills, services, experience and projects: add, edit, reorder, hide and delete entries.
- All six existing case studies, plus automatically generated case-study pages for new projects.
- Contact text, form labels, navigation, footer, section visibility and page titles/descriptions.
- Profile photo and PDF resume uploads.
- Contact messages, with read/delete controls and email links for replies.
- Your admin password.

Shared sections update on both Home and their dedicated pages. The homepage shows the first three visible projects. Hidden or deleted projects' case-study pages return 404.

## Storage and templates

`cms/data/content.json` is the saved content. `cms/data/templates.json` contains the page layouts and editable-field definitions. `cms/data/auth.json` is created on first setup and stores a password hash. `cms/data/messages.json` stores contact messages. Apache blocks direct access to this directory. Every content save creates a copy of the previous content there.

Upload files are stored in `dist/uploads/`. Photo uploads accept JPG, PNG and WebP up to 4 MB; resumes accept PDF up to 10 MB, subject to PHP's configured upload limits. The original photo and resume remain available.

PHP `index.php` entry points render the original visual layout with saved content. Existing HTML files are the original import; edit through Admin rather than changing those files. Apache must honor the `dist/.htaccess` DirectoryIndex setting.

## Contact messages

The contact form now saves messages in the local admin inbox. It does not send email automatically. Open the sender's email link in Messages to reply through your email application.

## Hosting

This CMS is for the local XAMPP website. The previously published static preview is separate. To move the CMS online, use PHP-capable hosting and copy the website, CMS files and protected content storage. A static-only host cannot execute the PHP admin panel.

## Verification

PHP and JavaScript syntax checks passed. Integration checks cover all 13 original pages, authenticated/unauthenticated access, CSRF, protected storage, persistent shared edits, case-study creation, hidden project pages, upload validation, conflict detection and the contact inbox. Chrome checks cover saving through the actual admin UI, all six case-study editors, and desktop/mobile layouts.

For a fresh import, run `C:\xampp\php\php.exe cms\initialize.php`, then `C:\xampp\php\php.exe cms\complete-import.php`. The initializer preserves existing content. The original `build-reference.ps1` imports public reference assets; it is not the content-management workflow.
