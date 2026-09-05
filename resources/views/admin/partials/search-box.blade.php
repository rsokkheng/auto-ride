{{--
    Reusable "search by name/phone/email/number" box for admin list pages.
    Params:
      - route: string (required) — route() result to submit the GET form to
      - search: string|null — current search value
      - placeholder: string|null
      - extra: array — other query params to preserve (key => value), e.g. ['status' => $status]
--}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ $route }}" class="form-inline flex-wrap" style="gap:6px">
            @foreach($extra ?? [] as $key => $value)
                @if($value !== null && $value !== '')
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="{{ $placeholder ?? 'Search by name, phone, or email…' }}"
                   value="{{ $search ?? '' }}" style="min-width:240px">
            <button class="btn btn-sm btn-primary"><i class="fas fa-search mr-1"></i>Search</button>
            @if(($search ?? '') !== '')
                <a href="{{ $route }}{{ !empty($extra) ? '?' . http_build_query(array_filter($extra ?? [], fn($v) => $v !== null && $v !== '')) : '' }}" class="btn btn-sm btn-secondary">Reset</a>
            @endif
        </form>
    </div>
</div>
