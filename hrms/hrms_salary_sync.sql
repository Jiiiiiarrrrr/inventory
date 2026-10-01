-- =====================================================================
-- One-time sync: copy the published offer salary of HIRED applicants
-- into the employee record (older hire flows left monthly_salary = 0,
-- which made Payroll show "No contract salary on file").
-- Run once in phpMyAdmin (SQL tab of brewco_inventory).
-- Optional: api/payroll.php now also self-heals this automatically
-- the first time you open/select the employee in Payroll.
-- =====================================================================
UPDATE hrms_employees e
JOIN hrms_applicants a
  ON a.email = e.email
 AND a.status = 'Hired'
 AND a.offer_salary > 0
SET e.monthly_salary = a.offer_salary
WHERE e.monthly_salary <= 0;
