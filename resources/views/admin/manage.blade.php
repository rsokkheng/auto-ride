@extends('admin.layout')

@section('title', 'Manage ' . $config['title'])
@section('page-title', 'Manage ' . $config['title'])

@section('content')
    <div class="row mb-3">
        <div class="col-12">
            <div class="btn-group">
                @foreach($resources as $key => $item)
                    <a href="{{ route('admin.manage', ['resource' => $key]) }}" class="btn btn-sm btn-outline-primary {{ $resource === $key ? 'active' : '' }}">
                        {{ $item['title'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    @if(isset($item))
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ $config['title'] }} Details</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <tbody>
                                @foreach($item->getAttributes() as $key => $value)
                                    <tr>
                                        <th>{{ ucwords(str_replace(['_', '-'], ' ', $key)) }}</th>
                                        <td>{{ is_null($value) ? '-' : $value }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer clearfix">
                        <a href="{{ route('admin.manage', ['resource' => $resource]) }}" class="btn btn-secondary btn-sm">Back to {{ $config['title'] }}</a>
                        <button class="btn btn-primary btn-sm">Edit</button>
                        <button class="btn btn-danger btn-sm">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ $config['title'] }} List</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover text-nowrap">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $record)
                            <tr>
                                <td>{{ $record->id }}</td>
                                <td>{{ $record->name ?? $record->title ?? ($record->email ?? 'N/A') }}</td>
                                <td>{{ $record->status ?? $record->role ?? '—' }}</td>
                                <td>{{ optional($record->created_at)->format('Y-m-d') }}</td>
                                <td>
                                    <a href="{{ route('admin.manage', ['resource' => $resource, 'id' => $record->id]) }}" class="btn btn-xs btn-info">Manage</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer clearfix">
                {{ $items->links() }}
            </div>
        </div>
    @endif
@endsection
