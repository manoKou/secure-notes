<?php

/**
 * Files are only reachable through their own note by its owner.
 * Other users and mismatched note/attachment URLs get 404.
 */

it('returns 404 when another user downloads my file')->todo();
it('returns 404 when another user deletes my file')->todo();
it('returns 404 when another user uploads to my note')->todo();
it('returns 404 when the attachment belongs to a different note')->todo();
