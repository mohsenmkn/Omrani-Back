<?php

namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAnswer extends Model
{
    protected $fillable = ['assessment_id', 'question_id', 'score', 'comment'];
    protected $casts = ['score' => 'integer'];
    public function assessment(): BelongsTo { return $this->belongsTo(Assessment::class); }
    public function question(): BelongsTo { return $this->belongsTo(AssessmentQuestion::class); }
}

