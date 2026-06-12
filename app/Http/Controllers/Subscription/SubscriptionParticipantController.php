<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\StoreParticipantRequest;
use App\Models\Participant;
use App\Models\People;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SubscriptionParticipantController extends Controller
{
    /**
     * Create a participant subscription.
     *
     * This endpoint creates a People record and links it to a new Participant
     * for the informed edition, all inside a single database transaction.
     *
     * When a photo is provided, the file is uploaded to the configured S3 disk
     * and its path is stored in the related people record.
     *
     * Each submission creates a new People and Participant pair. Any future
     * deduplication or sanitization rules are handled outside this endpoint.
     *
     * Typical usage:
     * - Submit a participant registration form
     * - Create the participant and related personal record in a single request
     *
     * Behavior:
     * - Creates a people record with the submitted personal data
     * - Uploads the photo to S3 when present
     * - Creates the participant for the selected edition
     * - Rolls back all database changes if any step fails
     *
     * Storage path pattern:
     * - people/{people_id}/photos/{uuidv7}.{ext}
     *
     * Response structure:
     * - message: operation result message
     * - data: created participant summary (id, type, status, created_at)
     *
     * @param StoreParticipantRequest $request Validated participant subscription request.
     *
     * @return JsonResponse
     */
    public function store(StoreParticipantRequest $request): JsonResponse
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

            // Create the participant record
            $participant = Participant::create([
                'edition_id' => $request->edition_id,
                'people_id' => $people->id,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Inscrição realizada com sucesso.',
                'data' => [
                    'id' => $participant->id,
                    'type' => 'participant',
                    'status' => 'pending',
                    'created_at' => $participant->created_at->toISOString(),
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
