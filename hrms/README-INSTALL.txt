=====================================================================
HRMS — SIGNED REQUEST FEATURE (formal letter uploader + signature)
=====================================================================
Staff requests now REQUIRE:
  1. A formal letter document (PDF, DOC, DOCX — max 8MB), then
  2. A drawn signature (signature pad) — ONLY after signing is the
     request sent to HR (Admin) or Superadmin.
Approvers can open the letter and view the signature from every
request list before approving/rejecting.

---------------------------------------------------------------------
FILES TO COPY (backup your originals first!)
---------------------------------------------------------------------
From this folder                       ->  Your project
-------------------------------------  -----------------------------------------
api/request_documents.php   (NEW)      ->  hrms/api/request_documents.php
api/request_submit.php      (NEW)      ->  hrms/api/request_submit.php
api/requests.php            (UPDATED)  ->  hrms/api/requests.php
api/staff_requests.php      (UPDATED)  ->  hrms/api/staff_requests.php
modules/requests.php        (UPDATED)  ->  hrms/modules/requests.php
modules/sa-requests.php     (UPDATED)  ->  hrms/modules/sa-requests.php
indexxx.php                 (UPDATED)  ->  hrms/indexxx.php   (staff portal)
hrms_request_documents.sql  (OPTIONAL) ->  run in phpMyAdmin only if you prefer
                                           manual migration (endpoints also
                                           migrate automatically on first use)

Full target path on your machine:
  C:\xampp\htdocs\INVENTORY\hrms\...

---------------------------------------------------------------------
WHAT CHANGED
---------------------------------------------------------------------
* NEW api/request_submit.php
  - Multipart endpoint (FormData): fields kind, detail, routed_to,
    leave_type, start_date, end_date, reason + files:
      letter    = the formal letter (pdf/doc/docx, max 8MB)  [REQUIRED]
      signature = PNG data URL drawn on the signature pad    [REQUIRED]
  - If either is missing/invalid -> 400 JSON error, nothing is saved.
  - Leave  -> inserts into hrms_leave_requests (with letter+signature)
  - General-> inserts into hrms_approval_requests (with routed_to,
    letter+signature)
  - Notifies the routed role (HR or Superadmin) + audit log.

* NEW api/request_documents.php (helper, included by the endpoints)
  - Idempotent migration: adds letter_path, letter_name,
    signature_path, signed_at, signed_by to BOTH request tables.
  - Saves letters/signatures into hrms/uploads/requests/
    (folder auto-created; make sure it is writable by Apache).

* UPDATED api/requests.php
  - All GET lists (?mine=1, ?to_process=1, ?status=X, default) now
    return the document columns + a `source` flag ('approval'|'leave')
    so the UI can link the letter/signature and route approvals.
  - to_process now includes pending leave requests for both HR and
    Superadmin (leaves have no routed_to).
  - Approve/reject accepts {action, id, source?, comment?} and writes
    approval history + notifies the employee by email.
  - The OLD unsigned JSON creation path is now REJECTED with a clear
    message (requests must come from the signed form).

* UPDATED api/staff_requests.php
  - GET now returns letter/signature columns + source for history.
  - Old unsigned POST is disabled (points staff to the signed form).

* UPDATED modules/requests.php (Requests page, admin shell iframe)
  - Formal-letter dropzone (click or drag & drop) with file chip,
    type/size validation (pdf/doc/docx, 8MB).
  - "Submit Request -> Sign" opens the SIGNATURE MODAL: canvas pad
    (mouse/touch/stylus), Clear / Cancel, and "Sign & Send Request"
    (disabled until you draw). Only then is the request submitted.
  - History + "Requests to Process" tables show Letter and Signature
    columns (open letter in new tab, view signature).

* UPDATED modules/sa-requests.php (Superadmin request management)
  - Table shows Formal Letter (download link) and Signature
    (thumbnail preview, click to enlarge) per request.
  - Approve/Reject now send the `source` flag.

* UPDATED indexxx.php (staff portal)
  - Same letter dropzone + signature modal flow on the staff
    "My Requests" form; history shows Letter / Signed links and
    "signed by" info.

---------------------------------------------------------------------
DATABASE
---------------------------------------------------------------------
No manual step required: columns are added automatically the first
time any requests endpoint runs (SHOW COLUMNS check, then ALTER).
Optional: run hrms_request_documents.sql in phpMyAdmin instead.

---------------------------------------------------------------------
TEST FLOW (quick demo)
---------------------------------------------------------------------
1. Log in as staff (staff portal indexxx.php) or open Requests page.
2. Fill a request, try Submit WITHOUT a letter  -> error shown.
3. Upload a PDF/DOC/DOCX letter, click Submit -> signature modal.
4. Try "Sign & Send" without drawing            -> blocked.
5. Draw signature -> "Sign & Send Request"      -> request sent.
6. Log in as HR / Superadmin: open the letter, view the signature,
   then Approve/Reject. Employee gets a notification.

Storage location of uploaded files:
  C:\xampp\htdocs\INVENTORY\hrms\uploads\requests\
    letter_YYYYmmdd_HHiiss_xxxx.pdf|doc|docx
    sig_YYYYmmdd_HHiiss_xxxx.png
=====================================================================
