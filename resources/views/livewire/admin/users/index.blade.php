<div>
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">Users</flux:heading>
        <flux:button icon="plus" wire:click="$set('showCreateModal', true)">Create User</flux:button>
    </div>

    @if (session()->has('status'))
        <flux:callout color="green" class="mb-4">{{ session('status') }}</flux:callout>
    @endif

    @if (session()->has('error'))
        <flux:callout color="red" class="mb-4">{{ session('error') }}</flux:callout>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            <flux:table.column>Role</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
        @foreach ($users as $user)
            <flux:table.row :key="$user->id">
                <flux:table.cell>{{ $user->name }}</flux:table.cell>
                <flux:table.cell>{{ $user->email }}</flux:table.cell>
                <flux:table.cell>
                    <flux:badge :color="match($user->role?->value) {
                        'admin' => 'red',
                        'operator' => 'blue',
                        'viewer' => 'green',
                        default => 'gray',
                    }">
                        {{ $user->role?->name ?? 'None' }}
                    </flux:badge>
                </flux:table.cell>
                <flux:table.cell>
                    <div class="flex items-center gap-2">
                        <flux:button size="sm" icon="pencil" wire:click="edit({{ $user->id }})">Edit</flux:button>
                        <flux:button size="sm" variant="danger" icon="trash" wire:click="delete({{ $user->id }})"
                            wire:confirm="Are you sure you want to delete {{ $user->name }}?">Delete</flux:button>
                    </div>
                </flux:table.cell>
            </flux:table.row>
        @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $users->links() }}
    </div>

    {{-- Create User Modal --}}
    <flux:modal wire:model="showCreateModal">
        <flux:heading size="lg">Create User</flux:heading>
        <form wire:submit="create" class="mt-4 space-y-4">
            <flux:field>
                <flux:label>Name</flux:label>
                <flux:input wire:model="name" required />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Email</flux:label>
                <flux:input type="email" wire:model="email" required />
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <flux:label>Password</flux:label>
                <flux:input type="password" wire:model="password" required />
                <flux:error name="password" />
            </flux:field>

            <flux:field>
                <flux:label>Confirm Password</flux:label>
                <flux:input type="password" wire:model="passwordConfirmation" required />
                <flux:error name="passwordConfirmation" />
            </flux:field>

            <flux:field>
                <flux:label>Role</flux:label>
                <flux:select wire:model="userRole">
                    <option value="viewer">Viewer</option>
                    <option value="operator">Operator</option>
                    <option value="admin">Admin</option>
                </flux:select>
                <flux:error name="userRole" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-4">
                <flux:button wire:click="$set('showCreateModal', false)">Cancel</flux:button>
                <flux:button variant="primary" type="submit">Create</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit User Modal --}}
    <flux:modal wire:model="showEditModal">
        <flux:heading size="lg">Edit User</flux:heading>
        <form wire:submit="update" class="mt-4 space-y-4">
            <flux:field>
                <flux:label>Name</flux:label>
                <flux:input wire:model="editName" required />
                <flux:error name="editName" />
            </flux:field>

            <flux:field>
                <flux:label>Email</flux:label>
                <flux:input type="email" wire:model="editEmail" required />
                <flux:error name="editEmail" />
            </flux:field>

            <flux:field>
                <flux:label>New Password (leave empty to keep current)</flux:label>
                <flux:input type="password" wire:model="editPassword" />
                <flux:error name="editPassword" />
            </flux:field>

            <flux:field>
                <flux:label>Confirm New Password</flux:label>
                <flux:input type="password" wire:model="editPasswordConfirmation" />
                <flux:error name="editPasswordConfirmation" />
            </flux:field>

            <flux:field>
                <flux:label>Role</flux:label>
                <flux:select wire:model="editRole">
                    <option value="viewer">Viewer</option>
                    <option value="operator">Operator</option>
                    <option value="admin">Admin</option>
                </flux:select>
                <flux:error name="editRole" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-4">
                <flux:button wire:click="$set('showEditModal', false)">Cancel</flux:button>
                <flux:button variant="primary" type="submit">Save</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
