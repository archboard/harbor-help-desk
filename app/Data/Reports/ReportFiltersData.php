<?php

namespace App\Data\Reports;

use App\Data\InputData;
use App\Enums\ReportBasis;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Optional;

/**
 * A report's filters. `from` and `to` are whole days in the viewer's timezone.
 */
class ReportFiltersData extends InputData
{
    /**
     * @param  list<string>|Optional  $deskIds
     */
    public function __construct(
        public string|Optional $from,
        public string|Optional $to,
        public array|Optional $deskIds,
        public ReportBasis|Optional $basis,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'from' => ['sometimes', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from'],
            'desk_ids' => ['sometimes', 'array'],
            'desk_ids.*' => ['string'],
            'basis' => ['sometimes', Rule::enum(ReportBasis::class)],
        ];
    }
}
