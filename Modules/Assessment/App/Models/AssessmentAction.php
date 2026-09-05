<?php

namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAction extends Model
{
    protected $fillable = ['gap_id', 'method_id', 'title', 'description', 'due_date', 'status', 'completed_at', 'training_course_code'];
    protected $casts = ['due_date' => 'date', 'completed_at' => 'datetime'];
    public function gap(): BelongsTo { return $this->belongsTo(AssessmentGap::class, 'gap_id'); }
    public function method(): BelongsTo { return $this->belongsTo(AssessmentMethod::class, 'method_id'); }
}
