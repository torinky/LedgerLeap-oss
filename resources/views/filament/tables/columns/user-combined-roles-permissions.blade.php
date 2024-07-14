<div>
    <div class="mb-1">
        <span class=" text-xs text-muted">Combined Roles:</span>
        @foreach($getRecord()->getAllUniqueRoles() as $role)
            {{--            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-1">--}}
            <span class="badge badge-sm badge-primary">
                {{ $role->name }}
            </span>
        @endforeach
    </div>
    <div>
        <span class=" text-xs text-muted">Combined Permissions:</span>
        @foreach($getRecord()->getAllUniquePermissions() as $permission)
            {{--            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mr-1">--}}
            <span class="badge badge-sm badge-info">
                {{ $permission->name }}
            </span>
        @endforeach
    </div>
</div>
