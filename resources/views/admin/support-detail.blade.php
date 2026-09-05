@extends('admin.layout')
@section('title', 'Ticket #' . $ticket->id)
@section('page-title', 'Support Ticket #' . $ticket->id)

@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
    <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
@endif

<a href="{{ route('admin.support') }}" class="btn btn-sm btn-secondary mb-3">
    <i class="fas fa-arrow-left mr-1"></i> Back to Tickets
</a>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">{{ $ticket->subject }}</h3>
                @php $sc = ['open'=>'primary','in_progress'=>'warning','resolved'=>'success','closed'=>'secondary']; @endphp
                <span class="badge badge-{{ $sc[$ticket->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_',' ',$ticket->status)) }}</span>
            </div>
            <div class="card-body" style="max-height:520px;overflow-y:auto;">
                @forelse($ticket->messages as $m)
                    @php $isStaff = $m->sender && $m->sender->role === 'admin'; @endphp
                    <div class="d-flex mb-3 {{ $isStaff ? 'justify-content-end' : 'justify-content-start' }}">
                        <div style="max-width:75%;">
                            <div class="rounded p-3 {{ $isStaff ? 'bg-primary text-white' : 'bg-light' }}">
                                <div style="font-size:.75rem;font-weight:700;margin-bottom:4px;{{ $isStaff ? 'opacity:.85' : 'color:#64748b' }}">
                                    {{ $m->sender?->name ?? 'Unknown' }} {{ $isStaff ? '(Staff)' : '' }}
                                </div>
                                <div style="white-space:pre-wrap;">{{ $m->message }}</div>
                            </div>
                            <div class="text-muted mt-1" style="font-size:.7rem;{{ $isStaff ? 'text-align:right;' : '' }}">
                                {{ $m->created_at->format('d M Y, g:i A') }}
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted text-center py-4">No messages yet.</p>
                @endforelse
            </div>
            <div class="card-footer">
                <form method="POST" action="{{ route('admin.support.reply', $ticket) }}">
                    @csrf
                    <div class="form-group">
                        <textarea name="message" class="form-control" rows="3" required maxlength="2000"
                                  placeholder="Type your reply to {{ $ticket->user?->name ?? 'the user' }}…"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane mr-1"></i> Send Reply</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title mb-0">Ticket Details</h3></div>
            <div class="card-body">
                <dl class="mb-0">
                    <dt>From</dt>
                    <dd>{{ $ticket->user?->name ?? '—' }} <br><small class="text-muted">{{ $ticket->user?->email }}</small></dd>

                    <dt>Role</dt>
                    <dd>{{ $ticket->user ? ucfirst($ticket->user->role) : '—' }}</dd>

                    @if($ticket->category)
                        <dt>Category</dt>
                        <dd>{{ ucfirst(str_replace('_',' ', $ticket->category)) }}</dd>
                    @endif

                    @if($ticket->ride_id)
                        <dt>Related Ride</dt>
                        <dd>#{{ $ticket->ride_id }}</dd>
                    @endif

                    @if($ticket->delivery_id)
                        <dt>Related Delivery</dt>
                        <dd>#{{ $ticket->delivery_id }}</dd>
                    @endif

                    <dt>Created</dt>
                    <dd>{{ $ticket->created_at->format('d M Y, g:i A') }}</dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title mb-0">Manage</h3></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.support.update', $ticket) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="user_id" value="{{ $ticket->user_id }}">
                    <input type="hidden" name="subject" value="{{ $ticket->subject }}">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="open" {{ $ticket->status === 'open' ? 'selected' : '' }}>Open</option>
                            <option value="in_progress" {{ $ticket->status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                            <option value="resolved" {{ $ticket->status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                            <option value="closed" {{ $ticket->status === 'closed' ? 'selected' : '' }}>Closed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority" class="form-control">
                            <option value="low" {{ $ticket->priority === 'low' ? 'selected' : '' }}>Low</option>
                            <option value="medium" {{ $ticket->priority === 'medium' ? 'selected' : '' }}>Medium</option>
                            <option value="high" {{ $ticket->priority === 'high' ? 'selected' : '' }}>High</option>
                            <option value="urgent" {{ $ticket->priority === 'urgent' ? 'selected' : '' }}>Urgent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Assign To</label>
                        <select name="assigned_to" class="form-control">
                            <option value="">— Unassigned —</option>
                            @foreach($admins as $a)
                                <option value="{{ $a->id }}" {{ $ticket->assigned_to === $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-block btn-secondary"><i class="fas fa-save mr-1"></i> Update</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
