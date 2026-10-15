<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>Local BTC Wallet</title>
<style>
:root{color-scheme:light dark;--bg:#f7f5f0;--card:rgba(255,255,255,.74);--solid:#fff;--soft:rgba(26,23,15,.05);--text:#1a170f;--muted:#7a7566;--line:rgba(26,23,15,.09);--accent:#f7931a;--accent2:#ffb347;--ink:#1a1204;--ok:#0f9d58;--err:#d93025;--shadow:0 18px 50px -18px rgba(70,45,0,.28);--r:22px}
@media(prefers-color-scheme:dark){:root{--bg:#0b0a08;--card:rgba(28,25,20,.72);--solid:#1b1813;--soft:rgba(255,255,255,.055);--text:#f4f1ea;--muted:#9b9585;--line:rgba(255,255,255,.08);--shadow:0 18px 50px -18px rgba(0,0,0,.7)}}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;min-height:100vh;font:15px/1.55 ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",sans-serif;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased}
.bg{position:fixed;inset:0;z-index:-1;background:radial-gradient(620px 420px at 12% -6%,rgba(247,147,26,.24),transparent 60%),radial-gradient(520px 440px at 104% 8%,rgba(255,179,71,.15),transparent 60%),radial-gradient(640px 520px at 50% 115%,rgba(247,147,26,.11),transparent 60%)}
a{color:var(--accent);text-decoration:none}a:hover{text-decoration:underline}
h1,h2,h3{margin:0;letter-spacing:-.02em}
.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;word-break:break-all}
.muted{color:var(--muted)}.small{font-size:.85rem}

/* cards */
.card{background:var(--card);-webkit-backdrop-filter:blur(16px);backdrop-filter:blur(16px);border:1px solid var(--line);border-radius:var(--r);box-shadow:var(--shadow);padding:26px;animation:rise .55s cubic-bezier(.2,.8,.2,1) both}
@keyframes rise{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}

/* brand */
.logo{width:46px;height:46px;border-radius:14px;display:grid;place-items:center;font-weight:800;font-size:24px;color:var(--ink);background:linear-gradient(135deg,var(--accent2),var(--accent));box-shadow:0 8px 22px -6px rgba(247,147,26,.65)}

/* auth */
.auth{min-height:100vh;display:grid;place-items:center;padding:28px 16px}
.auth .card{width:100%;max-width:440px;padding:34px 32px}
.auth.wide .card{max-width:520px}
.auth h1{font-size:1.65rem;margin:18px 0 6px}
.auth .lead{color:var(--muted);margin:0 0 24px}
.auth .foot{text-align:center;margin-top:22px;color:var(--muted);font-size:.92rem}

/* forms */
.field{margin-bottom:16px}
.field>label{display:block;font-size:.82rem;font-weight:600;margin-bottom:7px}
.field .hint{font-size:.8rem;color:var(--muted);margin-top:6px}
.input{position:relative;display:flex}
input[type=text],input[type=password],input:not([type]),select{width:100%;padding:13px 15px;font:inherit;color:var(--text);background:var(--soft);border:1px solid transparent;border-radius:14px;outline:none;transition:border-color .2s,box-shadow .2s,background .2s}
input:focus,select:focus{border-color:var(--accent);box-shadow:0 0 0 4px rgba(247,147,26,.18);background:var(--solid)}
.input input{padding-right:62px}
.eye{position:absolute;right:8px;top:50%;transform:translateY(-50%);border:0;background:none;color:var(--muted);font:600 .78rem inherit;cursor:pointer;padding:8px 10px;border-radius:10px}
.eye:hover{color:var(--text);background:var(--soft)}
.err-text{color:var(--err);font-size:.83rem;margin-top:6px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;width:100%;padding:14px 20px;border:0;border-radius:14px;font:700 1rem inherit;color:var(--ink);cursor:pointer;background:linear-gradient(135deg,var(--accent2),var(--accent));box-shadow:0 10px 24px -10px rgba(247,147,26,.8);transition:transform .15s,box-shadow .2s,filter .2s}
.btn:hover{transform:translateY(-1px);filter:brightness(1.04);box-shadow:0 14px 28px -10px rgba(247,147,26,.9)}
.btn:active{transform:translateY(0)}
.btn[disabled]{opacity:.7;cursor:progress}
.btn.ghost{background:var(--soft);color:var(--text);box-shadow:none;font-weight:600}
.btn.auto{width:auto}
.spin{width:16px;height:16px;border:2.5px solid rgba(26,18,4,.25);border-top-color:var(--ink);border-radius:50%;animation:sp .7s linear infinite}
@keyframes sp{to{transform:rotate(360deg)}}
.alert{padding:13px 16px;border-radius:14px;font-size:.92rem;margin-bottom:18px;border:1px solid var(--line);word-break:break-word}
.alert.ok{background:rgba(15,157,88,.12);color:var(--ok);border-color:rgba(15,157,88,.25)}
.alert.err{background:rgba(217,48,37,.1);color:var(--err);border-color:rgba(217,48,37,.25)}
.alert.warn{background:rgba(247,147,26,.12);color:inherit;border-color:rgba(247,147,26,.3)}
.meter{height:6px;border-radius:99px;background:var(--soft);overflow:hidden;margin-top:9px}
.meter i{display:block;height:100%;width:0;border-radius:99px;transition:width .3s,background .3s}
.check{display:flex;gap:11px;align-items:flex-start;font-size:.85rem;color:var(--muted);margin:4px 0 20px;cursor:pointer}
.check input{margin-top:3px;accent-color:var(--accent);width:17px;height:17px;flex:none}

/* dashboard */
.shell{max-width:1060px;margin:0 auto;padding:22px 18px 60px}
.nav{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:26px}
.nav .left,.nav .right{display:flex;align-items:center;gap:12px}
.nav .logo{width:40px;height:40px;font-size:20px;border-radius:12px}
.pill{display:inline-flex;align-items:center;gap:9px;padding:6px 14px 6px 6px;border-radius:99px;background:var(--card);border:1px solid var(--line);font-weight:600;font-size:.9rem}
.avatar{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;font-size:.8rem;font-weight:800;color:var(--ink);background:linear-gradient(135deg,var(--accent2),var(--accent))}
.badge{padding:4px 11px;border-radius:99px;font-size:.72rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;background:rgba(15,157,88,.14);color:var(--ok)}
.badge.test{background:rgba(247,147,26,.16);color:var(--accent)}
.out{border:0;background:var(--soft);color:var(--text);padding:9px 15px;border-radius:99px;font:600 .88rem inherit;cursor:pointer}
.out:hover{background:var(--line)}
.grid{display:grid;grid-template-columns:1.08fr .92fr;gap:22px;align-items:start}
.stack{display:grid;gap:22px}
@media(max-width:880px){.grid{grid-template-columns:1fr}.pill span.nm{display:none}.pill{padding-right:6px}}
.hero{position:relative;overflow:hidden;color:#fff;border:0;padding:30px;background:linear-gradient(135deg,#ff9f1c 0%,#f7931a 45%,#c96b00 100%);box-shadow:0 24px 60px -20px rgba(247,147,26,.7)}
.hero::after{content:"₿";position:absolute;right:-20px;bottom:-70px;font-size:260px;font-weight:800;opacity:.12;line-height:1}
.eyebrow{font-size:.78rem;font-weight:700;letter-spacing:.09em;text-transform:uppercase;opacity:.85}
.amount{font-size:clamp(2rem,6vw,2.9rem);font-weight:800;letter-spacing:-.03em;margin:8px 0 4px;font-variant-numeric:tabular-nums}
.amount small{font-size:1.1rem;font-weight:700;opacity:.85;margin-left:4px}
.hero .sub{opacity:.9;font-size:.92rem}
.hero .chip{display:inline-block;margin-top:14px;padding:5px 12px;border-radius:99px;background:rgba(255,255,255,.2);font-size:.8rem;font-weight:600}
.tabs{display:grid;grid-template-columns:1fr 1fr;gap:5px;padding:5px;background:var(--soft);border-radius:15px;margin-bottom:22px}
.tabs button{border:0;background:none;padding:11px;border-radius:11px;font:700 .92rem inherit;color:var(--muted);cursor:pointer;transition:.2s}
.tabs button.on{background:var(--solid);color:var(--text);box-shadow:0 4px 14px -6px rgba(0,0,0,.25)}
[data-panel]{display:none}[data-panel].on{display:block;animation:rise .35s both}
.fees{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.fees input{position:absolute;opacity:0;pointer-events:none}
.fees label{display:block;text-align:center;padding:10px 6px;border-radius:13px;background:var(--soft);border:1px solid transparent;cursor:pointer;font-size:.8rem;color:var(--muted);transition:.2s}
.fees label b{display:block;color:var(--text);font-size:.92rem}
.fees input:checked+label{border-color:var(--accent);background:rgba(247,147,26,.12)}
.fees input:focus-visible+label{box-shadow:0 0 0 4px rgba(247,147,26,.2)}
.addr{padding:16px;border-radius:16px;background:var(--soft);font-size:.95rem;line-height:1.6;margin:6px 0 14px}
.sec-title{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
.sec-title h2{font-size:1.1rem}
.tx{display:flex;align-items:center;gap:14px;padding:13px 0;border-top:1px solid var(--line)}
.tx:first-of-type{border-top:0}
.ico{width:40px;height:40px;border-radius:13px;display:grid;place-items:center;flex:none;font-size:1.1rem;font-weight:800}
.ico.in{background:rgba(15,157,88,.14);color:var(--ok)}.ico.outg{background:rgba(217,48,37,.12);color:var(--err)}
.tx .mid{flex:1;min-width:0}.tx .t{font-weight:600}.tx .id{font-size:.78rem;color:var(--muted)}
.tx .val{font-weight:700;font-variant-numeric:tabular-nums;text-align:right}
.tx .val.in{color:var(--ok)}.tx .val.outg{color:var(--err)}
.empty{text-align:center;padding:30px 10px;color:var(--muted)}

/* confirm */
.rows{margin:6px 0 22px}.rows .r{display:flex;justify-content:space-between;gap:20px;padding:12px 0;border-top:1px solid var(--line)}
.rows .r:first-child{border-top:0}.rows .r span:first-child{color:var(--muted)}.rows .r span:last-child{font-weight:600;text-align:right}
.btns{display:grid;grid-template-columns:1fr 1.4fr;gap:12px}.btns .btn{text-decoration:none}
</style>
</head>
<body>
<div class="bg"></div>
@yield('content')
<script>
document.querySelectorAll('[data-toggle]').forEach(function(b){b.addEventListener('click',function(){
  var i=b.parentElement.querySelector('input');var show=i.type==='password';i.type=show?'text':'password';b.textContent=show?'Hide':'Show';});});
document.querySelectorAll('[data-copy]').forEach(function(b){b.addEventListener('click',function(){
  var t=document.querySelector(b.dataset.copy).innerText.trim();var old=b.innerHTML;
  (navigator.clipboard?navigator.clipboard.writeText(t):Promise.reject()).catch(function(){var a=document.createElement('textarea');a.value=t;document.body.appendChild(a);a.select();document.execCommand('copy');a.remove();})
  .finally(function(){b.textContent='Copied ✓';setTimeout(function(){b.innerHTML=old},1600)});});});
document.querySelectorAll('form[data-busy]').forEach(function(f){f.addEventListener('submit',function(){
  var b=f.querySelector('button[type=submit],button:not([type])');if(!b||b.dataset.skip)return;
  setTimeout(function(){b.disabled=true;b.innerHTML='<span class="spin"></span> '+(f.dataset.busy||'Working…')},0);});});
</script>
@stack('scripts')
</body>
</html>
