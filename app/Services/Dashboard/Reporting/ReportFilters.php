<?php

namespace App\Services\Dashboard\Reporting;

use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Normalised report filters shared by the on-screen report and every export
 * format (DASH-13). Centralising the shape and the date-window resolution keeps
 * the downloaded file consistent with what the user sees on the page.
 *
 * Supported presets: `all`, `today`, `7d`, `30d`, `custom`.
 */
final class ReportFilters
{
    /** @var list<string> */
    public const DATE_PRESETS = ['all', 'today', '7d', '30d', 'custom'];

    public function __construct(
        public readonly string $date = 'all',
        public readonly string $startDate = '',
        public readonly string $endDate = '',
        public readonly ?int $outletId = null,
    ) {}

    /**
     * Normalise raw request/query input into a filter object. Unknown presets
     * fall back to `all`, exactly like the reports page does.
     *
     * @param  array<string, mixed>  $filters
     */
    public static function fromArray(array $filters): self
    {
        $date = isset($filters['date']) && $filters['date'] !== '' ? (string) $filters['date'] : 'all';

        if (! in_array($date, self::DATE_PRESETS, true)) {
            $date = 'all';
        }

        $outletId = null;
        if (isset($filters['outlet_id']) && $filters['outlet_id'] !== '' && $filters['outlet_id'] !== 'all') {
            $outletId = (int) $filters['outlet_id'];
        }

        return new self(
            date: $date,
            startDate: isset($filters['start_date']) ? trim((string) $filters['start_date']) : '',
            endDate: isset($filters['end_date']) ? trim((string) $filters['end_date']) : '',
            outletId: $outletId,
        );
    }

    /**
     * Base validation rules shared by the reports page and the export request.
     * Tenant ownership of `outlet_id` is enforced separately by the export
     * request so the on-screen page keeps its "ignore invalid filter" contract.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'date' => ['nullable', 'string', Rule::in(self::DATE_PRESETS)],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'outlet_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array{date: string, start_date: string, end_date: string, outlet_id: int|string}
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'outlet_id' => $this->outletId ?? '',
        ];
    }

    public function periodLabel(): string
    {
        return match ($this->date) {
            'today' => 'Hari Ini',
            '7d', '7days' => '7 Hari Terakhir',
            '30d', '30days' => '30 Hari Terakhir',
            'custom' => 'Periode Kustom',
            default => 'Semua Waktu',
        };
    }

    /**
     * Resolved (timezone-aware) date window. `all` — and a `custom` range with
     * no usable dates — resolves to an open window so no date filter is applied.
     *
     * @return array{start: Carbon|null, end: Carbon|null}
     */
    public function window(): array
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $today = Carbon::now($timezone)->startOfDay();

        return match ($this->date) {
            'today' => ['start' => $today, 'end' => $today->copy()->endOfDay()],
            '7d', '7days' => ['start' => $today->copy()->subDays(6), 'end' => $today->copy()->endOfDay()],
            '30d', '30days' => ['start' => $today->copy()->subDays(29), 'end' => $today->copy()->endOfDay()],
            'custom' => $this->customWindow($timezone),
            default => ['start' => null, 'end' => null],
        };
    }

    /**
     * Human-readable range for the report/export header. Custom ranges are
     * swapped when `start_date > end_date` (same rule as the on-screen report).
     */
    public function rangeLabel(): string
    {
        $window = $this->window();

        if ($window['start'] === null && $window['end'] === null) {
            return 'Semua Waktu';
        }

        $start = $window['start']?->translatedFormat('d M Y') ?? 'Awal';
        $end = $window['end']?->translatedFormat('d M Y') ?? 'Sekarang';

        return $start.' – '.$end;
    }

    /**
     * @return array{start: Carbon|null, end: Carbon|null}
     */
    private function customWindow(string $timezone): array
    {
        $start = $this->parseDate($this->startDate, $timezone)?->startOfDay();
        $end = $this->parseDate($this->endDate, $timezone)?->endOfDay();

        if ($start === null && $end === null) {
            return ['start' => null, 'end' => null];
        }

        // Consistent, documented rule: a reversed range is swapped, never
        // silently widened to "all time".
        if ($start !== null && $end !== null && $start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return ['start' => $start, 'end' => $end];
    }

    private function parseDate(string $value, string $timezone): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value, $timezone) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
