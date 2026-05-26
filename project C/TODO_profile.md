# TODO: Add Profile Picture Changing Feature

## Current State
- Users have avatar URLs stored in the database
- Avatars are displayed in the header after login
- Profile picture changing feature is implemented

## Implementation Plan
- [x] Create uploads directory for user images
- [x] Add profile settings modal to the UI
- [x] Create upload_profile_picture.php for handling file uploads
- [x] Add file upload validation (image types, size limits)
- [x] Update database with new avatar URL
- [x] Refresh UI to show new avatar immediately
- [x] Add error handling and user feedback

## Dependent Files to be edited
- script.js: Add profile modal and upload functionality
- index.html: Add profile settings modal HTML
- styles.css: Style the profile modal
- New file: upload_profile_picture.php

## Followup Steps
- [x] Test image upload with different file types
- [x] Verify database updates
- [x] Test UI updates after upload
- [x] Check error handling for invalid files
- [x] Ensure uploads directory is writable
