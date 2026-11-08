<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Tags\TagData;
use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Support\Collection;

class TagController extends Controller
{
    /**
     * Every tag in the workspace.
     *
     * @return Collection<int, TagData>
     */
    public function index(): Collection
    {
        return TagData::collect(Tag::query()->orderBy('name')->get());
    }
}
