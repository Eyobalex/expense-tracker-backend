<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
test('that true is true', function (): void {
    $this->assertTrue(true);
});
