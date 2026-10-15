@extends('layout')
@section('content')
<main class="auth wide">
  <div class="card">
    <div class="logo">₿</div>
    <h1>Review & send</h1>
    <p class="lead">Check everything carefully — Bitcoin transactions can't be reversed.</p>

    <div class="rows">
      <div class="r"><span>To</span><span class="mono small" style="max-width:300px">{{ $p['to'] }}</span></div>
      <div class="r"><span>Amount</span><span style="font-size:1.15rem">{{ number_format($p['amount'] / 1e8, 8) }} BTC</span></div>
      <div class="r"><span>Network fee</span><span>{{ number_format($p['fee'] / 1e8, 8) }} BTC</span></div>
      <div class="r"><span>Change back to you</span><span>{{ number_format($p['change'] / 1e8, 8) }} BTC</span></div>
      <div class="r"><span>Total leaving wallet</span><span>{{ number_format(($p['amount'] + $p['fee']) / 1e8, 8) }} BTC</span></div>
    </div>

    <div class="btns">
      <a href="{{ route('dashboard') }}" class="btn ghost">Cancel</a>
      <form method="POST" action="{{ route('send.confirm') }}" data-busy="Broadcasting…">@csrf<button class="btn" type="submit">Sign & broadcast</button></form>
    </div>
  </div>
</main>
@endsection
