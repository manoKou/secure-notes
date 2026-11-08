<?php

/*
 * Upload rules for note attachments.
 *
 * One place for the allowlist and size limit: AttachmentRequest validates
 * against it and the upload form shows it as a hint, so they never disagree.
 * Codex's tests read these values too, instead of hard-coding them.
 */
return [

    // Allowed file types, checked against the real file content, not the name.
    // No svg/html: they can carry scripts (XSS).
    'mimes' => ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'txt', 'docx'],

    // Maximum size in kilobytes (Laravel's file "max" rule uses KB). 10240 = 10 MB.
    'max_kb' => 10240,

];
