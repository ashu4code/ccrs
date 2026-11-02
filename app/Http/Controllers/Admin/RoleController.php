<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\Discom;
use App\Models\Division;
use App\Models\RoleType;
use App\Models\Section;
use App\Models\Subdivision;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->with(['discom', 'circle', 'division', 'subdivision', 'section'])
            ->orderBy('name')
            ->get();

        return view('admin.roles.index', compact('users'));
    }

    public function create(): View
    {
        $roles = collect(UserRole::cases())
            ->filter(fn (UserRole $role) => $role !== UserRole::SiteAdmin)
            ->values();
        $roleTypes = RoleType::query()->where('is_active', true)->orderBy('name')->get();
        $discoms = Discom::query()->where('is_active', true)->orderBy('name')->get();

        return view('admin.roles.create', compact('roles', 'roleTypes', 'discoms'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'login_username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'discom_id' => ['nullable', 'exists:discoms,id'],
            'circle_id' => ['nullable', 'exists:circles,id'],
            'division_id' => ['nullable', 'exists:divisions,id'],
            'subdivision_id' => ['nullable', 'exists:subdivisions,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'mobile' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
        ]);

        $role = ! empty($validated['role']) ? UserRole::from($validated['role']) : null;

        // A "Login Username" that looks like an email is stored verbatim so the
        // official can sign in with either their email or their username.
        $login = $validated['login_username'] ?? null;
        $defaultEmail = strtolower(str_replace(' ', '.', $validated['user_name'])).'@ht-cgrf.test';

        if ($login !== null && filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $email = $login;
            $username = $login;
        } elseif ($login !== null) {
            $email = $defaultEmail;
            $username = $login;
        } else {
            $email = $defaultEmail;
            $username = $defaultEmail;
        }

        $password = ! empty($validated['password']) ? bcrypt($validated['password']) : bcrypt('password');

        $data = [
            'name' => $validated['user_name'],
            'display_name' => $validated['display_name'] ?? null,
            'email' => $email,
            'username' => $username,
            'password' => $password,
        ];

        // Map onto an existing official matched by username first, then email.
        $user = User::query()->where('username', $username)->first()
            ?? User::query()->where('email', $email)->first();

        $user = $user !== null ? tap($user)->update($data) : User::query()->create($data);

        $data = [
            'name' => $validated['user_name'],
            'display_name' => $validated['display_name'] ?? null,
            'role' => $role?->value,
            'mobile' => $validated['mobile'] ?? null,
            'designation' => $validated['designation'] ?? null,
            'discom_id' => null,
            'circle_id' => null,
            'division_id' => null,
            'subdivision_id' => null,
            'section_id' => null,
        ];

        if ($role === UserRole::Discom && ! empty($validated['discom_id'])) {
            $data['discom_id'] = $validated['discom_id'];
        } elseif ($role === UserRole::Circle && ! empty($validated['circle_id'])) {
            $data['circle_id'] = $validated['circle_id'];
            $circle = Circle::query()->find($validated['circle_id']);
            if ($circle) {
                $data['discom_id'] = $circle->discom_id;
            }
        } elseif ($role === UserRole::Division && ! empty($validated['division_id'])) {
            $data['division_id'] = $validated['division_id'];
            $division = Division::query()->with('circle')->find($validated['division_id']);
            if ($division) {
                $data['circle_id'] = $division->circle_id;
                $data['discom_id'] = $division->circle?->discom_id;
            }
        } elseif ($role === UserRole::Subdivision && ! empty($validated['subdivision_id'])) {
            $data['subdivision_id'] = $validated['subdivision_id'];
            $subdivision = Subdivision::query()->with('division.circle')->find($validated['subdivision_id']);
            if ($subdivision) {
                $data['division_id'] = $subdivision->division_id;
                $data['circle_id'] = $subdivision->division?->circle_id;
                $data['discom_id'] = $subdivision->division?->circle?->discom_id;
            }
        } elseif ($role === UserRole::Section && ! empty($validated['section_id'])) {
            $data['section_id'] = $validated['section_id'];
            $section = Section::query()->with('subdivision.division.circle')->find($validated['section_id']);
            if ($section) {
                $data['subdivision_id'] = $section->subdivision_id;
                $data['division_id'] = $section->subdivision?->division_id;
                $data['circle_id'] = $section->subdivision?->division?->circle_id;
                $data['discom_id'] = $section->subdivision?->division?->circle?->discom_id;
            }
        } elseif (in_array($role, [UserRole::RegionalManager, UserRole::CompetentAuthority], true)) {
            if (! empty($validated['section_id'])) {
                $data['section_id'] = $validated['section_id'];
                $section = Section::query()->with('subdivision.division.circle')->find($validated['section_id']);
                if ($section) {
                    $data['subdivision_id'] = $section->subdivision_id;
                    $data['division_id'] = $section->subdivision?->division_id;
                    $data['circle_id'] = $section->subdivision?->division?->circle_id;
                    $data['discom_id'] = $section->subdivision?->division?->circle?->discom_id;
                }
            } elseif (! empty($validated['subdivision_id'])) {
                $data['subdivision_id'] = $validated['subdivision_id'];
                $subdivision = Subdivision::query()->with('division.circle')->find($validated['subdivision_id']);
                if ($subdivision) {
                    $data['division_id'] = $subdivision->division_id;
                    $data['circle_id'] = $subdivision->division?->circle_id;
                    $data['discom_id'] = $subdivision->division?->circle?->discom_id;
                }
            } elseif (! empty($validated['division_id'])) {
                $data['division_id'] = $validated['division_id'];
                $division = Division::query()->with('circle')->find($validated['division_id']);
                if ($division) {
                    $data['circle_id'] = $division->circle_id;
                    $data['discom_id'] = $division->circle?->discom_id;
                }
            } elseif (! empty($validated['circle_id'])) {
                $data['circle_id'] = $validated['circle_id'];
                $circle = Circle::query()->find($validated['circle_id']);
                if ($circle) {
                    $data['discom_id'] = $circle->discom_id;
                }
            } elseif (! empty($validated['discom_id'])) {
                $data['discom_id'] = $validated['discom_id'];
            }
        } elseif ($role === null || ! $role instanceof UserRole) {
            // allow setting hierarchy even if role not in enum list? but just in case
            if (! empty($validated['section_id'])) {
                $data['section_id'] = $validated['section_id'];
                $section = Section::query()->with('subdivision.division.circle')->find($validated['section_id']);
                if ($section) {
                    $data['subdivision_id'] = $section->subdivision_id;
                    $data['division_id'] = $section->subdivision?->division_id;
                    $data['circle_id'] = $section->subdivision?->division?->circle_id;
                    $data['discom_id'] = $section->subdivision?->division?->circle?->discom_id;
                }
            } elseif (! empty($validated['subdivision_id'])) {
                $data['subdivision_id'] = $validated['subdivision_id'];
                $subdivision = Subdivision::query()->with('division.circle')->find($validated['subdivision_id']);
                if ($subdivision) {
                    $data['division_id'] = $subdivision->division_id;
                    $data['circle_id'] = $subdivision->division?->circle_id;
                    $data['discom_id'] = $subdivision->division?->circle?->discom_id;
                }
            } elseif (! empty($validated['division_id'])) {
                $data['division_id'] = $validated['division_id'];
                $division = Division::query()->with('circle')->find($validated['division_id']);
                if ($division) {
                    $data['circle_id'] = $division->circle_id;
                    $data['discom_id'] = $division->circle?->discom_id;
                }
            } elseif (! empty($validated['circle_id'])) {
                $data['circle_id'] = $validated['circle_id'];
                $circle = Circle::query()->find($validated['circle_id']);
                if ($circle) {
                    $data['discom_id'] = $circle->discom_id;
                }
            } elseif (! empty($validated['discom_id'])) {
                $data['discom_id'] = $validated['discom_id'];
            }
        } else {
            if (! empty($validated['section_id'])) {
                $data['section_id'] = $validated['section_id'];
                $section = Section::query()->with('subdivision.division.circle')->find($validated['section_id']);
                if ($section) {
                    $data['subdivision_id'] = $section->subdivision_id;
                    $data['division_id'] = $section->subdivision?->division_id;
                    $data['circle_id'] = $section->subdivision?->division?->circle_id;
                    $data['discom_id'] = $section->subdivision?->division?->circle?->discom_id;
                }
            } elseif (! empty($validated['subdivision_id'])) {
                $data['subdivision_id'] = $validated['subdivision_id'];
                $subdivision = Subdivision::query()->with('division.circle')->find($validated['subdivision_id']);
                if ($subdivision) {
                    $data['division_id'] = $subdivision->division_id;
                    $data['circle_id'] = $subdivision->division?->circle_id;
                    $data['discom_id'] = $subdivision->division?->circle?->discom_id;
                }
            } elseif (! empty($validated['division_id'])) {
                $data['division_id'] = $validated['division_id'];
                $division = Division::query()->with('circle')->find($validated['division_id']);
                if ($division) {
                    $data['circle_id'] = $division->circle_id;
                    $data['discom_id'] = $division->circle?->discom_id;
                }
            } elseif (! empty($validated['circle_id'])) {
                $data['circle_id'] = $validated['circle_id'];
                $circle = Circle::query()->find($validated['circle_id']);
                if ($circle) {
                    $data['discom_id'] = $circle->discom_id;
                }
            } elseif (! empty($validated['discom_id'])) {
                $data['discom_id'] = $validated['discom_id'];
            }
        }

        // Region is derived from the mapped DISCOM (e.g. "Discom-North" → "North").
        if (! empty($data['discom_id'])) {
            $discom = Discom::query()->find($data['discom_id']);
            if ($discom !== null) {
                $data['region'] = $this->regionForDiscom($discom);
            }
        }

        $user->update($data);

        return redirect()->route('admin.roles.index')
            ->with('status', 'Role mapped successfully.');
    }

    public function edit(User $user): View
    {
        $roles = collect(UserRole::cases())
            ->filter(fn (UserRole $role) => $role !== UserRole::SiteAdmin)
            ->values();
        $roleTypes = RoleType::query()->where('is_active', true)->orderBy('name')->get();
        $discoms = Discom::query()->where('is_active', true)->orderBy('name')->get();

        return view('admin.roles.edit', compact('user', 'roles', 'roleTypes', 'discoms'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role_name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'password' => ['nullable', 'string', 'max:255'],
            'discom_id' => ['nullable', 'exists:discoms,id'],
            'circle_id' => ['nullable', 'exists:circles,id'],
            'division_id' => ['nullable', 'exists:divisions,id'],
            'subdivision_id' => ['nullable', 'exists:subdivisions,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'mobile' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
        ]);

        $role = ! empty($validated['role']) ? UserRole::from($validated['role']) : null;

        $data = [
            'name' => $validated['role_name'],
            'display_name' => $validated['display_name'] ?? null,
            'role' => $role?->value,
            'mobile' => $validated['mobile'] ?? null,
            'designation' => $validated['designation'] ?? null,
            'discom_id' => null,
            'circle_id' => null,
            'division_id' => null,
            'subdivision_id' => null,
            'section_id' => null,
        ];

        if (! empty($validated['password'])) {
            $data['password'] = bcrypt($validated['password']);
        }

        if ($role === UserRole::Discom && ! empty($validated['discom_id'])) {
            $data['discom_id'] = $validated['discom_id'];
        } elseif ($role === UserRole::Circle && ! empty($validated['circle_id'])) {
            $data['circle_id'] = $validated['circle_id'];
            $circle = Circle::query()->find($validated['circle_id']);
            if ($circle) {
                $data['discom_id'] = $circle->discom_id;
            }
        } elseif ($role === UserRole::Division && ! empty($validated['division_id'])) {
            $data['division_id'] = $validated['division_id'];
            $division = Division::query()->with('circle')->find($validated['division_id']);
            if ($division) {
                $data['circle_id'] = $division->circle_id;
                $data['discom_id'] = $division->circle?->discom_id;
            }
        } elseif ($role === UserRole::Subdivision && ! empty($validated['subdivision_id'])) {
            $data['subdivision_id'] = $validated['subdivision_id'];
            $subdivision = Subdivision::query()->with('division.circle')->find($validated['subdivision_id']);
            if ($subdivision) {
                $data['division_id'] = $subdivision->division_id;
                $data['circle_id'] = $subdivision->division?->circle_id;
                $data['discom_id'] = $subdivision->division?->circle?->discom_id;
            }
        } elseif ($role === UserRole::Section && ! empty($validated['section_id'])) {
            $data['section_id'] = $validated['section_id'];
            $section = Section::query()->with('subdivision.division.circle')->find($validated['section_id']);
            if ($section) {
                $data['subdivision_id'] = $section->subdivision_id;
                $data['division_id'] = $section->subdivision?->division_id;
                $data['circle_id'] = $section->subdivision?->division?->circle_id;
                $data['discom_id'] = $section->subdivision?->division?->circle?->discom_id;
            }
        } elseif (in_array($role, [UserRole::RegionalManager, UserRole::CompetentAuthority], true) || $role === null) {
            // Relationship Managers, Competent Authorities, and unmapped roles
            // inherit their organisation chain from the deepest level chosen.
            if (! empty($validated['section_id'])) {
                $data['section_id'] = $validated['section_id'];
                $section = Section::query()->with('subdivision.division.circle')->find($validated['section_id']);
                if ($section) {
                    $data['subdivision_id'] = $section->subdivision_id;
                    $data['division_id'] = $section->subdivision?->division_id;
                    $data['circle_id'] = $section->subdivision?->division?->circle_id;
                    $data['discom_id'] = $section->subdivision?->division?->circle?->discom_id;
                }
            } elseif (! empty($validated['subdivision_id'])) {
                $data['subdivision_id'] = $validated['subdivision_id'];
                $subdivision = Subdivision::query()->with('division.circle')->find($validated['subdivision_id']);
                if ($subdivision) {
                    $data['division_id'] = $subdivision->division_id;
                    $data['circle_id'] = $subdivision->division?->circle_id;
                    $data['discom_id'] = $subdivision->division?->circle?->discom_id;
                }
            } elseif (! empty($validated['division_id'])) {
                $data['division_id'] = $validated['division_id'];
                $division = Division::query()->with('circle')->find($validated['division_id']);
                if ($division) {
                    $data['circle_id'] = $division->circle_id;
                    $data['discom_id'] = $division->circle?->discom_id;
                }
            } elseif (! empty($validated['circle_id'])) {
                $data['circle_id'] = $validated['circle_id'];
                $circle = Circle::query()->find($validated['circle_id']);
                if ($circle) {
                    $data['discom_id'] = $circle->discom_id;
                }
            } elseif (! empty($validated['discom_id'])) {
                $data['discom_id'] = $validated['discom_id'];
            }
        }

        // Region is derived from the mapped DISCOM (e.g. "Discom-North" → "North"),
        // and is preserved unchanged when no hierarchy is selected.
        if (! empty($data['discom_id'])) {
            $discom = Discom::query()->find($data['discom_id']);
            if ($discom !== null) {
                $data['region'] = $this->regionForDiscom($discom);
            }
        }

        $user->update($data);

        return redirect()->route('admin.roles.index')
            ->with('status', 'Role updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.roles.index')
                ->with('status', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('admin.roles.index')
            ->with('status', 'User deleted successfully.');
    }

    /**
     * Derive the region label from a DISCOM name (e.g. "Discom-North" → "North").
     */
    private function regionForDiscom(Discom $discom): string
    {
        return str_starts_with($discom->name, 'Discom-')
            ? substr($discom->name, strlen('Discom-'))
            : $discom->name;
    }
}
