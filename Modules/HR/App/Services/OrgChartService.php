<?php

namespace Modules\HR\App\Services;

use Modules\HR\App\Models\EmployeePosition;
use Modules\HR\App\Models\OrganizationalUnit;

class OrgChartService
{
    /**
     * ساخت درخت کامل چارت سازمانی با پرسنل
     */
    public function buildTree(): array
    {
        // همه واحدها با پرسنل
        $units = OrganizationalUnit::with(['employees' => function ($q) {
            $q->with('user:id,name,mobile');
        }])
            ->orderBy('level')
            ->orderBy('title')
            ->get();

        // ساخت lookup map
        $unitMap = [];
        foreach ($units as $unit) {
            $unitMap[$unit->id] = [
                'key' => (string) $unit->id,
                'data' => [
                    'id'             => $unit->id,
                    'title'          => $unit->title,
                    'code'           => $unit->code,
                    'level'          => $unit->level,
                    'employee_count' => $unit->employees->count(),
                    'type'           => 'unit',
                ],
                'children' => [],
            ];
        }

        // ساخت درخت
        $tree = [];
        foreach ($unitMap as $id => &$node) {
            $parentId = $units->firstWhere('id', $id)->parent_id;

            if ($parentId && isset($unitMap[$parentId])) {
                $unitMap[$parentId]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);

        // اضافه کردن پرسنل به هر واحد
        foreach ($units as $unit) {
            if (!isset($unitMap[$unit->id])) continue;

            foreach ($unit->employees as $emp) {
                $unitMap[$unit->id]['children'][] = [
                    'key' => "emp-{$emp->id}",
                    'data' => [
                        'type'           => 'employee',
                        'user_id'        => $emp->user_id,
                        'name'           => $emp->user?->name ?? '—',
                        'personnel_code' => $emp->personnel_code,
                        'post_title'     => $emp->post_title,
                        'job_title'      => $emp->job_title,
                        'mobile'         => $emp->user?->mobile,
                    ],
                ];
            }
        }

        return $tree;
    }

    /**
     * آمار کلی چارت
     */
    public function getStats(): array
    {
        return [
            'total_units'     => OrganizationalUnit::count(),
            'total_employees' => EmployeePosition::count(),
            'active_units'    => OrganizationalUnit::where('is_active', true)->count(),
            'last_sync'       => EmployeePosition::max('synced_at'),
        ];
    }
}
