<?php

namespace App\Services;

use App\Exceptions\JsonApiException;
use App\Models\Budget;
use App\Models\BudgetVersion;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;
use Throwable;

class BudgetVersionService
{
    protected DatabaseManager $db;

    public function __construct(DatabaseManager $db)
    {
        $this->db = $db;
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws Throwable
     */
    public function createAndReplaceVersion(
        Budget $budget,
        array $attributes = []
    ): BudgetVersion {
        if ($budget->budgetVersions->count()) {
            // Fill missing attributes, for example in case of a PATCH request
            $attributes = array_merge(
                Arr::except($budget->activeVersion()->attributesToArray(), ['effective_from', 'effective_to']),
                $attributes,
            );
        }

        if (isset($attributes['effective_from'])) {
            $effectiveFrom = Carbon::parse($attributes['effective_from']);
        } else {
            assert(! is_null($budget->started_at));
            $effectiveFrom = $budget->started_at->copy();
        }

        $this->validateEffectiveFromOrFail($budget, $effectiveFrom);

        $version = $this->create($attributes, $budget->id, $effectiveFrom->setTimezone('UTC'));

        $this->invalidateOverlappingVersions($budget, $version);

        $budget->load('budgetVersions');
        $budget->unsetRelation('periods');

        return $version;
    }

    protected function validateEffectiveFromOrFail(Budget $budget, Carbon $effectiveFrom): void
    {
        assert(! is_null($budget->started_at));

        $budgetStartedAt = $budget->started_at->setTimezone('UTC');
        $effectiveFrom->setTimezone('UTC');

        // Validate for no renewal frequency
        if (! $budget->renewal_frequency) {
            if ($budgetStartedAt->isSameDay($effectiveFrom)) {
                return;
            }

            throw new JsonApiException(422, [
                [
                    'status' => '422',
                    'detail' => 'effectiveFrom must be same day as startedAt if no renewalFrequency',
                ],
            ]);
        }

        if ($budget->wasRecentlyCreated) {
            if ($budgetStartedAt->isSameDay($effectiveFrom)) {
                return;
            }

            throw new JsonApiException(422, [
                [
                    'status' => '422',
                    'detail' => 'effectiveFrom must be the same day as startedAt when it\'s a new budget',
                ],
            ]);
        }

        foreach ($budget->periods() as $period) {
            if ($period->getStartDate()->setTimezone('UTC')->isSameDay($effectiveFrom)) {
                return; // Successfully validated
            }
        }

        throw new JsonApiException(422, [
            [
                'status' => '422',
                'detail' => 'effectiveFrom must be at the start of a period',
            ],
        ]);
    }

    /**
     * @throws Exception
     */
    protected function invalidateOverlappingVersions(Budget $budget, BudgetVersion $newVersion): void
    {
        foreach ($budget->budgetVersions as $version) {
            if ($version->id === $newVersion->id) {
                continue;
            }

            if ($version->isObsoleteComparedTo($newVersion)) {
                $version->delete();

                continue;
            }

            assert(! is_null($newVersion->effective_from));

            if ($version->overlapsWith($newVersion)) {
                $version->effective_to = $newVersion->effective_from->copy()->subMinute();
                $version->save();
            }
        }

        $budget->load('budgetVersions');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function create(
        array $attributes,
        int $budgetId,
        ?Carbon $effectiveFrom = null
    ): BudgetVersion {
        /** @var BudgetVersion $version */
        $version = BudgetVersion::query()->create(array_merge(
            $attributes,
            [
                'budget_id' => $budgetId,
                'effective_from' => $effectiveFrom,
            ]
        ));

        return $version;
    }
}
