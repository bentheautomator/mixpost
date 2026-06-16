<?php

namespace Inovector\Mixpost\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Inovector\Mixpost\Concerns\Model\BelongsToOwner;
use Inovector\Mixpost\Concerns\Model\HasUuid;

class Tag extends Model
{
    use BelongsToOwner;
    use HasFactory;
    use HasUuid;

    public $table = 'mixpost_tags';

    protected $fillable = [
        'name',
        'hex_color',
    ];
}
