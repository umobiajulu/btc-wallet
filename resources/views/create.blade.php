@extends('layout')
@section('content')
<main class="auth wide">
  <div class="card">
    <div class="logo">₿</div>
    <h1>Create your wallet</h1>
    <p class="lead">Set a username and password, and add a seed phrase of your choice. <span class="badge {{ $network === 'mainnet' ? '' : 'test' }}">{{ $network }}</span></p>

    <div class="alert warn small">
      Your seed phrase is used <strong>once</strong> to create the key, which is then stored encrypted by your password.
      It stays your recovery backup, so keep it with your username. Make it genuinely unpredictable — no quotes, lyrics or dates — because bots sweep weak phrases within seconds.
    </div>

    <form method="POST" data-busy="Creating & encrypting… (a few seconds)" autocomplete="off">
      @csrf
      <div class="field">
        <label for="name">Username</label>
        <input id="name" name="name" value="{{ old('name') }}" autocomplete="off" autofocus required>
        <div class="hint">Also your wallet name and part of key derivation — write it down.</div>
        @error('name')<div class="err-text">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="phrase">Seed phrase</label>
        <div class="input"><input id="phrase" type="password" name="phrase" autocomplete="new-password" required><button type="button" class="eye" data-toggle>Show</button></div>
        <div class="meter"><i id="m1"></i></div>
        <div class="hint" id="t1">Any text, case-sensitive, at least 25 characters.</div>
        @error('phrase')<div class="err-text">{{ $message }}</div>@enderror
      </div>
      <div class="field">
        <label for="phrase2">Confirm seed phrase</label>
        <div class="input"><input id="phrase2" type="password" name="phrase_confirmation" autocomplete="new-password" required><button type="button" class="eye" data-toggle>Show</button></div>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <div class="input"><input id="password" type="password" name="password" autocomplete="new-password" required><button type="button" class="eye" data-toggle>Show</button></div>
        <div class="meter"><i id="m2"></i></div>
        <div class="hint" id="t2">Used to log in and to sign transactions. Minimum 10 characters.</div>
        @error('password')<div class="err-text">{{ $message }}</div>@enderror
      </div>
      <div class="field">
        <label for="password2">Confirm password</label>
        <div class="input"><input id="password2" type="password" name="password_confirmation" autocomplete="new-password" required><button type="button" class="eye" data-toggle>Show</button></div>
      </div>

      <label class="check"><input type="checkbox" name="ack" required>
        I've saved my username and seed phrase. I understand that without them (and the password) my coins cannot be recovered.</label>

      <button class="btn" type="submit">Create wallet</button>
    </form>

    <div class="foot">Already have a wallet? <a href="{{ route('login') }}">Log in</a></div>
  </div>
</main>
@endsection

@push('scripts')
<script>
function score(v){var s=0;if(v.length>=12)s++;if(v.length>=20)s++;if(v.length>=30)s++;if(/[a-z]/.test(v)&&/[A-Z]/.test(v))s++;if(/\d/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;if(/(.)\1\1/.test(v))s--;return Math.max(0,Math.min(6,s));}
function bind(inp,bar,txt,base){var L=['Very weak','Weak','Weak','Fair','Good','Strong','Excellent'],C=['#d93025','#d93025','#f7931a','#f7931a','#9bb800','#0f9d58','#0f9d58'];
  var el=document.getElementById(inp);el.addEventListener('input',function(){var s=el.value?score(el.value):0;var b=document.getElementById(bar);b.style.width=(el.value?(s+1)/7*100:0)+'%';b.style.background=C[s];
  document.getElementById(txt).textContent=el.value?L[s]+' — '+base:base;});}
bind('phrase','m1','t1','at least 25 characters, unpredictable.');
bind('password','m2','t2','used to log in and to sign.');
</script>
@endpush
