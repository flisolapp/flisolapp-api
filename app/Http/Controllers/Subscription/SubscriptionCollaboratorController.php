<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\StoreCollaboratorRequest;
use App\Models\Collaborator;
use App\Models\CollaboratorArea;
use App\Models\CollaboratorAvailability;
use App\Models\People;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SubscriptionCollaboratorController extends Controller
{
    /**
     * Create a collaborator subscription.
     *
     * This endpoint creates a People record, a Collaborator record, and the
     * related collaboration areas and availability shifts, all inside a single
     * database transaction.
     *
     * When a photo is provided, the file is uploaded to the configured S3 disk
     * and its path is stored in the related people record.
     *
     * Typical usage:
     * - Submit a collaborator registration form
     * - Create the collaborator and all related selections in a single request
     *
     * Behavior:
     * - Creates a people record with the submitted personal data
     * - Uploads the photo to S3 when present
     * - Creates the collaborator for the selected edition
     * - Creates related area and availability rows
     * - Rolls back all database changes if any step fails
     *
     * Storage path pattern:
     * - people/{people_id}/photos/{uuidv7}.{ext}
     *
     * Response structure:
     * - message: operation result message
     * - data: created collaborator summary (id, type, status, created_at)
     *
     * @param StoreCollaboratorRequest $request Validated collaborator subscription request.
     *
     * @return JsonResponse
     */
    public function store(StoreCollaboratorRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            // Create the people record
            $people = People::create([
                'name' => $request->name,
                'email' => $request->email,
                'federal_code' => $request->federal_code,
                'phone' => $request->phone,
                'bio' => $request->bio,
                'site' => $request->site,
                'use_free' => $request->boolean('use_free', false),
                'distro_id' => $request->distro_id,
                'student_info_id' => $request->student_info_id,
                'student_place' => $request->student_place,
                'student_course' => $request->student_course,
                'address_state' => $request->address_state,
            ]);

            // Upload photo to S3 when present
            if ($request->hasFile('photo')) {
                $photo = $request->file('photo');
                $ext = $photo->extension();
                $path = "people/{$people->id}/photos/" . Str::uuid7() . ".{$ext}";

                Storage::disk('s3')->put($path, file_get_contents($photo->getRealPath()));

                $people->update(['photo' => $path]);
            }

            // Create the collaborator record
            $collaborator = Collaborator::create([
                'edition_id' => $request->edition_id,
                'people_id' => $people->id,
            ]);

            // Create collaboration area links
            $areaRows = array_map(
                fn(int $areaId) => [
                    'collaborator_id' => $collaborator->id,
                    'collaboration_area_id' => $areaId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                $request->areas,
            );

            CollaboratorArea::insert($areaRows);

            // Create availability shift links
            $availabilityRows = array_map(
                fn(int $shiftId) => [
                    'collaborator_id' => $collaborator->id,
                    'collaborator_shift_id' => $shiftId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                $request->availabilities,
            );

            CollaboratorAvailability::insert($availabilityRows);

            DB::commit();

            return response()->json([
                'message' => 'Inscrição realizada com sucesso.',
                'data' => [
                    'id' => $collaborator->id,
                    'type' => 'collaborator',
                    'status' => 'pending',
                    'created_at' => $collaborator->created_at->toISOString(),
                ],
            ], 201);
        } catch (Throwable $e) {
            DB::rollBack();

            report($e);

            return response()->json([
                'message' => 'Erro interno ao processar a inscrição. Tente novamente.',
            ], 500);
        }
    }
}
