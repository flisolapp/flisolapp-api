<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\People;
use App\Models\Talk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TalkController extends Controller
{
    /**
     * GET /api/talks
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'edition_id' => ['nullable', 'integer', 'exists:editions,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'kind' => ['nullable', 'string', 'in:T,W'],  // T=Talk, W=Workshop
            'shift' => ['nullable', 'string', 'in:M,A,W'],
            'approved' => ['nullable', 'boolean'],
            'presented' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', 'string', 'in:id,title,kind,shift,approved,presented,created_at,updated_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $perPage = $data['per_page'] ?? 15;
        $search = trim($data['search'] ?? '');
        $sortBy = $data['sort_by'] ?? 'id';
        $sortDirection = strtolower($data['sort_direction'] ?? 'desc');

        // speaker_talks.speaker_id references people.id directly — no intermediate speakers table.
        $talks = Talk::query()
            ->select('talks.*')
            ->with(['speakers', 'talkSubject'])
            ->leftJoin('speaker_talks', function ($join) {
                $join->on('speaker_talks.talk_id', '=', 'talks.id')
                    ->whereNull('speaker_talks.removed_at');
            })
            ->leftJoin('people', 'people.id', '=', 'speaker_talks.speaker_id')
            ->whereNull('talks.removed_at')
            ->when(isset($data['edition_id']), fn($q) => $q->where('talks.edition_id', $data['edition_id'])
            )
            ->when(isset($data['kind']), fn($q) => $q->where('talks.kind', $data['kind'])
            )
            ->when(isset($data['shift']), fn($q) => $q->where('talks.shift', $data['shift'])
            )
            ->when(isset($data['approved']), fn($q) => $data['approved']
                ? $q->whereNotNull('talks.approved_at')
                : $q->whereNull('talks.approved_at')
            )
            ->when(isset($data['presented']), fn($q) => $data['presented']
                ? $q->whereNotNull('talks.presented_at')
                : $q->whereNull('talks.presented_at')
            )
            ->when($search !== '', fn($q) => $q->where(fn($sub) => $sub->where('talks.title', 'like', '%' . $search . '%')
                ->orWhere('talks.description', 'like', '%' . $search . '%')
                ->orWhere('people.name', 'like', '%' . $search . '%')
                ->orWhere('people.email', 'like', '%' . $search . '%')
                ->orWhere('people.federal_code', 'like', '%' . $search . '%')
                ->orWhere('people.phone', 'like', '%' . $search . '%')
            )
            )
            ->distinct();

        switch ($sortBy) {
            case 'title':
                $talks->orderBy('talks.title', $sortDirection);
                break;

            case 'kind':
                $talks->orderBy('talks.kind', $sortDirection);
                break;

            case 'shift':
                $talks->orderBy('talks.shift', $sortDirection);
                break;

            case 'approved':
                // approved_at is a datetime — sort by presence, matching the presented_at pattern
                $talks->orderByRaw(
                    'CASE WHEN talks.approved_at IS NULL THEN 0 ELSE 1 END ' . $sortDirection
                );
                break;

            case 'presented':
                // presented_at is a datetime — sort by presence, matching the presented_at pattern
                $talks->orderByRaw(
                    'CASE WHEN talks.presented_at IS NULL THEN 0 ELSE 1 END ' . $sortDirection
                );
                break;

            case 'created_at':
                $talks->orderBy('talks.created_at', $sortDirection);
                break;

            case 'updated_at':
                $talks->orderBy('talks.updated_at', $sortDirection);
                break;

            case 'id':
            default:
                $talks->orderBy('talks.id', $sortDirection);
                break;
        }

        $talks = $talks
            ->paginate($perPage)
            ->through(fn(Talk $talk) => $this->format($talk));

        return response()->json($talks);
    }

    /**
     * GET /api/talks/{talk}
     */
    public function show(Talk $talk): JsonResponse
    {
        if ($talk->removed_at !== null) {
            return response()->json(['message' => 'Talk not found'], 404);
        }

        $talk->load(['speakers', 'talkSubject']);

        return response()->json($this->format($talk));
    }

    /**
     * POST /api/talks
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'shift' => ['required', 'in:M,A,W'],
            'kind' => ['required', 'in:T,W'],  // T=Talk, W=Workshop
            'talk_subject_id' => ['nullable', 'exists:talk_subjects,id'],
            'slide_url' => ['nullable', 'url', 'max:500'],
            'slide' => ['nullable', 'file', 'mimes:pdf,odp,ppt,pptx', 'max:20480'],
            'speaker_ids' => ['nullable', 'array'],
            'speaker_ids.*' => ['exists:people,id'],
        ]);

        $talk = DB::transaction(function () use ($data, $request) {
            $talk = Talk::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'shift' => $data['shift'],
                'kind' => $data['kind'],
                'edition_id' => $data['edition_id'],
                'talk_subject_id' => $data['talk_subject_id'] ?? null,
                'slide_url' => $data['slide_url'] ?? null,
            ]);

            if (!empty($data['speaker_ids'])) {
                $talk->speakers()->sync($data['speaker_ids']);
            }

            if ($request->hasFile('slide')) {
                $path = $request->file('slide')->store(
                    "talks/{$talk->id}/slide", 's3'
                );
                $talk->update(['slide_file' => $path]);
            }

            return $talk;
        });

        $talk->load(['speakers', 'talkSubject']);

        return response()->json($this->format($talk), 201);
    }

    /**
     * PUT/PATCH /api/talks/{talk}
     */
    public function update(Request $request, Talk $talk): JsonResponse
    {
        if ($talk->removed_at !== null) {
            return response()->json(['message' => 'Talk not found'], 404);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'shift' => ['sometimes', 'in:M,A,W'],
            'kind' => ['sometimes', 'in:T,W'],  // T=Talk, W=Workshop
            'talk_subject_id' => ['nullable', 'exists:talk_subjects,id'],
            'slide_url' => ['nullable', 'url', 'max:500'],
            'slide' => ['nullable', 'file', 'mimes:pdf,odp,ppt,pptx', 'max:20480'],
            'speaker_ids' => ['nullable', 'array'],
            'speaker_ids.*' => ['exists:people,id'],
        ]);

        DB::transaction(function () use ($data, $request, $talk) {
            $talkData = [];

            foreach (['title', 'description', 'shift', 'kind', 'talk_subject_id', 'slide_url'] as $field) {
                if (array_key_exists($field, $data)) {
                    $talkData[$field] = $data[$field];
                }
            }

            if (!empty($talkData)) {
                $talk->update($talkData);
            }

            if (isset($data['speaker_ids'])) {
                $talk->speakers()->sync($data['speaker_ids']);
            }

            if ($request->hasFile('slide')) {
                if ($talk->slide_file) {
                    Storage::disk('s3')->delete($talk->slide_file);
                }
                $path = $request->file('slide')->store(
                    "talks/{$talk->id}/slide", 's3'
                );
                $talk->update(['slide_file' => $path]);
            }
        });

        $talk->refresh();
        $talk->load(['speakers', 'talkSubject']);

        return response()->json($this->format($talk));
    }

    /**
     * PATCH /api/talks/{talk}/approve
     */
    public function approve(Request $request, Talk $talk): JsonResponse
    {
        if ($talk->removed_at !== null) {
            return response()->json(['message' => 'Talk not found'], 404);
        }

        $data = $request->validate([
            'approved' => ['required', 'boolean'],
        ]);

        // approved_at is not in $fillable — set directly to bypass mass-assignment guard
        $talk->approved_at = $data['approved'] ? now() : null;
        $talk->save();

        $talk->load(['speakers', 'talkSubject']);

        return response()->json($this->format($talk));
    }

