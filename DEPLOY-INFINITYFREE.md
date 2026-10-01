# Deploy Brew & Co. on InfinityFree (free plan)

## Why the original import failed
InfinityFree free hosting blocks MySQL stored procedures and triggers. The old local dump began with a `CREATE DEFINER=root@localhost PROCEDURE record_movement` statement, so phpMyAdmin stopped with error **#1044** before the application tables could be imported.

This project is now compatible with that restriction: stock-movement logic is performed by `inv_record_movement()` in `db.php`, and the database import contains only tables, data, indexes, and foreign keys.

## Import the database

1. In InfinityFree Client Area, create/select the database named in your hosting panel.
2. Open that database in the InfinityFree phpMyAdmin. The selected database name must appear at the top before importing.
3. If the failed attempt created any tables, use the **Structure** tab to drop those partial tables first. In the screenshot failure, the routine is the first statement, so normally no tables were created.
4. Choose **Import** and upload **`brewco_inventory_infinityfree.sql`** from this project (or the updated `brewco_inventory (5).sql`; they are intentionally routine-free).
5. Wait for phpMyAdmin’s success message, then confirm that the `users`, `items`, and `menu_items` tables appear.

Do **not** use an old archive copy of the SQL that contains `CREATE DEFINER` or `PROCEDURE`.

## Configure the uploaded PHP files

1. Copy `config.infinityfree.example.php` to **`config.php`** beside `db.php`.
2. Edit `config.php` with the **MySQL hostname, database name, username, and password from the InfinityFree Client Area**. Do not use local XAMPP values such as `localhost`, `root`, or a blank password.
3. Update `SITE_URL` to your live `https://...` address.
4. Upload all project files to the site’s `htdocs` directory, keeping the folders intact.

`config.php` is loaded when present and overrides local defaults. It is deliberately not supplied with a real password.

## Important limitation
The free InfinityFree plan does not support stored procedures or triggers. This release does not depend on either one. Future imports should use the provided routine-free SQL file.
