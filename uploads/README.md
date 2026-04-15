# Uploads Directory

This directory contains all user-uploaded files.

## Structure

```
uploads/
profile_pictures/          # Temporary profile picture uploads
documents/
  {user_id}/              # User-specific document folders
    profile/              # User's profile picture (after registration)
    proof_of_billing_*.pdf
    valid_id_*.pdf
    coe_*.pdf
```

## File Paths

Database stores relative paths like:
- Profile: `documents/123/profile/profile_a1b2c3d4e5f6g7h8.jpg`
- Documents: `documents/123/proof_of_billing_a1b2c3d4e5f6g7h8.pdf`

## Security Notes

- All files have randomized filenames
- File types are validated (images/PDF only)
- Each user has their own isolated directory
