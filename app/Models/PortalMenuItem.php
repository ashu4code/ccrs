<?php

namespace App\Models;

use Database\Factories\PortalMenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

#[Fillable(['audience', 'section', 'label', 'route_name', 'url', 'icon', 'sort_order', 'is_enabled'])]
class PortalMenuItem extends Model
{
    /** @use HasFactory<PortalMenuItemFactory> */
    use HasFactory;

    public const AUDIENCE_CONSUMER = 'consumer';

    public const AUDIENCE_SITE_ADMIN = 'site_admin';

    public const AUDIENCE_REGIONAL_MANAGER = 'relationship_manager';

    public const AUDIENCE_COMPETENT_AUTHORITY = 'competent_authority';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PortalMenuItem $item): void {
            $item->sort_order ??= (int) (static::query()->where('audience', $item->audience)->max('sort_order') + 1);
        });
    }

    /**
     * The audiences receiving a role-aware sidebar.
     *
     * @return list<string>
     */
    public static function audiences(): array
    {
        return [
            self::AUDIENCE_CONSUMER,
            self::AUDIENCE_SITE_ADMIN,
            self::AUDIENCE_REGIONAL_MANAGER,
            self::AUDIENCE_COMPETENT_AUTHORITY,
        ];
    }

    /**
     * Enabled sidebar items for the given audience, in menu order.
     *
     * @return Collection<int, PortalMenuItem>
     */
    public static function menuFor(string $audience): Collection
    {
        return static::query()
            ->where('audience', $audience)
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Resolvable destination, or null when the item renders as a coming-soon
     * placeholder (no target stored, or a stored route that no longer exists).
     */
    public function displayUrl(): ?string
    {
        if ($this->route_name !== null) {
            try {
                return route($this->route_name);
            } catch (Throwable) {
                return null;
            }
        }

        if ($this->url !== null) {
            return url($this->url);
        }

        return null;
    }

    /**
     * Whether the being-requested page is this item's destination.
     */
    public function isActive(): bool
    {
        if ($this->route_name === null) {
            return false;
        }

        try {
            route($this->route_name);
        } catch (Throwable) {
            return false;
        }

        return request()->routeIs($this->route_name);
    }

    /**
     * Move one step up or down within the audience ordering and resequence.
     */
    public function move(string $direction): bool
    {
        $siblings = static::query()
            ->where('audience', $this->audience)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $index = $siblings->search(fn (self $candidate): bool => $candidate->is($this));

        if ($index === false) {
            return false;
        }

        $targetIndex = $index + ($direction === 'up' ? -1 : 1);

        if ($targetIndex < 0 || $targetIndex >= $siblings->count()) {
            return false;
        }

        [$siblings[$index], $siblings[$targetIndex]] = [$siblings[$targetIndex], $siblings[$index]];

        $this->resequence($siblings);

        return true;
    }

    /**
     * Assign dense, sequential sort orders to the ordered list.
     *
     * @param  Collection<int, PortalMenuItem>  $items
     */
    private function resequence(Collection $items): void
    {
        foreach ($items->values() as $position => $item) {
            $item->sort_order = $position + 1;
            $item->save();
        }
    }
}
