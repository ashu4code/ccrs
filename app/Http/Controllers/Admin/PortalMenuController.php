<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PortalMenuItem;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PortalMenuController extends Controller
{
    /**
     * Every sidebar item grouped by audience, with management forms.
     */
    public function index(): View
    {
        return view('admin.menu', [
            'groups' => PortalMenuItem::query()
                ->orderBy('audience')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->groupBy('audience'),
            'audienceLabels' => collect(PortalMenuItem::audiences())->mapWithKeys(
                fn (string $audience): array => [$audience => str_replace('_', ' ', Str::headline($audience))],
            ),
        ]);
    }

    /**
     * Render the edit form for a single sidebar item.
     */
    public function edit(PortalMenuItem $item): View
    {
        return view('admin.menu-edit', ['item' => $item]);
    }

    /**
     * Create a new sidebar item.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->itemRules());

        PortalMenuItem::create($validated);

        return redirect()->route('admin.menu.index');
    }

    /**
     * Update an existing sidebar item.
     */
    public function update(Request $request, PortalMenuItem $item): RedirectResponse
    {
        $validated = $request->validate($this->itemRules());

        $item->update($validated);

        return redirect()->route('admin.menu.index');
    }

    /**
     * Toggle the enabled flag — disabled items vanish from the sidebar.
     */
    public function toggle(PortalMenuItem $item): RedirectResponse
    {
        $item->update(['is_enabled' => ! $item->is_enabled]);

        return redirect()->route('admin.menu.index');
    }

    /**
     * Move one step up or down within the audience ordering.
     */
    public function move(Request $request, PortalMenuItem $item): RedirectResponse
    {
        $validated = $request->validate(['direction' => ['required', 'in:up,down']]);

        $item->move($validated['direction']);

        return redirect()->route('admin.menu.index');
    }

    /**
     * Delete an item; defaults come back when the menu seeder is rerun.
     */
    public function destroy(PortalMenuItem $item): RedirectResponse
    {
        $item->delete();

        return redirect()->route('admin.menu.index');
    }

    /**
     * Shared validation for create and update.
     *
     * @return array<string, list<mixed>>
     */
    private function itemRules(): array
    {
        return [
            'audience' => ['required', 'string', Rule::in(PortalMenuItem::audiences())],
            'section' => ['required', 'string', 'max:100'],
            'label' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:50'],
            'route_name' => ['nullable', 'string', 'max:120', $this->namedRouteRule()],
            'url' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Named-route validation: a configured route must actually exist.
     */
    private function namedRouteRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            if (! Route::getRoutes()->hasNamedRoute($value)) {
                $fail('The named route does not exist.');
            }
        };
    }
}
