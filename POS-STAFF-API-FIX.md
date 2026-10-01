# POS staff creation API fix

## Problem

Creating a staff account could return **HTTP 500 Internal Server Error**.

## Cause

`mysqli_stmt::bind_param()` receives its bound values by reference. The POS account API was passing literal source values such as `'admin_created'` and `'signup'` directly into `bind_param()`. PHP treats that as a fatal error when a staff account is created or reactivated.

## Fix

The API now assigns each source value to a variable before binding it:

- Staff creation uses `$source = 'admin_created'`.
- Member sign-up uses `$source = 'signup'`.
- Archived staff reactivation uses variables for both source and ID.

The API/database schema, roles, password hashing, duplicate-email behavior, and account workflow are otherwise unchanged.
