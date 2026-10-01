# POS staff name fields

The modern POS Admin **Add staff** and **Edit staff** modal now uses two required fields:

- First name
- Last name

For existing staff records, the stored `full_name` value is split into the first word and remaining words when the edit modal opens. On save, the two fields are combined back into the existing `full_name` API/database field.

This preserves the current `pos_users` schema, account API, staff listing, authentication, roles, and login behavior while providing the requested two-input form.

Responsive behavior:

- First name and Last name appear side-by-side on wider screens.
- They stack into separate full-width inputs on narrow phones.
