<?php

namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentMethod extends Model
{
    protected $fillable = ['title', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
