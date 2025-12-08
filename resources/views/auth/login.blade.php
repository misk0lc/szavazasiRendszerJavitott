<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bejelentkezés</title>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:24px auto;padding:0 16px}
        label{display:block;margin-top:12px}
        input{width:100%;padding:8px;border:1px solid #ccc;border-radius:4px}
        button{margin-top:16px;background:#1b1b18;color:#fff;border:none;padding:10px 14px;border-radius:4px}
        .error{color:#b00020;margin-top:4px}
    </style>
    </head>
<body>
    <p><a href="{{ route('polls.index') }}">← Vissza</a></p>
    <h1>Bejelentkezés</h1>

    @if($errors->any())
        <div style="background:#ffecec;padding:10px;border-radius:4px;margin:12px 0;">
            <ul>
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if(session('status'))
        <p style="background:#eef;padding:8px 12px;border-radius:4px;">{{ session('status') }}</p>
    @endif

    <form method="post" action="{{ route('login.post') }}">
        @csrf
        <label>
            E-mail
            <input type="email" name="email" value="{{ old('email') }}" required>
        </label>
        <label>
            Jelszó
            <input type="password" name="password" required>
        </label>
        <label style="display:flex;align-items:center;gap:8px;margin-top:8px">
            <input type="checkbox" name="remember" value="1"> Maradjak bejelentkezve
        </label>
        <button type="submit">Belépés</button>
    </form>

    <p>Nincs fiókod? <a href="{{ route('register') }}">Regisztráció</a></p>
</body>
</html>
