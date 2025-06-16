<h1>Table: {{ $table }}</h1>
<a href="{{ route('crud.create', ['table' => $table ]) }}">Tambah</a>

<table border="1">
    <thead>
        <tr>
            @if($data->isNotEmpty())
                @foreach (array_keys((array) $data[0]) as $field)
                    <th>{{ $field }}</th>
                @endforeach
                <th>Aksi</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $item)
            <tr>
                @foreach ($item as $field)
                    <td>{{ $field }}</td>
                @endforeach
                <td>
                    <a href="{{ route('crud.show', ['table' => $table, 'id' => $item->id ]) }}">View</a> |
                    <a href="{{ route('crud.edit', ['table' => $table, 'id' => $item->id ]) }}">Edit</a> |
                    <form action="{{ route('crud.destroy', ['table' => $table, 'id' => $item->id ]) }}"
                                   method="POST" style="display:inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" onclick="return confirm('Yakin Hapus?')">
                            Hapus
                        </button>
                    </form>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
