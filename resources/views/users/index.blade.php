@extends('layouts.app')

@section('title', 'Users')
@section('heading', 'Users')
@section('subheading', 'Create Front Office, Coordinator, Operation and Admin users')

@section('content')
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
    <div class="rounded-2xl border border-line bg-white p-4 h-fit">
        <h3 class="text-sm font-extrabold mb-3">Create user</h3>
        <form method="POST" action="{{ route('users.store') }}" class="space-y-2.5">
            @csrf
            <input name="name" required placeholder="Full name" class="w-full rounded-xl border border-line px-3 py-2 text-[13px]">
            <input name="username" required placeholder="Username" class="w-full rounded-xl border border-line px-3 py-2 text-[13px]">
            <input name="email" type="email" required placeholder="Email" class="w-full rounded-xl border border-line px-3 py-2 text-[13px]">
            <input name="password" type="password" required placeholder="Password" class="w-full rounded-xl border border-line px-3 py-2 text-[13px]">
            <select name="role" required class="w-full rounded-xl border border-line px-3 py-2 text-[13px]">
                @foreach(\App\Models\User::ROLES as $value => $label)
                    <option value="{{ $value }}" @selected(old('role', 'front_office') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="w-full rounded-xl bg-brand text-white font-bold py-2.5 text-[13px]">Create</button>
        </form>
    </div>

    <div class="xl:col-span-2 rounded-2xl border border-line bg-white overflow-hidden">
        <table class="w-full text-[13px]">
            <thead class="bg-mist/80 text-slate-500 text-[11px] uppercase tracking-wide">
                <tr>
                    <th class="text-left font-bold px-4 py-2.5">Name</th>
                    <th class="text-left font-bold px-4 py-2.5">Username</th>
                    <th class="text-left font-bold px-4 py-2.5">Role</th>
                    <th class="text-right font-bold px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $u)
                    @php
                        $roleTone = [
                            'admin' => 'bg-brand-soft text-brand',
                            'coordinator' => 'bg-teal-50 text-teal-800',
                            'front_office' => 'bg-sky-50 text-sky-800',
                            'operation' => 'bg-amber-50 text-amber-800',
                        ];
                    @endphp
                    <tr class="border-t border-line">
                        <td class="px-4 py-2.5">
                            <div class="font-semibold">{{ $u->name }}</div>
                            <div class="text-[11px] text-slate-400">{{ $u->email }}</div>
                        </td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $u->username }}</td>
                        <td class="px-4 py-2.5">
                            <form method="POST" action="{{ route('users.update', $u) }}" class="inline-flex items-center gap-1.5">
                                @csrf @method('PUT')
                                <input type="hidden" name="name" value="{{ $u->name }}">
                                <input type="hidden" name="username" value="{{ $u->username }}">
                                <input type="hidden" name="email" value="{{ $u->email }}">
                                <select name="role" onchange="this.form.submit()"
                                        class="text-[11px] font-bold px-2 py-1 rounded-md border border-line {{ $roleTone[$u->role] ?? 'bg-mist' }}">
                                    @foreach(\App\Models\User::ROLES as $value => $label)
                                        <option value="{{ $value }}" @selected($u->role === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            @if($u->id !== auth()->id())
                                <form method="POST" action="{{ route('users.destroy', $u) }}" onsubmit="return confirm('Delete user?')">
                                    @csrf @method('DELETE')
                                    <button class="text-[12px] font-bold text-rose-600">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-3">{{ $users->links() }}</div>
    </div>
</div>
@endsection
