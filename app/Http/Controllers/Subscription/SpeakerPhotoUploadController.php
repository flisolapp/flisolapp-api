<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\StoreSpeakerPhotoRequest;
use App\Models\People;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SpeakerPhotoUploadController extends Controller
{
    /**
     * Attach a photo to an existing People (speaker) record.
     *
     * Called as the second step of speaker registration, after the JSON
     * subscription has been created and speaker IDs are known.
     *
     * Storage path pattern:
     * - people/{people_id}/photos/{uuidv7}.{ext}
     *
     * @param StoreSpeakerPhotoRequest $request
     * @param int $speakerId ID of the People record to attach the photo to.
     * @return JsonResponse
     */
    public function store(StoreSpeakerPhotoRequest $request, int $speakerId): JsonResponse
    {
        try {
            $people = People::findOrFail($speakerId);

            $photo = $request->file('photo');
            $ext = $photo->extension();
            $path = "people/{$people->id}/photos/" . Str::uuid7() . ".{$ext}";

            Storage::disk('s3')->put($path, file_get_contents($photo->getRealPath()));

            $people->update(['photo' => $path]);

            return response()->json([
                'message' => 'Foto enviada com sucesso.',
                'data' => ['people_id' => $people->id, 'photo' => $path],
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Erro ao fazer upload da foto. Tente novamente.',
            ], 500);
        }
    }
}
