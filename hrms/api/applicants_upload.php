<?php
// Public endpoint: accepts the multipart application form (with CV file),
// stores the file, inserts the applicant row, and provisions a temporary
// applicant-portal account so they can check status/log in immediately.
//
// Must stay reachable by anonymous applicants — common.php's auth gate
// blocks every script except auth.php when there's no session, so without
// this define every submission 401s with "Login required" before this
// file's own code ever runs (matches the same bypass positions.php uses
// for its public GET).
define('HRMS_NO_AUTH_GATE', true);
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/security.php";
require_once __DIR__ . "/feature_init.php";
require_once __DIR__ . "/request_documents.php";
$pdo = conn();
hrms_feature_init($pdo);

// Fixed default password for all temporary applicant accounts.
const APPLICANT_DEFAULT_PASSWORD = 'applicant123';

function ensure_applicant_accounts_table($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS hrms_applicant_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        applicant_id INT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(150) NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
ensure_applicant_accounts_table($pdo);

// Applicant profile columns (contact number, birthdate, etc.) — shared
// helper lives in request_documents.php so this file and api/applicants.php
// always agree on the same column list.
hrms_ensure_applicant_profile_columns($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$position = trim($_POST['position'] ?? '');
$interviewMode = $_POST['interview_mode'] ?? '';
$notes = trim($_POST['notes'] ?? '');

// New profile fields.
$contactNumber = trim($_POST['contact_number'] ?? '');
$birthdate = trim($_POST['birthdate'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$address = trim($_POST['address'] ?? '');
$civilStatus = trim($_POST['civil_status'] ?? '');
$preferredStartDate = trim($_POST['preferred_start_date'] ?? '');
$referralSource = trim($_POST['referral_source'] ?? '');

if ($firstName === '' || $lastName === '' || $email === '' || $position === '') {
    response(['success' => false, 'message' => 'First name, last name, email, and position are required'], 400);
}
if (!in_array($interviewMode, ['Online', 'Walk-in'], true)) {
    response(['success' => false, 'message' => 'Please choose Online or Walk-in for your interview'], 400);
}

// Contact number: required. Accepts PH mobile formats like 09171234567
// or +639171234567, with optional spaces/dashes which we strip first.
$contactDigitsOnly = preg_replace('/[\s\-()]/', '', $contactNumber);
if ($contactDigitsOnly === '') {
    response(['success' => false, 'message' => 'Contact number is required'], 400);
}
if (!preg_match('/^(\+63|0)9\d{9}$/', $contactDigitsOnly)) {
    response(['success' => false, 'message' => 'Please enter a valid PH mobile number, e.g. 09171234567'], 400);
}

// Birthdate: required, must be a real date, and applicant must be 18+.
if ($birthdate === '') {
    response(['success' => false, 'message' => 'Birthdate is required'], 400);
}
$birthdateObj = DateTime::createFromFormat('Y-m-d', $birthdate);
if (!$birthdateObj || $birthdateObj->format('Y-m-d') !== $birthdate) {
    response(['success' => false, 'message' => 'Please enter a valid birthdate'], 400);
}
$age = $birthdateObj->diff(new DateTime())->y;
if ($birthdateObj > new DateTime()) {
    response(['success' => false, 'message' => 'Birthdate cannot be in the future'], 400);
}
if ($age < 18) {
    response(['success' => false, 'message' => 'Applicants must be at least 18 years old'], 400);
}

// Gender / civil status: optional, but constrain to known values if sent
// (defends against arbitrary free text landing in a "structured" field).
$allowedGenders = ['Male', 'Female', 'Prefer not to say'];
if ($gender !== '' && !in_array($gender, $allowedGenders, true)) $gender = '';

$allowedCivilStatus = ['Single', 'Married', 'Widowed', 'Separated'];
if ($civilStatus !== '' && !in_array($civilStatus, $allowedCivilStatus, true)) $civilStatus = '';

$allowedReferralSources = ['Referral', 'Walk-in poster', 'Facebook', 'Job site', 'Other'];
if ($referralSource !== '' && !in_array($referralSource, $allowedReferralSources, true)) $referralSource = '';

// Preferred start date: optional, but if given must be a valid date and
// not in the past.
if ($preferredStartDate !== '') {
    $startObj = DateTime::createFromFormat('Y-m-d', $preferredStartDate);
    if (!$startObj || $startObj->format('Y-m-d') !== $preferredStartDate) {
        response(['success' => false, 'message' => 'Please enter a valid preferred start date'], 400);
    }
    $today = new DateTime('today');
    if ($startObj < $today) {
        response(['success' => false, 'message' => 'Preferred start date cannot be in the past'], 400);
    }
}

if (strlen($address) > 500) {
    response(['success' => false, 'message' => 'Address is too long (max 500 characters)'], 400);
}

$existing = $pdo->prepare("SELECT id FROM hrms_applicants WHERE email = ?");
$existing->execute([$email]);
if ($existing->fetch()) {
    response(['success' => false, 'message' => 'An application with this email already exists. Check your status instead.'], 409);
}

// The authoritative slot check happens inside the transaction below
// (with a row lock), so two applicants racing for the last slot can't
// both slip through. This file only pre-validates form fields up here.

$cvPath = null;
if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
    $allowedExt = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg'];
    $ext = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        response(['success' => false, 'message' => 'CV file type not allowed'], 400);
    }
    $uploadDir = __DIR__ . '/../uploads/cv';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $safeName = 'cv_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath = $uploadDir . '/' . $safeName;
    if (!move_uploaded_file($_FILES['cv']['tmp_name'], $destPath)) {
        response(['success' => false, 'message' => 'Failed to save CV upload'], 500);
    }
    $cvPath = 'uploads/cv/' . $safeName;
}

$fullName = trim($firstName . ' ' . $lastName);

$pdo->beginTransaction();
try {
    // Lock the position row for the duration of this transaction so two
    // applicants racing for the last slot can't both get inserted before
    // either commit sees the other's count (FOR UPDATE only takes effect
    // inside a transaction, which is why this check moved down here from
    // the earlier plain SELECT).
    $posStmt = $pdo->prepare("SELECT slots_total, is_open FROM hrms_positions WHERE title = ? FOR UPDATE");
    $posStmt->execute([$position]);
    $posRow = $posStmt->fetch();
    if ($posRow) {
        $filledStmt = $pdo->prepare("SELECT COUNT(*) FROM hrms_applicants WHERE position = ? AND status <> 'Rejected'");
        $filledStmt->execute([$position]);
        $filled = (int)$filledStmt->fetchColumn();
        $isAvailable = (int)$posRow['is_open'] && $filled < (int)$posRow['slots_total'];
        if (!$isAvailable) {
            $pdo->rollBack();
            response(['success' => false, 'message' => 'This position is no longer accepting applications — all slots are filled.'], 409);
        }
    }
    // If the position isn't in hrms_positions at all, allow it through
    // (keeps this endpoint working for any legacy/ad-hoc position names).

    $pdo->prepare("INSERT INTO hrms_applicants
            (name, first_name, last_name, email, position, interview_mode, status, stage, cv_path, notes,
             contact_number, birthdate, gender, address, civil_status, preferred_start_date, referral_source)
            VALUES (?,?,?,?,?,?, 'Pending', 'Pending', ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([
            $fullName, $firstName, $lastName, $email, $position, $interviewMode, $cvPath, $notes ?: null,
            $contactDigitsOnly, $birthdate, $gender ?: null, $address ?: null, $civilStatus ?: null,
            $preferredStartDate ?: null, $referralSource ?: null,
        ]);
    $applicantId = (int)$pdo->lastInsertId();

    // Provision (or reuse) the temporary applicant-portal account.
    $acctCheck = $pdo->prepare("SELECT id FROM hrms_applicant_accounts WHERE email = ?");
    $acctCheck->execute([$email]);
    if (!$acctCheck->fetch()) {
        $pdo->prepare("INSERT INTO hrms_applicant_accounts (applicant_id, email, password, full_name, status) VALUES (?,?,?,?, 'active')")
            ->execute([$applicantId, $email, password_hash(APPLICANT_DEFAULT_PASSWORD, PASSWORD_DEFAULT), $fullName]);
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    response(['success' => false, 'message' => 'Failed to submit application. Please try again.'], 500);
}

addLog($fullName, 'Applicant Submitted', "Applied for $position ($interviewMode interview)");
response([
    'success' => true,
    'message' => 'Application submitted.',
    'portal_login' => ['email' => $email, 'password' => APPLICANT_DEFAULT_PASSWORD],
]);