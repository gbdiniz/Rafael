<?php

it('returns a successful response', function () {
    $this->get(route('login'))->assertOk();
});
