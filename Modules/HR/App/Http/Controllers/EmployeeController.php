<?php

namespace Modules\HR\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\App\Services\EmployeeService;

class EmployeeController extends Controller
{
    public function __construct(
        private EmployeeService $employeeService
    ) {}

    /**
     * GET /api/v1/hr/employees
     * لیست پرسنل با فیلتر و pagination
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'unit_id']);
        $perPage = min((int) $request->input('per_page', 15), 50);

        $employees = $this->employeeService->getEmployeesList($filters, $perPage);

        return response()->json($employees);
    }

    /**
     * GET /api/v1/hr/employees/{user}
     * پروفایل یک کارمند
     */
    public function show(int $user): JsonResponse
    {
        $profile = $this->employeeService->getEmployeeProfile($user);

        if (!$profile) {
            return response()->json([
                'message' => 'کارمند یافت نشد.',
            ], 404);
        }

        return response()->json(['employee' => $profile]);
    }


    /**
     * GET /api/v1/hr/employees/{user}/relatives
     * اطلاعات خانواده کارمند
     */
    public function relatives(int $user): JsonResponse
    {
        $relatives = $this->employeeService->getEmployeeRelatives($user);

        return response()->json([
            'relatives' => $relatives,
            'total'     => count($relatives),
        ]);
    }


    /**
     * GET /api/v1/hr/employees/{user}/history
     * تاریخچه احکام و سمت‌های کارمند
     */
    public function history(int $user): JsonResponse
    {
        $history = $this->employeeService->getStatuteHistory($user);

        return response()->json([
            'history' => $history,
            'total'   => count($history),
        ]);
    }


    /**
     * GET /api/v1/hr/employees/{user}/history/details
     * جزئیات احکام یک دوره خاص
     */
    /**
     * GET /api/v1/hr/employees/{user}/history/details
     * جزئیات احکام یک دوره خاص
     */
    public function historyDetails(Request $request, int $user): JsonResponse
    {
        $validated = $request->validate([
            'post_ref' => 'required|integer',
            'job_ref'  => 'required|integer',
        ], [
            'post_ref.required' => 'post_ref الزامی است.',
            'job_ref.required'  => 'job_ref الزامی است.',
        ]);

        $details = $this->employeeService->getStatuteHistoryDetails(
            $user,
            (int) $validated['post_ref'],
            (int) $validated['job_ref']
        );

        return response()->json([
            'details' => $details,
            'total'   => count($details),
        ]);
    }

    /**
     * GET /api/v1/hr/employees/{user}/attendance
     * اطلاعات تردد کارمند
     */
    public function attendance(Request $request, int $user): JsonResponse
    {
        $month = $request->query('month');

        $data = $this->employeeService->getEmployeeAttendance($user, $month);

        if (isset($data['error'])) {
            return response()->json(['message' => $data['error']], 404);
        }

        return response()->json($data);
    }


}
