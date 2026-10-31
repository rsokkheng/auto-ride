{{--
    Consistent icon buttons for a table row: Edit, then any extra buttons from the slot, then Delete.

    <x-admin.row-actions :edit="'openEdit(' . $json . ')'"
                         :delete="route('admin.x.destroy', $row)" :confirm="'Delete ' . $row->name . '?'" />

    :edit is a JS onclick string or a URL (:edit-url). :delete is the DELETE form action.
--}}
@props(['edit' => null, 'editUrl' => null, 'delete' => null, 'confirm' => 'Delete this record?'])
<div class="action-group">
    @if($edit)
        <button type="button" class="btn btn-sm btn-outline-primary btn-icon" title="Edit" data-toggle="tooltip" onclick="{{ $edit }}">
            <i class="fas fa-pen"></i>
        </button>
    @elseif($editUrl)
        <a href="{{ $editUrl }}" class="btn btn-sm btn-outline-primary btn-icon" title="Edit" data-toggle="tooltip"><i class="fas fa-pen"></i></a>
    @endif
    {{ $slot }}
    @if($delete)
        <form method="POST" action="{{ $delete }}" class="m-0" onsubmit="return confirm({{ Illuminate\Support\Js::from($confirm) }})">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger btn-icon" title="Delete" data-toggle="tooltip"><i class="fas fa-trash-can"></i></button>
        </form>
    @endif
</div>
