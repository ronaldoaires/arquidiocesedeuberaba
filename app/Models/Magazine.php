<?php

namespace App\Models;

class Magazine extends ContentModel
{
    protected $table = 'magazines';

    public static string $sourceTable = 'revista';
}
