<?php

use App\Models\DbCopy;
use App\Models\DbCopyRow;

it('belongs to a db copy', function () {
    $row = DbCopyRow::factory()->create();

    expect($row->dbCopy)->toBeInstanceOf(DbCopy::class);
    expect($row->dbCopy->rows)->toContain($row);
});
