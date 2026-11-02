<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Attachment content-type guard shared by consumer lodging and authority
 * resolution uploads: accepts a file when its extension or its content-detected
 * MIME type is on the allow-list, and reports failures with the file's own name.
 */
class AttachmentFile implements ValidationRule
{
    /**
     * Extensions a complaint may carry.
     *
     * @var list<string>
     */
    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'mp4', 'avi', 'mov', 'webm',
    ];

    /**
     * Content-detected MIME types a complaint may carry.
     *
     * @var list<string>
     */
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'video/mp4',
        'video/x-msvideo',
        'video/quicktime',
        'video/webm',
    ];

    /**
     * Program-like detections rejected outright, even under an allowed extension.
     *
     * @var list<string>
     */
    private const BLOCKED_MIMES = [
        'application/x-dosexec',
        'application/x-executable',
        'application/x-sharedlib',
        'application/x-msdos-program',
    ];

    /**
     * Validate a single uploaded attachment, naming the failing file so the
     * portal can point the visitor straight at the offending row.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value instanceof UploadedFile === false) {
            return;
        }

        $name = $value->getClientOriginalName() ?: 'attachment';
        $detected = (string) $value->getMimeType();

        if ($value->getSize() === 0) {
            $fail("The file \"{$name}\" is empty (0 KB). Remove this row or attach the actual file.");

            return;
        }

        if (in_array($detected, self::BLOCKED_MIMES, true)) {
            $fail("The file \"{$name}\" looks like a program, so it cannot be attached. Allowed: jpg, jpeg, png, gif, pdf, doc, docx, mp4, avi, mov, webm — 20 MB each.");

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());

        // Extension allows a file whose content sniffing mislabels it (DOCX is a
        // ZIP archive and some hosts report exactly that); MIME allows a file
        // whose viewer-reported extension is unconventional.
        if (in_array($extension, self::ALLOWED_EXTENSIONS, true) || in_array($detected, self::ALLOWED_MIMES, true)) {
            return;
        }

        $fail("The file \"{$name}\" is not an allowed type (detected as \"{$detected}\"). Allowed: jpg, jpeg, png, gif, pdf, doc, docx, mp4, avi, mov, webm — 20 MB each.");
    }
}
