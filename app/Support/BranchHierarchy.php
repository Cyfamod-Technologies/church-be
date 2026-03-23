<?php

namespace App\Support;

use App\Models\Branch;

class BranchHierarchy
{
    /**
     * @return array<int, int>
     */
    public static function descendantIdsInclusive(int $branchId): array
    {
        $resolvedIds = [$branchId];
        $pendingParentIds = [$branchId];

        while ($pendingParentIds !== []) {
            $childIds = Branch::query()
                ->whereIn('current_parent_branch_id', $pendingParentIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $childIds = array_values(array_diff($childIds, $resolvedIds));

            if ($childIds === []) {
                break;
            }

            $resolvedIds = [...$resolvedIds, ...$childIds];
            $pendingParentIds = $childIds;
        }

        return $resolvedIds;
    }
}
