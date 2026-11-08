<?php

/**
 * Who may reach a note: guests are sent to login, other users get 404 (not 403),
 * so the app never reveals that a note exists.
 */

it('redirects guests to login')->todo();
it('returns 404 when viewing another user\'s note')->todo();
it('returns 404 when updating another user\'s note')->todo();
it('returns 404 when deleting another user\'s note')->todo();
