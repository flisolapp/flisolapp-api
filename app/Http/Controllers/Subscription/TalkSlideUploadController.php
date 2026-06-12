<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\StoreTalkSlideRequest;
use App\Models\Talk;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class TalkSlideUploadController extends Controller
{
    /**
     * Attach a slide file to an existing Talk record.
     *
     * Called as the second step of speaker registration, after the JSON
     * subscription has been created and talk IDs are known.
     *
     * Storage path pattern:
     * - talks/{talk_id}/{uuidv7}.{ext}
     *
     * @param StoreTalkSlideRequest $request
     * @param int $talkId ID of the Talk record to attach the slide to.
     * @return JsonResponse
     */
    public function store(StoreTalkSlideRequest $request, int $talkId): JsonResponse
    {
        try {
            $talk = Talk::findOrFail($talkId);

            $slide = $request->file('slide_file');
            $ext = $slide->extension();
            $path = "talks/{$talk->id}/" . Str::uuid7() . ".{$ext}";

            Storage::disk('s3')->put($path, file_get_contents($slide->getRealPath()));

            $talk->update(['slide_file' => $path]);

            return response()->json([
                'message' => 'Slide enviado com sucesso.',
                'data' => ['talk_id' => $talk->id, 'slide_file' => $path],
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Erro ao fazer upload do slide. Tente novamente.',
            ], 500);
        }
    }
}
