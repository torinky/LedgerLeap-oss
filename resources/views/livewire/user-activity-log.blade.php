<div>
    @if(empty($activityLog))
        <p class="text-base-content">アクティビティログはありません。</p>
    @else
        <table class="table w-full h-full table-zebra">
            <thead>
            <tr>
                <th class="px-4 py-2 text-base-content">ユーザー</th>
                <th class="px-4 py-2 text-base-content">内容</th>
                <th class="px-4 py-2 text-base-content">日時</th>
            </tr>
            </thead>
            <tbody>
            @foreach($activityLog as $log)
                <tr class="hover">
                    <td class="px-4 py-2 text-base-content">{{ $log['causer'] }}</td>
                    <td class="px-4 py-2 text-base-content">{{ $log['description'] }}</td>
                    <td class="px-4 py-2 text-base-content/70">{{ $log['created_at']->diffForHumans() }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
