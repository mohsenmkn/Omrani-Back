<?php

namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentPost extends Model
{
    protected $fillable = ['title', 'code', 'min_education', 'min_experience_years', 'description', 'is_active','source'];
    protected $casts = ['is_active' => 'boolean'];
    public function questions(): HasMany { return $this->hasMany(AssessmentQuestion::class, 'post_id'); }
    //public function categories(): BelongsToMany { /* اختیاری */ }
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'post_id');
    }
}
