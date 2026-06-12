<?php

namespace App\Http\Controllers\Certified;

use App\Helpers\CertificateHelper;
use App\Helpers\ColorHelper;
use App\Helpers\StorageCacheHelper;
use App\Helpers\StringHelper;
use App\Http\Controllers\Controller;
use App\Models\PeopleCertificate;
use DateTime;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificatesDownloadController extends Controller
{
    /**
     * Generate and download a certificate image as PNG.
     *
     * Validates the certificate code, resolves the correct template for the
     * certificate type, renders the participant name and optional metadata on
     * top of the template, caches the generated PNG, updates the last-view
     * timestamp, and streams the final file.
     *
     * When a previously generated PNG already exists in cache, the cached
     * file is streamed directly instead of re-rendering.
     *
     * GET /api/certified/{code}/download
     *
     * Possible responses:
     * - 200: Certificate PNG streamed successfully
     * - 404: Certificate, edition, font, or template not found
     * - 500: Certificate image generation failed
     *
     * @param string $code Unique public certificate verification code.
     */
    public function execute(string $code): StreamedResponse|JsonResponse
    {
        $this->removeMemoryAndTimeLimits();

        $certificate = PeopleCertificate::with(['edition', 'talk'])
            ->where('code', $code)
            ->whereNull('removed_at')
            ->first();

        if (!$certificate || !$certificate->edition) {
            return response()->json(['found' => false], 404);
        }

        $edition = $certificate->edition;
        $certificateOptions = $edition->options->certificate ?? null;
        $editionId = $edition->id;
        $name = $certificate->name;

        // QR code verification URL — intentionally hardcoded to the public
        // certified front-end. Do not change: existing certificates already
        // embed this URL in the QR code printed on the PNG.
        $codeVerificationUrl = 'https://certified.flisol.app/' . $certificate->code;

        // Serve cached PNG when available
        $cachedCertificate = StorageCacheHelper::get("certificates/{$editionId}/{$certificate->code}.png");

        if ($cachedCertificate && file_exists($cachedCertificate)) {
            return Response::streamDownload(function () use ($cachedCertificate) {
                readfile($cachedCertificate);
            }, 'certificate_' . $certificate->code . '.png', [
                'Content-Type' => 'image/png',
            ]);
        }

        // Load font
        $fontFileName = $certificateOptions->font ?? 'NunitoSans-Bold.ttf';
        $fontKey = "editions/{$editionId}/{$fontFileName}";
        $font = StorageCacheHelper::get($fontKey);

        if (!$font || !file_exists($font)) {
            return response()->json(['found' => false, 'error' => 'Font not found'], 404);
        }

        // Resolve template and text colour
        [$certificateFile, $colorHex] = $this->resolveCertificateTemplate(
            $certificate, $certificateOptions, $editionId
        );

        if (!$certificateFile || !file_exists($certificateFile)) {
            return response()->json(['found' => false, 'error' => 'Template not found'], 404);
        }

        $image = @imagecreatefrompng($certificateFile);

        // Render name — layout differs for editions <= 21 (legacy template)
        if ($editionId <= 21) {
            $this->renderNameLegacy($image, $name, $colorHex, $font);
            $this->renderTalkTitleLegacy($image, $certificate->talk, $font);
        } else {
            $this->renderName($image, $name, $colorHex, $font);
            $this->renderTalkTitle($image, $certificate->talk, $font);
        }

        // Render CPF when available
        if ($certificate->federal_code) {
            CertificateHelper::addFederalCode($image, 'CPF', $certificate->federal_code, 12, 23);
        }

        CertificateHelper::addCodeVerificationUrl($image, $codeVerificationUrl, 1586, 0);
        CertificateHelper::addQrCode($image, $codeVerificationUrl, 1280, 780);

        $data = CertificateHelper::getData($image);

        if ($data === null) {
            return response()->json(['found' => false, 'error' => 'Image generation failed'], 500);
        }

        $certificate->last_view_at = DateTimeImmutable::createFromMutable(new DateTime());
        $certificate->save();

        StorageCacheHelper::save("certificates/{$editionId}/{$certificate->code}.png", $data);

        return Response::streamDownload(function () use ($data) {
            echo $data;
        }, 'certificate_' . $certificate->code . '.png', [
            'Content-Type' => 'image/png',
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Remove PHP execution time and memory limits.
     * Required because certificate generation involves large PNG files,
     * custom fonts, QR code generation and GD image manipulation.
     */
    private function removeMemoryAndTimeLimits(): void
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);
    }

    /**
     * Resolve the certificate template file and text colour.
     *
     * Priority: Organizer > Collaborator > Talk/Speaker > Participant
     *
     * @return array{0: string|null, 1: string}
     */
    private function resolveCertificateTemplate(
        PeopleCertificate $certificate,
        ?object           $certificateOptions,
        int|string        $editionId
    ): array
    {
        $defaultColor = '#FE8200';

        $types = [
            'organizer' => 'organizer',
            'collaborator' => 'collaborator',
            'talk' => 'speaker',
            'participant' => 'participant',
        ];

        foreach ($types as $property => $optionKey) {
            if ($certificate->$property && isset($certificateOptions->$optionKey)) {
                $option = $certificateOptions->$optionKey;
                $fileKey = "editions/{$editionId}/{$option->file}";
                $file = StorageCacheHelper::get($fileKey);

                return [$file, $option->color];
            }
        }

        return [null, $defaultColor];
    }

    /** Render name on editions > 21 (current template). */
    private function renderName($image, ?string $name, string $colorHex, string $font): void
    {
        if (!$name) {
            return;
        }

        [$firstLine, $secondLine] = StringHelper::splitTextBySpace($name, 23);

        $shadow = imagecolorallocate($image, 240, 240, 240);
        imagefttext($image, 70, 0, 149, 489, $shadow, $font, $firstLine);

        if ($secondLine) {
            imagefttext($image, 70, 0, 149, 574, $shadow, $font, $secondLine);
        }

        $rgb = ColorHelper::hexToRgb($colorHex);
        $nameColor = imagecolorallocate($image, $rgb->red, $rgb->green, $rgb->blue);
        imagefttext($image, 70, 0, 148, 488, $nameColor, $font, $firstLine);

        if ($secondLine) {
            imagefttext($image, 70, 0, 148, 573, $nameColor, $font, $secondLine);
        }
    }

    /** Render name on editions <= 21 (legacy template). */
    private function renderNameLegacy($image, ?string $name, string $colorHex, string $font): void
    {
        if (!$name) {
            return;
        }

        [$firstLine, $secondLine] = StringHelper::splitTextBySpace($name, 23);

        $shadow = imagecolorallocate($image, 240, 240, 240);
        imagefttext($image, 60, 0, 109, 471, $shadow, $font, $firstLine);

        if ($secondLine) {
            imagefttext($image, 60, 0, 109, 551, $shadow, $font, $secondLine);
        }

        $rgb = ColorHelper::hexToRgb($colorHex);
        $nameColor = imagecolorallocate($image, $rgb->red, $rgb->green, $rgb->blue);
        imagefttext($image, 60, 0, 108, 470, $nameColor, $font, $firstLine);

        if ($secondLine) {
            imagefttext($image, 60, 0, 108, 550, $nameColor, $font, $secondLine);
        }
    }

    /** Render talk title on editions > 21. */
    private function renderTalkTitle($image, $talk, string $font): void
    {
        if (!$talk) {
            return;
        }

        $titleColor = imagecolorallocate($image, 74, 79, 82);
        imagefttext($image, 18, 0, 154, 635, $titleColor, $font, $talk->title);
    }

    /** Render talk title on editions <= 21 (legacy). */
    private function renderTalkTitleLegacy($image, $talk, string $font): void
    {
        if (!$talk) {
            return;
        }

        $titleColor = imagecolorallocate($image, 74, 79, 82);
        imagefttext($image, 18, 0, 114, 620, $titleColor, $font, $talk->title);
    }
}
