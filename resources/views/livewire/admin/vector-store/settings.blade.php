<div>
    <flux:card>
        <h3 class="text-lg font-semibold">Vector Store Settings</h3>
        <p class="text-sm text-gray-500">Current driver: <strong>{{ $driver }}</strong></p>
        <p class="text-sm text-gray-500">Status: <flux:badge :color="$isHealthy ? 'green' : 'red'">{{ $isHealthy ? 'Healthy' : 'Unhealthy' }}</flux:badge></p>
    </flux:card>
</div>
