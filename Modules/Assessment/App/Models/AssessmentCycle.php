<?php

namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentCycle extends Model
{

    protected $fillable = ['title', 'type', 'start_date', 'end_date', 'status', 'description'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];
    public function assessments(): HasMany { return $this->hasMany(Assessment::class, 'cycle_id'); }

    // AssessmentCycle
    const STATUS_DRAFT  = 'draft';
    const STATUS_ACTIVE = 'active';
    const STATUS_CLOSED = 'closed';

}
