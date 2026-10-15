@extends('layout')
@section('content')
<main class="auth">
  <div class="card">
    <div class="logo">₿</div>
    <h1>Welcome back</h1>
    <p class="lead">Unlock your local wallet with your username and password.</p>

    @if(session('status'))<div class="alert ok">{{ session('status') }}</div>@endif

    <form method="POST" data-busy="Unlocking…" autocomplete="on">
      @csrf
      <div class="field">
        <label for="name">Username</label>
        <input id="name" name="name" value="{{ old('name') }}" autocomplete="off" placeholder="control" autofocus required>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <div class="input">
          <input id="password" type="password" name="password" autocomplete="new-password" required>
          <button type="button" class="eye" data-toggle>Show</button>
        </div>
        @error('password')<div class="err-text">{{ $message }}</div>@enderror
      </div>
      <button class="btn" type="submit">Unlock wallet</button>
    </form>

    <div class="foot">New here? <a href="{{ route('create') }}">Create a wallet</a></div>
  </div>
</main>
@endsection
