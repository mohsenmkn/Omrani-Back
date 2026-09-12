<?php

namespace Modules\Assessment\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Assessment\App\Models\AssessmentPeriod;
use Modules\Assessment\App\Services\AssessmentBulkService;

// AssessmentPeriodController.php

class AssessmentPeriodController extends Controller
{
    public function generate(AssessmentPeriod $period, AssessmentBulkService $bulkService): JsonResponse
    {
        $result = $bulkService->generateFromRules($period);

        return response()->json([
            'message' => "ارزیابی‌ها با موفقیت ساخته شدند.",
            'created' => $result['created'],
            'skipped' => $result['skipped'],
        ]);
    }
}
