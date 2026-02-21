# F-183: Client Complaint Submission

## Summary
Client complaint submission system. Clients can report problems on delivered/completed orders by selecting a category (food quality, delivery issue, missing item, wrong order, other), writing a description (10-1000 chars), and optionally uploading a photo. One complaint per order enforced. Complaint triggers notification to cook, activity logging, and payment clearance pause. Redirects to complaint status page after submission.

## Key Files
- `app/Http/Controllers/Client/ComplaintController.php` — create/store/show with Gale responses
- `app/Services/ComplaintSubmissionService.php` — business logic
- `app/Models/Complaint.php` — CLIENT_CATEGORIES, photo_path
- `resources/views/client/complaints/create.blade.php` — complaint form
- `resources/views/client/complaints/show.blade.php` — complaint status display
- `tests/Unit/Client/ComplaintSubmissionUnitTest.php` — 20 unit tests

## Verification
- 9/9 verification steps passed
- 3/3 edge cases passed
- Responsive: PASS (375px, 768px, 1280px)
- Theme: PASS (light + dark)
- Bugs fixed: 1 (validateState/multipart FormData incompatibility)
- Retries: Impl(0) + Rev(0) + Test(0)

## Convention
When Gale forms use x-files for file uploads, use $request->validate() instead of validateState() because Gale auto-converts to multipart FormData.
