# IM2-FinalProject

The interface uses React, React Router and TanStack Query, including sign-in, registration and the authenticated dashboard. PHP and MySQL handle authentication, uploads and record changes. Existing `.php` URLs open the React app and support direct links and refreshes, including installations in an Apache subfolder.

After changing files in `frontend/`, run `npm install` once and `npm run build` to rebuild `assets/build/`. Serve the project through PHP or XAMPP as usual. The built assets are included so another checkout can run through PHP immediately. `npm run dev` watches frontend files and rebuilds them as you edit. Refresh the PHP-served page to load a rebuilt version.

For an existing B-TRACK database, import `database/document_recycle_bin.sql` before using the Recycle Bin. The full Week 3 SQL file is for a fresh installation and recreates the database.

For optional soft copies, import `database/document_soft_copies.sql` on existing databases. PHP needs file uploads enabled and a request limit above 10 MB. Set `BTRACK_SOFT_COPY_DIR` to a writable directory outside the web root if the default private data directory is unavailable. Back up this directory together with the database.
