<?php

namespace App\Mcp\Tools;

use App\Actions\Contacts\SearchContacts;
use App\Data\Contacts\ContactData;
use App\Data\Contacts\SearchContactsData;
use App\Models\User;
use App\Support\DataSchema;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Search the workspace\'s contacts (customers) by name or email, 25 per page. Use `page` to continue while `next_page` is set.')]
#[IsReadOnly]
class SearchContactsTool extends Tool
{
    public function handle(Request $request, SearchContacts $searchContacts): Response
    {
        /** @var User $user */
        $user = $request->user();

        $paginator = $searchContacts->handle($user, $request->except('page'))
            ->paginate(25, page: max(1, (int) $request->get('page', 1)));

        return Response::json([
            'contacts' => ContactData::collect($paginator->items()),
            'page' => $paginator->currentPage(),
            'next_page' => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
            'total' => $paginator->total(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...DataSchema::for(SearchContactsData::class),
            'page' => $schema->integer()->min(1)->description('Page number, starting at 1.'),
        ];
    }
}
