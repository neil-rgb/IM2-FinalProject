# IM2-FinalProject

For an existing B-TRACK database, import `database/document_recycle_bin.sql` before using the Recycle Bin. The full Week 3 SQL file is for a fresh installation and recreates the database.

For optional soft copies, import `database/document_soft_copies.sql` on existing databases. PHP needs file uploads enabled and a request limit above 10 MB. Set `BTRACK_SOFT_COPY_DIR` to a writable directory outside the web root if the default private data directory is unavailable. Back up this directory together with the database.