<?php

namespace App\Data\Workspaces;

use App\Data\InputData;
use Illuminate\Http\UploadedFile;

class ImportWorkspaceData extends InputData
{
    /**
     * The largest import zip in kilobytes (2 GB). PHP's upload limits still apply.
     */
    public const int MAX_KB = 2097152;

    public function __construct(
        public UploadedFile $file,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:zip', 'max:'.self::MAX_KB]];
    }
}
