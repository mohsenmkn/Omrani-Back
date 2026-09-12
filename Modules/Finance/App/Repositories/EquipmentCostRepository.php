<?php

namespace Modules\Finance\App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Modules\Finance\App\Models\EquipmentTypeAccess;

class EquipmentCostRepository
{
    const CONFIRMED_VOUCHER_STATES = [4, 32];

    public function getAccessibleEquipmentTypes(?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $user = Auth::user();

        if ($user && $user->can('equipment_costs.manage')) {
            return $this->getAllEquipmentTypesFromDB();
        }

        $roleIds = $user ? $user->roles->pluck('id')->toArray() : [];

        $accessibleTypeIds = EquipmentTypeAccess::where(function ($q) use ($userId, $roleIds) {
            $q->where('user_id', $userId);
            if (!empty($roleIds)) {
                $q->orWhereIn('role_id', $roleIds);
            }
        })
            ->where('can_view', true)
            ->pluck('dl_type_ref')
            ->unique()
            ->toArray();

        if (empty($accessibleTypeIds)) {
            return [];
        }

        return DB::connection('gtarabar')
            ->table('FIN3.DLType')
            ->whereIn('DLTypeID', $accessibleTypeIds)
            ->select('DLTypeID', 'Title', 'Title_En')
            ->orderBy('Title')
            ->get()
            ->toArray();
    }

    public function getAllEquipmentTypesFromDB(): array
    {
        return DB::connection('gtarabar')
            ->table('FIN3.DLType as dt')
            ->join('FIN3.DL as dl', 'dl.DLTypeRef', '=', 'dt.DLTypeID')
            ->select('dt.DLTypeID', 'dt.Title', 'dt.Title_En')
            ->distinct()
            ->orderBy('dt.Title')
            ->get()
            ->toArray();
    }

    public function canAccessEquipmentType(int $dlTypeRef, ?int $userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        $user = Auth::user();

        if ($user && $user->can('equipment_costs.manage')) {
            return true;
        }

        $roleIds = $user ? $user->roles->pluck('id')->toArray() : [];

        return EquipmentTypeAccess::where('dl_type_ref', $dlTypeRef)
            ->where('can_view', true)
            ->where(function ($q) use ($userId, $roleIds) {
                $q->where('user_id', $userId);
                if (!empty($roleIds)) {
                    $q->orWhereIn('role_id', $roleIds);
                }
            })
            ->exists();
    }

    public function getEquipmentsByType(int $typeId): array
    {
        if (!$this->canAccessEquipmentType($typeId)) {
            abort(403, 'شما دسترسی به این نوع تجهیز را ندارید');
        }

        return DB::connection('gtarabar')
            ->table('FIN3.DL')
            ->where('DLTypeRef', $typeId)
            ->select('DLID', 'Code', 'Title', 'ReferenceID', 'Description')
            ->orderBy('Code')
            ->get()
            ->toArray();
    }

    public function getEquipmentCosts(
        string $equipmentCode,
        int $typeId,
        string $fromDate,
        string $toDate
    ): array {
        if (!$this->canAccessEquipmentType($typeId)) {
            abort(403, 'شما دسترسی به این نوع تجهیز را ندارید');
        }

        return DB::connection('gtarabar')
            ->table('FIN3.Voucher as v')
            ->join('FIN3.VoucherItem as vi', 'v.VoucherID', '=', 'vi.VoucherRef')
            ->join('FIN3.SL as sl', 'vi.SLRef', '=', 'sl.SLID')
            ->join('FIN3.DL as dl', function ($join) use ($typeId) {
                $join->on('dl.Code', '=', 'vi.DLLevel5')
                    ->where('dl.DLTypeRef', '=', $typeId);
            })
            ->whereIn('v.State', self::CONFIRMED_VOUCHER_STATES)
            ->whereBetween('v.Date', [$fromDate, $toDate])
            ->where('vi.DLLevel5', $equipmentCode)
            ->where('vi.DLTypeRef5', $typeId)
            ->select(
                'v.VoucherID',
                'v.Number as voucher_number',
                'v.Date as voucher_date',
                'v.State as voucher_state',
                'v.Description as voucher_description',
                'vi.RowNumber as row_number',
                'sl.Title as account_title',
                'sl.Code as account_code',
                'dl.Title as equipment_title',
                'dl.Code as equipment_code',
                'vi.Debit',
                'vi.Credit',
                DB::raw('(ISNULL(vi.Debit, 0) - ISNULL(vi.Credit, 0)) as amount')
            )
            ->orderBy('v.Date', 'desc')
            ->orderBy('v.Number', 'desc')
            ->get()
            ->toArray();
    }

    public function getTotalCost(
        string $equipmentCode,
        int $typeId,
        string $fromDate,
        string $toDate
    ): float {
        if (!$this->canAccessEquipmentType($typeId)) {
            abort(403, 'شما دسترسی به این نوع تجهیز را ندارید');
        }

        return DB::connection('gtarabar')
            ->table('FIN3.Voucher as v')
            ->join('FIN3.VoucherItem as vi', 'v.VoucherID', '=', 'vi.VoucherRef')
            ->whereIn('v.State', self::CONFIRMED_VOUCHER_STATES)
            ->whereBetween('v.Date', [$fromDate, $toDate])
            ->where('vi.DLLevel5', $equipmentCode)
            ->where('vi.DLTypeRef5', $typeId)
            ->select(DB::raw('SUM(ISNULL(vi.Debit, 0) - ISNULL(vi.Credit, 0)) as total'))
            ->value('total') ?? 0.0;
    }
}
