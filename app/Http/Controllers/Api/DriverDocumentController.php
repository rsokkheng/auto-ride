<?php

namespace App\Http\Controllers\Api;

use App\Models\DriverDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DriverDocumentController extends ApiController
{
    const REQUIRED_TYPES = [
        'id_card',
        'driver_license',
        'vehicle_registration',
        'selfie_with_id',
    ];

    /**
     * GET /v1/driver/documents
     * Driver views their own uploaded documents + completion status.
     */
    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $documents = DriverDocument::where('driver_id', $user->id)
            ->get(['id', 'type', 'status', 'admin_note', 'reviewed_at', 'file_path'])
            ->map(fn($d) => [
                'id'          => $d->id,
                'type'        => $d->type,
                'type_label'  => $this->typeLabel($d->type),
                'status'      => $d->status,
                'admin_note'  => $d->admin_note,
                'reviewed_at' => $d->reviewed_at?->toDateTimeString(),
                'file_url'    => $d->file_url,
            ]);

        $uploaded     = $documents->pluck('type')->unique()->toArray();
        $missing      = array_diff(self::REQUIRED_TYPES, $uploaded);
        $allApproved  = $documents->where('status', 'approved')->count() >= count(self::REQUIRED_TYPES);

        return $this->success([
            'approval_status' => $user->approval_status,
            'documents'       => $documents,
            'required_types'  => array_map(fn($t) => [
                'type'        => $t,
                'label'       => $this->typeLabel($t),
                'uploaded'    => in_array($t, $uploaded),
            ], self::REQUIRED_TYPES),
            'all_required_uploaded' => empty($missing),
            'all_approved'          => $allApproved,
        ]);
    }

    /**
     * POST /v1/driver/documents
     * Driver uploads a document file.
     * Body (multipart): type, file
     */
    public function upload(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver') return $this->unauthorized();

        $data = $request->validate([
            'type' => 'required|in:id_card,driver_license,vehicle_registration,vehicle_insurance,selfie_with_id,other',
            'file' => 'required|file|mimes:jpeg,jpg,png,pdf|max:5120', // 5 MB
        ]);

        // Delete previous upload of same type (replace).
        $existing = DriverDocument::where('driver_id', $user->id)
            ->where('type', $data['type'])
            ->first();

        if ($existing) {
            Storage::disk('public')->delete($existing->file_path);
            $existing->delete();
        }

        $file = $request->file('file');
        $ext  = $file->getClientOriginalExtension();
        $path = $file->storeAs(
            'driver-documents/' . $user->id,
            $data['type'] . '_' . Str::random(8) . '.' . $ext,
            'public'
        );

        $doc = DriverDocument::create([
            'driver_id' => $user->id,
            'type'      => $data['type'],
            'file_path' => $path,
            'status'    => 'pending',
        ]);

        return $this->success([
            'id'         => $doc->id,
            'type'       => $doc->type,
            'type_label' => $this->typeLabel($doc->type),
            'status'     => $doc->status,
            'file_url'   => $doc->file_url,
            'message'    => 'Document uploaded. Admin will review it shortly.',
        ], 201);
    }

    /**
     * GET /v1/admin/drivers/{driver}/documents
     * Admin views all documents for a driver.
     */
    public function adminView(Request $request, User $driver)
    {
        $admin = $this->authUser($request);
        if (! $admin || $admin->role !== 'admin') return $this->unauthorized();

        $documents = DriverDocument::where('driver_id', $driver->id)
            ->get()
            ->map(fn($d) => [
                'id'          => $d->id,
                'type'        => $d->type,
                'type_label'  => $this->typeLabel($d->type),
                'status'      => $d->status,
                'admin_note'  => $d->admin_note,
                'reviewed_at' => $d->reviewed_at?->toDateTimeString(),
                'file_url'    => $d->file_url,
            ]);

        return $this->success([
            'driver' => [
                'id'              => $driver->id,
                'name'            => $driver->name,
                'email'           => $driver->email,
                'phone'           => $driver->phone,
                'city'            => $driver->city,
                'service_zone'    => $driver->service_zone,
                'driver_type'     => $driver->driver_type,
                'approval_status' => $driver->approval_status,
                'approved_at'     => $driver->approved_at?->toDateTimeString(),
            ],
            'documents' => $documents,
        ]);
    }

    /**
     * POST /v1/admin/drivers/{driver}/documents/{document}/review
     * Admin approves or rejects a specific document.
     * Body: action (approve|reject), note?
     */
    public function adminReview(Request $request, User $driver, DriverDocument $document)
    {
        $admin = $this->authUser($request);
        if (! $admin || $admin->role !== 'admin') return $this->unauthorized();

        if ($document->driver_id !== $driver->id) {
            return response()->json(['data' => null, 'message' => 'Document does not belong to this driver.'], 422);
        }

        $data = $request->validate([
            'action' => 'required|in:approve,reject',
            'note'   => 'nullable|string|max:500',
        ]);

        $document->update([
            'status'      => $data['action'] === 'approve' ? 'approved' : 'rejected',
            'admin_note'  => $data['note'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
        ]);

        return $this->success([
            'document_id' => $document->id,
            'type'        => $document->type,
            'status'      => $document->status,
        ]);
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'id_card'              => 'National ID / Passport',
            'driver_license'       => 'Driver License',
            'vehicle_registration' => 'Vehicle Registration',
            'vehicle_insurance'    => 'Vehicle Insurance',
            'selfie_with_id'       => 'Selfie with ID',
            default                => 'Other Document',
        };
    }
}
