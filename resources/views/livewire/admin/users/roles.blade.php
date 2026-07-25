<div>
    <flux:heading size="xl" class="mb-6">Roles</flux:heading>

    @foreach ($roles as $role)
        <flux:card class="mb-4">
            <div class="flex items-center justify-between mb-3">
                <flux:heading size="lg">{{ $role->name }}</flux:heading>
                <flux:badge :color="match($role->value) {
                    'admin' => 'red',
                    'operator' => 'blue',
                    'viewer' => 'green',
                    default => 'gray',
                }">
                    {{ count($usersByRole[$role->value] ?? []) }} users
                </flux:badge>
            </div>

            @if (!empty($usersByRole[$role->value]))
                <div class="flex flex-wrap gap-2">
                    @foreach ($usersByRole[$role->value] as $user)
                        <flux:badge>{{ $user->name }}</flux:badge>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500">No users assigned to this role.</p>
            @endif
        </flux:card>
    @endforeach

    @if ($unassignedUsers->isNotEmpty())
        <flux:card class="mt-4">
            <flux:heading size="lg" class="mb-3">Unassigned</flux:heading>
            <div class="flex flex-wrap gap-2">
                @foreach ($unassignedUsers as $user)
                    <flux:badge color="zinc">{{ $user->name }}</flux:badge>
                @endforeach
            </div>
        </flux:card>
    @endif
</div>
