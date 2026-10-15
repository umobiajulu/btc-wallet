@extends('layout')
@section('content')
@php
  $explorer = $wallet->network === 'mainnet' ? 'https://mempool.space/tx/' : 'https://mempool.space/testnet4/tx/';
  $sendErr = $errors->has('send') || $errors->has('password') || $errors->has('to') || $errors->has('amount') || $errors->has('fee_rate');
@endphp
<div class="shell">
  <header class="nav">
    <div class="left"><div class="logo">₿</div></div>
    <div class="right">
      <span class="badge {{ $wallet->network === 'mainnet' ? '' : 'test' }}">{{ $wallet->network }}</span>
      <div class="pill"><div class="avatar">{{ mb_strtoupper(mb_substr($wallet->name, 0, 1)) }}</div><span class="nm">{{ $wallet->name }}</span></div>
      <form method="POST" action="{{ route('logout') }}">@csrf<button class="out" type="submit">Log out</button></form>
    </div>
  </header>

  @if(session('status'))<div class="alert ok">✓ {{ session('status') }}</div>@endif
  @if($error)<div class="alert err">{{ $error }}</div>@endif
  @error('send')<div class="alert err">{{ $message }}</div>@enderror

  <div class="grid">
    <div class="stack">
      <section class="card hero">
        <div class="eyebrow">Total balance</div>
        @if($balance)
          <div class="amount">{{ number_format($balance['confirmed'] / 1e8, 8) }}<small>BTC</small></div>
          <div class="sub">{{ number_format($balance['confirmed']) }} sats</div>
          @if($balance['unconfirmed'] != 0)
            <span class="chip">{{ $balance['unconfirmed'] > 0 ? '+' : '' }}{{ number_format($balance['unconfirmed'] / 1e8, 8) }} BTC pending</span>
          @endif
        @else
          <div class="amount">—<small>BTC</small></div><div class="sub">Balance unavailable</div>
        @endif
      </section>

      <section class="card">
        <div class="sec-title"><h2>Recent activity</h2><span class="muted small">{{ count($history) }} latest</span></div>
        @forelse($history as $h)
          <div class="tx">
            <div class="ico {{ $h['net'] >= 0 ? 'in' : 'outg' }}">{{ $h['net'] >= 0 ? '↓' : '↑' }}</div>
            <div class="mid">
              <div class="t">{{ $h['net'] >= 0 ? 'Received' : 'Sent' }}
                @unless($h['confirmed'])<span class="badge test" style="margin-left:6px">Pending</span>@endunless</div>
              <div class="id"><a href="{{ $explorer . $h['txid'] }}" target="_blank" rel="noopener noreferrer">{{ substr($h['txid'], 0, 10) }}…{{ substr($h['txid'], -6) }}</a>
                @if($h['time']) · {{ date('M j, H:i', $h['time']) }}@endif</div>
            </div>
            <div class="val {{ $h['net'] >= 0 ? 'in' : 'outg' }}">{{ $h['net'] >= 0 ? '+' : '' }}{{ number_format($h['net'] / 1e8, 8) }}</div>
          </div>
        @empty
          <div class="empty">No transactions yet.<br><span class="small">Receive some coins to get started.</span></div>
        @endforelse
      </section>
    </div>

    <section class="card">
      <div class="tabs"><button type="button" data-tab="send" class="on">↑ Send</button><button type="button" data-tab="receive">↓ Receive</button></div>

      <div data-panel="send" class="on">
        <form method="POST" action="{{ route('send.preview') }}" data-busy="Signing…" autocomplete="off">
          @csrf
          <div class="field">
            <label for="to">Recipient address</label>
            <input id="to" name="to" value="{{ old('to') }}" placeholder="{{ $wallet->network === 'mainnet' ? 'bc1q…' : 'tb1q…' }}" spellcheck="false" required>
            @error('to')<div class="err-text">{{ $message }}</div>@enderror
          </div>
          <div class="field">
            <label for="amount">Amount (BTC)</label>
            <input id="amount" autocomplete="off" name="amount" value="{{ old('amount') }}" placeholder="0.00010000" inputmode="decimal" required>
            @error('amount')<div class="err-text">{{ $message }}</div>@enderror
          </div>
          <div class="field">
            <label>Network fee</label>
            <div class="fees">
              @foreach(['slow' => ['Slow', '~1 hr'], 'medium' => ['Normal', '~30 min'], 'fast' => ['Fast', '~10 min']] as $k => [$label, $eta])
                <div><input type="radio" id="f-{{ $k }}" name="fee_rate" value="{{ $fees[$k] }}" @checked((string) old('fee_rate', $fees['medium']) === (string) $fees[$k])>
                <label for="f-{{ $k }}"><b>{{ $label }}</b>{{ $fees[$k] }} sat/vB · {{ $eta }}</label></div>
              @endforeach
            </div>
            @error('fee_rate')<div class="err-text">{{ $message }}</div>@enderror
          </div>
          <div class="field">
            <label for="spw">Password</label>
            <div class="input"><input id="spw" type="password" name="password" autocomplete="new-password" required><button type="button" class="eye" data-toggle>Show</button></div>
            <div class="hint">Needed to sign each transaction. You'll review it before it's sent.</div>
            @error('password')<div class="err-text">{{ $message }}</div>@enderror
          </div>
          <button class="btn" type="submit">Review transaction →</button>
        </form>
      </div>

      <div data-panel="receive">
        <p class="muted" style="margin-top:0">Send only {{ $wallet->network === 'mainnet' ? 'Bitcoin (BTC)' : 'testnet coins' }} to this address.</p>
        <div class="addr mono" id="addr">{{ $wallet->address }}</div>
        <button type="button" class="btn ghost" data-copy="#addr" data-skip="1">Copy address</button>
        <div class="alert warn small" style="margin:16px 0 0">Always compare the first and last characters of the address on the sender's side before sending.</div>
      </div>
    </section>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-tab]').forEach(function(t){t.addEventListener('click',function(){
  document.querySelectorAll('[data-tab]').forEach(function(x){x.classList.toggle('on',x===t)});
  document.querySelectorAll('[data-panel]').forEach(function(p){p.classList.toggle('on',p.dataset.panel===t.dataset.tab)});});});
</script>
@endpush
