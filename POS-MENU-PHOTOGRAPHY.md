# POS menu photography update

Updated after the Batch 1–8 responsive delivery in response to feedback on the POS menu cards.

## Change

The POS product cards now use the existing item image uploads as prominent menu photography instead of icon-sized/cropped visuals.

- Product images in `pos/uploads/` are shown with `object-fit: contain` so complete drinks, pastries, and meals are visible.
- Each photo receives a warm Brew & Co. background while preserving transparent PNG artwork.
- Category/menu card layout gives the photo more vertical space.
- Home-page featured products now use the same item images, with the existing emoji still used as a fallback when an item has no image.
- Older seeded `menu_items` rows with an empty `image` database value now receive a **display-only** match to the existing local product photos. This makes real images appear immediately without needing a manual re-upload or a database update.
- POS Kiosk now uses the same image paths as the menu grid.

## Files changed

- `pos/api/items.php`
- `pos/kiosk.php`
- `pos/css/style.css`
- `pos/js/app.js`

No item record, image upload, product price, stock, cart, checkout, API write, or order handling behaviour changed.