//    /**
//     * PATCH /api/talks/{talk}/confirm
//     */
//    public function confirm(Request $request, Talk $talk): JsonResponse
//    {
//        if ($talk->removed_at !== null) {
//            return response()->json(['message' => 'Talk not found'], 404);
//        }
//
//        $data = $request->validate([
//            'presented' => ['required', 'boolean'],
//        ]);
//
//        // presented_at is not in $fillable — set directly to bypass mass-assignment guard
//        $talk->presented_at = $data['presented'] ? now() : null;
//        $talk->save();
//
//        $talk->load(['speakers', 'talkSubject']);
//
//        return response()->json($this->format($talk));
//    }

    /**
     * DELETE /api/talks/{talk}
     */
    public function destroy(Talk $talk): JsonResponse
    {
        if ($talk->removed_at === null) {
            $talk->update(['removed_at' => now()]);
        }

        return response()->json(null, 204);
    }


    /**
     * GET /api/talks/{talk}/slide
     *
     * Proxies the slide file through the server — same reason as speakerPhoto:
     * the S3 bucket policy blocks direct browser access by IP.
     */
    public function slide(Talk $talk): StreamedResponse
    {
        if ($talk->removed_at !== null) {
            abort(404);
        }

        if (!$talk->slide_file) {
            abort(404);
        }

        $disk = Storage::disk('s3');

        return response()->stream(
            function () use ($disk, $talk): void {
                $stream = $disk->readStream($talk->slide_file);
                fpassthru($stream);
                fclose($stream);
            },
            200,
            [
                'Content-Type' => $this->mimeFromPath($talk->slide_file),
                'Content-Disposition' => 'inline; filename="' . basename($talk->slide_file) . '"',
                'Cache-Control' => 'public, max-age=86400',
            ]
        );
    }

    /**
     * GET /api/talks/speaker-photo/{person}
     *
     * Proxies the speaker photo through the server so the browser never hits
     * S3 directly — required when the bucket policy restricts access by IP.
     */
    public function speakerPhoto(People $person): StreamedResponse
    {
        if (!$person->photo) {
            abort(404);
        }

        $disk = Storage::disk('s3');

        return response()->stream(
            function () use ($disk, $person): void {
                $stream = $disk->readStream($person->photo);
                fpassthru($stream);
                fclose($stream);
            },
            200,
            [
                'Content-Type' => $this->mimeFromPath($person->photo),
                'Cache-Control' => 'public, max-age=86400',
            ]
        );
    }

    /**
     * Format API response
     */
    private function format(Talk $t): array
    {
        return [
            'id' => $t->id,
            'edition_id' => $t->edition_id,
            'title' => $t->title,
            'description' => $t->description,
            'shift' => $t->shift,
            'kind' => $t->kind,
            'approved' => $t->approved_at !== null,
            'approved_at' => $t->approved_at,
            'presented' => $t->presented_at !== null,
            'presented_at' => $t->presented_at,
            'talk_subject_id' => $t->talk_subject_id,
            'talk_subject_name' => $t->talkSubject?->name,
            'slide_file_url' => $t->slide_file
                ? route("records.talks.slide", $t->id) // Storage::disk('s3')->url($t->slide_file)
                : null,
            'slide_url' => $t->slide_url,
            'created_at' => $t->created_at,
            'updated_at' => $t->updated_at,
            'removed_at' => $t->removed_at,
            'speakers' => $t->speakers->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'federal_code' => $s->federal_code,
                'email' => $s->email,
                'phone' => $s->phone,
                'bio' => $s->bio,
                'website' => $s->site,
                'photo_url' => $s->photo
                    ? route("records.talks.speaker-photo", $s->id) // Storage::disk('s3')->url($s->photo)
                    : null,
            ])->toArray(),
        ];
    }

    private function mimeFromPath(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'odp' => 'application/vnd.oasis.opendocument.presentation',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            default => 'application/octet-stream',
        };
    }
}
