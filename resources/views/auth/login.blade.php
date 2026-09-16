<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login · PulseCRM</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: { brand: { DEFAULT: '#0f766e', dark: '#115e59' } }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen font-sans antialiased text-slate-900"
      style="background:
        linear-gradient(135deg, rgba(15,118,110,.88), rgba(15,23,42,.92)),
        url('https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=1800&q=80') center/cover;">
<div class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-[400px]">
        <div class="mb-6 text-center text-white">
            <div class="text-[12px] uppercase tracking-[0.22em] opacity-80 font-semibold">Secure access</div>
            <h1 class="mt-2 text-3xl font-extrabold tracking-tight">Pulse<span class="text-teal-200">CRM</span></h1>
            <p class="mt-1 text-sm text-white/75">Sign in to continue. Session stays until logout.</p>
        </div>
        <div class="rounded-2xl bg-white/95 backdrop-blur shadow-2xl border border-white/40 p-6">
            @if($errors->any())
                <div class="mb-4 rounded-xl bg-rose-50 text-rose-700 text-sm px-3 py-2 border border-rose-100">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[12px] font-bold text-slate-500 mb-1.5">Username</label>
                    <input type="text" name="username" value="{{ old('username', 'admin') }}" required autofocus
                           class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
                </div>
                <div>
                    <label class="block text-[12px] font-bold text-slate-500 mb-1.5">Password</label>
                    <input type="password" name="password" value="admin123" required
                           class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
                </div>
                <label class="flex items-center gap-2 text-[12px] text-slate-500">
                    <input type="checkbox" name="remember" value="1" checked class="rounded border-slate-300 text-brand focus:ring-brand">
                    Keep me signed in until logout
                </label>
                <button class="w-full rounded-xl bg-brand hover:bg-brand-dark text-white font-bold py-2.5 text-sm transition">
                    Enter CRM
                </button>
            </form>
            <p class="mt-4 text-center text-[11px] text-slate-400">Demo: <span class="font-semibold text-slate-600">admin / admin123</span></p>
        </div>
    </div>
</div>
</body>
</html>
