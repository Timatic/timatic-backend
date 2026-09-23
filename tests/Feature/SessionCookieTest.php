<?php

it('does not send the session cookie on cross site requests', function () {
    expect(config('session.same_site'))->not->toBe('none');
});
