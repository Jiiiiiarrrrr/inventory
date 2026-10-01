BREW & CO. - CONFIG RESET KIT (read me first)
Goal: kill the "403 Forbidden" on your login portals and get you signing in again.

WHAT IS INSIDE (9 files only - no pages, no data, database untouched):
  .htaccess                 root gate (listing block, clean URLs, sessions, headers)
  hrms/.htaccess            HRMS gate
  pos/.htaccess             POS gate
  uploads/.htaccess         stops script execution inside uploads folder
  hrms/uploads/.htaccess    same for HRMS uploads
  index.php                 8-line entry guard (root -> hub)
  hrms/login.php            smart router (logged in -> dashboard, else -> login screen)
  hrms/hrms-login.php       HRMS login page (skips form if already signed in)
  FIX-README-FIRST.txt      this file

STEPS (about 2 minutes):
 1. XAMPP Control Panel -> Apache -> Stop.
 2. Extract this zip INTO  C:\xampp\htdocs\INVENTORY\  and choose
    "Replace the files in the destination".
    - In Explorer: View -> check "Hidden items" so you can SEE .htaccess files.
    - If Windows refuses to replace a file (in use / error 0x80004005):
      DELETE that one file first in Explorer, then extract again.
 3. XAMPP Control Panel -> Apache -> Start.
 4. Test in your browser:
      localhost/INVENTORY/login             -> Inventory sign-in screen
      localhost/INVENTORY/hrms/hrms-login   -> HRMS sign-in screen
      localhost/INVENTORY/hrms/finance.php  -> redirects to sign-in (no Forbidden)
 5. Sign in like normal. Done.

WHY THIS HAPPENED: an interrupted / half-extracted .htaccess from an earlier
merge left a stray "deny everything" line behind, so Apache blocked whole
folders. Replacing the gate files fixes it. Your database, uploads and all
page files are NOT touched by this kit.

Still Forbidden somewhere after this? Screenshot + exact URL to your dev.
Keep this txt OUTSIDE the website folder (or delete it after fixing) -
the site intentionally refuses to serve .txt files.
