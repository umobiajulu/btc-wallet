@extends('layout')
@section('content')
<style>
.hero>*{position:relative;z-index:1}.hero::after{pointer-events:none}
.hero-top{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
.cur{display:inline-flex;padding:4px;border-radius:99px;background:rgba(255,255,255,.2);gap:2px}
.cur button{border:0;background:none;color:#fff;font:700 .78rem inherit;padding:6px 14px;border-radius:99px;cursor:pointer;opacity:.9;transition:.2s}
.cur button.on{background:#fff;color:#7a4300;opacity:1;box-shadow:0 4px 12px -4px rgba(0,0,0,.3)}
.fiat-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
.fiat-row span,.hero .chip{padding:5px 12px;border-radius:99px;background:rgba(255,255,255,.2);font-size:.82rem;font-weight:600;font-variant-numeric:tabular-nums}
.hero .chip{display:inline-block;margin-top:10px}
.rate{margin-top:14px;font-size:.78rem;opacity:.88}.rate a{color:#fff;text-decoration:underline;cursor:pointer}
.amount.swap{animation:rise .3s both}
</style>
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
      <section class="card hero" id="hero" data-net="{{ $wallet->network }}" @if($balance) data-sats="{{ $balance['confirmed'] }}" data-pending="{{ $balance['unconfirmed'] }}" @endif>
        <div class="hero-top">
          <div class="eyebrow">Total balance</div>
          @if($balance)
            <div class="cur"><button type="button" data-cur="BTC" class="on">BTC</button><button type="button" data-cur="USD">USD</button><button type="button" data-cur="NGN">NGN</button></div>
          @endif
        </div>
        @if($balance)
          <div class="amount" id="main-amt">{{ number_format($balance['confirmed'] / 1e8, 8) }}<small>BTC</small></div>
          <div class="sub" id="sub-amt">{{ number_format($balance['confirmed']) }} sats</div>
          <div class="fiat-row" id="fiat-row"></div>
          <span class="chip" id="pend" style="{{ $balance['unconfirmed'] != 0 ? '' : 'display:none' }}"></span>
          <div class="rate" id="rate">Loading rates…</div>
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
            <input id="amount" name="amount" value="{{ old('amount') }}" placeholder="0.00010000" autocomplete="off" inputmode="decimal" required>
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

(function(){
  var hero=document.getElementById('hero');if(!hero||hero.dataset.sats===undefined)return;
  var sats=+hero.dataset.sats,pend=+hero.dataset.pending,test=hero.dataset.net!=='mainnet';
  var $=function(id){return document.getElementById(id)};
  var F={USD:new Intl.NumberFormat('en-US',{style:'currency',currency:'USD'}),NGN:new Intl.NumberFormat('en-NG',{style:'currency',currency:'NGN',maximumFractionDigits:0})};
  var btcStr=function(v){return (v/1e8).toFixed(8)};
  var cur='BTC',rates=null,stamp=null,failed=false;
  try{cur=localStorage.getItem('wallet_cur')||'BTC';var c=JSON.parse(localStorage.getItem('wallet_rates')||'null');if(c&&Date.now()-c.t<3e5){rates=c.r;stamp=c.t}}catch(e){}
  if(test&&cur!=='BTC')cur='BTC';

  function fiat(code,satsVal){return F[code].format(satsVal/1e8*rates[code])}
  function render(){
    document.querySelectorAll('[data-cur]').forEach(function(b){b.classList.toggle('on',b.dataset.cur===cur)});
    var main=$('main-amt'),sub=$('sub-amt'),row=$('fiat-row'),pe=$('pend'),rt=$('rate');
    main.classList.remove('swap');void main.offsetWidth;main.classList.add('swap');
    var ready=!test&&rates;
    if(cur==='BTC'||!ready){
      main.innerHTML=btcStr(sats)+'<small>BTC</small>';
      sub.textContent=sats.toLocaleString('en-US')+' sats';
      if(cur!=='BTC'&&!test)main.innerHTML=(failed?'—':'…')+'<small>'+cur+'</small>';
    }else{
      main.innerHTML=fiat(cur,sats);
      sub.textContent='≈ '+btcStr(sats)+' BTC';
    }
    row.innerHTML='';
    if(ready){['BTC','USD','NGN'].forEach(function(c){if(c===cur)return;var sp=document.createElement('span');sp.textContent=c==='BTC'?btcStr(sats)+' BTC':fiat(c,sats);row.appendChild(sp)})}
    if(pend){pe.style.display='';var sign=pend>0?'+':'−',a=Math.abs(pend);
      pe.textContent=sign+(ready&&cur!=='BTC'?F[cur].format(a/1e8*rates[cur]):btcStr(a)+' BTC')+' pending'}
    if(test){rt.textContent='Testnet coins have no market value, so there is no USD or NGN price.'}
    else if(rates){var d=new Date(stamp);rt.innerHTML='1 BTC = '+F.USD.format(rates.USD)+' · '+F.NGN.format(rates.NGN)+' · updated '+d.toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'})}
    else if(failed){rt.innerHTML='Couldn\'t load prices. <a id="retry">Retry</a>';$('retry').onclick=load}
    else rt.textContent='Loading rates…';
  }

  function get(url){var ctl=new AbortController(),t=setTimeout(function(){ctl.abort()},10000);
    return fetch(url,{signal:ctl.signal}).then(function(r){clearTimeout(t);if(!r.ok)throw 0;return r.json()})}
  function load(){
    if(test)return;failed=false;
    get('https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd,ngn')
      .then(function(j){return{USD:j.bitcoin.usd,NGN:j.bitcoin.ngn}})
      .catch(function(){return get('https://api.coinbase.com/v2/exchange-rates?currency=BTC').then(function(j){return{USD:+j.data.rates.USD,NGN:+j.data.rates.NGN}})})
      .then(function(r){if(!(r.USD>0&&r.NGN>0))throw 0;rates=r;stamp=Date.now();try{localStorage.setItem('wallet_rates',JSON.stringify({t:stamp,r:r}))}catch(e){}})
      .catch(function(){if(!rates)failed=true})
      .then(render);
  }
  document.querySelectorAll('[data-cur]').forEach(function(b){b.addEventListener('click',function(){
    if(test&&b.dataset.cur!=='BTC'){cur=b.dataset.cur;render();cur='BTC';return}
    cur=b.dataset.cur;try{localStorage.setItem('wallet_cur',cur)}catch(e){}render();})});
  render();load();setInterval(load,6e4);
})();
</script>
@endpush