<?php

namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentCategory extends Model
{
    protected $fillable = ['title', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function questions(): HasMany { return $this->hasMany(AssessmentQuestion::class, 'category_id'); }
}
