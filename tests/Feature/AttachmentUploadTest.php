<?php

/**
 * Uploading files to my own note. Allowed types and max size are read
 * from config/attachments.php, never hard-coded, with Storage::fake('s3').
 */

it('stores an allowed file under a random path')->todo();
it('rejects a disallowed type such as svg')->todo();
it('rejects a file larger than the max size')->todo();
it('downloads with Content-Disposition attachment and nosniff headers')->todo();
it('deletes an attachment and its stored file')->todo();
