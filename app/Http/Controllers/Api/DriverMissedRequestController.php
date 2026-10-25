<?php

namespace App\Http\Controllers\Api;

use App\Models\CarRental;
use App\Models\Delivery;
use App\Models\DriverMissedRequest;
use App\Models\Ride;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * A driver's "missed calls" — requests offered to them that they declined or
 * let time out on the request card.
 */
class DriverMissedRequestController extends ApiController
{
    /**
     * POST /driver/missed-requests
     * { "request_type": "ride|delivery|rental", "request_id": 12, "reason": "declined|missed" }
     *
     * Called by the driver app when a request card is declined or expires.
     * Idempotent per driver + request: a repeat offer just refreshes the row.
     */
    public function store(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $data = $request->validate([
            'request_type' => 'required|in:' . implode(',', DriverMissedRequest::TYPES),
            'request_id'   => 'required|integer|min:1',
            'reason'       => 'required|in:' . implode(',', DriverMissedRequest::REASONS),
        ]);

        $model = $this->findRequest($data['request_type'], (int) $data['request_id']);
        if (! $model) {
            return response()->json(['message' => 'Request not found.'], 404);
        }

        $row = DriverMissedRequest::updateOrCreate(
            [
                'driver_id'    => $user->id,
                'request_type' => $data['request_type'],
                'request_id'   => $model->getKey(),
            ],
            [
                'reason'  => $data['reason'],
                'summary' => $this->summarize($data['request_type'], $model),
            ],
        );
        $row->touch();

        return $this->success(['missed_request' => $this->present($row, $model, $user->id)], 201);
    }

    /**
     * GET /driver/missed-requests
     * Latest 100, newest first, each with what happened to the request since
     * (`outcome`: open | taken | cancelled).
     */
    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $rows = DriverMissedRequest::where('driver_id', $user->id)
            ->latest('updated_at')
            ->limit(100)
            ->get();

        // One query per request type for the current status.
        $current = [];
        foreach ($rows->groupBy('request_type') as $type => $group) {
            $class = $this->modelClass($type);
            $current[$type] = $class::whereIn('id', $group->pluck('request_id'))->get()->keyBy('id');
        }

        return $this->success([
            'missed_requests' => $rows->map(fn (DriverMissedRequest $r) => $this->present(
                $r, $current[$r->request_type][$r->request_id] ?? null, $user->id,
            ))->values(),
        ]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** @return class-string<Model> */
    private function modelClass(string $type): string
    {
        return match ($type) {
            'delivery' => Delivery::class,
            'rental'   => CarRental::class,
            default    => Ride::class,
        };
    }

    private function findRequest(string $type, int $id): ?Model
    {
        return $this->modelClass($type)::find($id);
    }

    /** Snapshot shown in the list even if the request changes or is deleted later. */
    private function summarize(string $type, Model $m): array
    {
        return match ($type) {
            'delivery' => [
                'pickup_address'  => $m->pickup_address,
                'dropoff_address' => $m->dropoff_address,
                'fare_khr'        => (int) round((float) $m->fee),
                'customer_name'   => $m->sender_name ?: $m->sender?->name,
                'detail'          => $m->package_size,
            ],
            'rental' => [
                'pickup_address'  => $m->pickup_location,
                'dropoff_address' => null,
                'fare_khr'        => (int) round((float) $m->total_amount_khr),
                'customer_name'   => $m->guest_name ?: $m->user?->name,
                'detail'          => trim(($m->start_date?->toDateString() ?? '') . ' → ' . ($m->end_date?->toDateString() ?? ''), ' →'),
            ],
            default => [
                'pickup_address'  => $m->pickup_address,
                'dropoff_address' => $m->dropoff_address,
                'fare_khr'        => (int) round((float) $m->fare),
                'customer_name'   => $m->passenger?->name,
                'detail'          => $m->service_type,
            ],
        };
    }

    /** open = still waiting for a driver, taken = another driver has it, cancelled. */
    private function outcome(?Model $m, int $driverId): string
    {
        if (! $m) return 'cancelled';

        $status = (string) $m->status;
        if (in_array($status, ['cancelled', 'canceled', 'rejected', 'expired'], true)) {
            return 'cancelled';
        }
        $assigned = $m->driver_id ?? null;
        if ($assigned && (int) $assigned !== $driverId) return 'taken';
        if (in_array($status, ['requested', 'pending', 'searching'], true)) return 'open';

        return 'taken';
    }

    private function present(DriverMissedRequest $r, ?Model $current, int $driverId): array
    {
        return [
            'id'           => $r->id,
            'request_type' => $r->request_type,
            'request_id'   => $r->request_id,
            'reason'       => $r->reason,
            'outcome'      => $this->outcome($current, $driverId),
            'summary'      => $r->summary ?? [],
            'at'           => $r->updated_at?->toIso8601String(),
        ];
    }
}
