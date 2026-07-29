# Sprint 4.4C — Secure Question Media

## Delivered scope

- Authorized administrators can upload, replace, preview, and remove one image for a question and one image for each option.
- Supported formats are JPEG, PNG, and WebP. SVG and GIF are intentionally rejected.
- Validation uses actual file bytes through `fileinfo` and `getimagesize`; the browser-provided MIME type and original extension are not trusted.
- Default limits are 5 MB, 5000 pixels per side, and 20 megapixels. They are configurable through `.env`.
- Files are stored outside `public/` using cryptographically random names. The storage tree contains Apache deny rules as defense in depth.
- Images are served only through `/admin/questions/media` after the `view_questions` permission is verified.
- Media responses include `nosniff`, private caching, an ETag, a restrictive CSP, and a generic download filename.
- Replacement and removal queue the previous private file in `pending_media_deletions`; an immediate best-effort deletion is attempted after commit.
- Remaining queued files can be processed by the CLI script:

```bash
php scripts/process_pending_media_deletions.php
```

## Database behavior

The existing `question_media` and `pending_media_deletions` tables are used; no schema migration is required for this sprint.

Question images have `media_type = QUESTION_IMAGE` and an exclusive `question_id`. Option images have `media_type = OPTION_IMAGE` and an exclusive `option_id`. Existing database constraints prevent mixed ownership and duplicate images for an owner.

## Audit privacy

Audit entries never contain filenames, storage paths, media tokens, MIME values, image dimensions, or question/option text. They contain only the changed field name (`question_image` or `option_image`).

## Deployment settings

Add or review:

```env
QUESTION_MEDIA_STORAGE_PATH=storage/uploads
QUESTION_MEDIA_MAX_BYTES=5242880
QUESTION_MEDIA_MAX_DIMENSION=5000
QUESTION_MEDIA_MAX_PIXELS=20000000
MEDIA_DELETION_BATCH_SIZE=25
```

The web-server user must have read/write permission on `storage/uploads`, while direct HTTP access to `storage/` must remain denied.
