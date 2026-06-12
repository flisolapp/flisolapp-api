<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\StoreSpeakerRequest;
use App\Models\People;
use App\Models\SpeakerTalk;
use App\Models\Talk;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SubscriptionSpeakerController extends Controller
{
    /**
     * Create a speaker subscription.
     *
     * This endpoint creates one People record for each submitted speaker and
     * one Talk record for each submitted talk, then links every speaker to
     * every created talk through the SpeakerTalk pivot table, all inside a
     * single database transaction.
     *
     * Uploaded speaker photos and talk slide files are stored on the configured
     * S3 disk when present, and their generated paths are saved in the related
     * records after creation.
     *
     * Typical usage:
     * - Submit a speaker registration form with one or more speakers
     * - Submit one or more talks in the same request
     * - Link all submitted speakers to all submitted talks
     *
     * Behavior:
     * - Creates one people record per submitted speaker
     * - Uploads each speaker photo to S3 when present
     * - Creates one talk record per submitted talk
     * - Uploads each talk slide file to S3 when present
     * - Creates pivot links between every speaker and every talk
     * - Rolls back all database changes if any step fails
     *
     * Expected payload format:
     * - edition_id
     * - speakers[]: array of speaker objects
     * - talks[]: array of talk objects
     *
     * Storage path patterns:
     * - Photo: people/{people_id}/photos/{uuidv7}.{ext}
     * - Slide: talks/{talk_id}/{uuidv7}.{ext}
     *
     * Response structure:
     * - message: operation result message
     * - data: created subscription summary
     *   - type: subscription type
     *   - status: current processing status
     *   - created_at: request completion timestamp
     *   - speakers: array of created speaker ids
     *   - talks: array of created talk ids
     *
     * @param StoreSpeakerRequest $request Validated speaker subscription request.
     *
     * @return JsonResponse
     */
    public function store(StoreSpeakerRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $speakersData = $request->input('speakers', []);
            $talksData = $request->input('talks', []);

            // Uploaded files are handled separately from request input
            $speakerFiles = $request->file('speakers', []);
            $talkFiles = $request->file('talks', []);

            // Create one people record per speaker
            $peopleIds = [];

            foreach ($speakersData as $i => $speaker) {
                $people = People::create([
                    'name' => $speaker['name'],
                    'email' => $speaker['email'],
                    'federal_code' => $speaker['federal_code'],
                    'phone' => $speaker['phone'],
                    'bio' => $speaker['bio'] ?? null,
                    'site' => $speaker['site'] ?? null,
                    'use_free' => false,
                ]);

                // Upload speaker photo to S3 when present
                $photo = $speakerFiles[$i]['photo'] ?? null;

                if ($photo) {
                    $ext = $photo->extension();
                    $path = "people/{$people->id}/photos/" . Str::uuid7() . ".{$ext}";

                    Storage::disk('s3')->put($path, file_get_contents($photo->getRealPath()));

                    $people->update(['photo' => $path]);
                }

                $peopleIds[] = $people->id;
            }

            // Create one talk record per submitted talk
            $talkIds = [];

            foreach ($talksData as $i => $talkData) {
                $talk = Talk::create([
                    'edition_id' => $request->input('edition_id'),
                    'title' => $talkData['title'],
                    'description' => $talkData['description'],
                    'shift' => $talkData['shift'],
                    'kind' => $talkData['kind'],
                    'talk_subject_id' => $talkData['talk_subject_id'] ?? null,
                    'slide_url' => $talkData['slide_url'] ?? null,
                ]);

                // Upload talk slide file to S3 when present
                $slide = $talkFiles[$i]['slide_file'] ?? null;

                if ($slide) {
                    $ext = $slide->extension();
                    $path = "talks/{$talk->id}/" . Str::uuid7() . ".{$ext}";

                    Storage::disk('s3')->put($path, file_get_contents($slide->getRealPath()));

                    $talk->update(['slide_file' => $path]);
                }

                $talkIds[] = $talk->id;
            }

            // Link every created speaker to every created talk
            foreach ($peopleIds as $personId) {
                foreach ($talkIds as $talkId) {
                    SpeakerTalk::create([
                        'speaker_id' => $personId,
                        'talk_id' => $talkId,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Inscrição realizada com sucesso.',
                'data' => [
                    'type' => 'speaker',
                    'status' => 'pending',
                    'created_at' => now()->toISOString(),
                    'speakers' => collect($peopleIds)->map(fn($id) => ['id' => $id])->values(),
                    'talks' => collect($talkIds)->map(fn($id) => ['id' => $id])->values(),
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
