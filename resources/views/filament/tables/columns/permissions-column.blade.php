<div>
    <div class="mb-1">
        <span class="text-xs text-muted">Direct Permissions:</span>
        @foreach($getRecord()->permissions as $permission)
            {{--            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-1">--}}
            <span class="badge badge-sm badge-primary">
                {{ $permission->name }}
            </span>
        @endforeach
    </div>
    <div class="mb-1">
        <span class="text-xs text-muted">Inherited Permissions:</span>
        @foreach($getRecord()->getInheritedPermissions() as $permission)
            {{--            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mr-1">--}}
            <span class="badge badge-sm badge-secondary">
                {{ $permission->name }}
            </span>
        @endforeach
    </div>
    <div class="mb-1">
        <span class="text-xs text-muted">Permissions via Direct Roles:</span>
        @foreach($getRecord()->getDirectPermissionsViaRoles() as $permission)
            {{--            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 mr-1">--}}
            <span class="badge badge-sm badge-info">
                {{ $permission->name }}
            </span>
        @endforeach
    </div>
    <div>
        <span class="text-xs text-muted">Permissions via Inherited Roles:</span>
        @foreach($getRecord()->getInheritedPermissionsViaRoles() as $permission)
            {{--            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 mr-1">--}}
            <span class="badge badge-sm badge-default">
                {{ $permission->name }}
            </span>
        @endforeach
    </div>
</div>
