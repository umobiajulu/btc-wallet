<script setup>
import { ref, reactive, computed, watch } from 'vue'
import * as W from './lib/wallet'
import Pw from './components/Pw.vue'

const db = { get: () => { try { return JSON.parse(localStorage.getItem('btc_wallets') || '{}') } catch { return {} } }, set: (v) => localStorage.setItem('btc_wallets', JSON.stringify(v)) }
const view = ref(Object.keys(db.get()).length ? 'login' : 'create')
const busy = ref(''), err = ref(''), ok = ref('')
const f = reactive({ net: 'mainnet', name: '', phrase: '', phrase2: '', pw: '', pw2: '', ack: false })
const l = reactive({ name: '', pw: '' })
const s = reactive({ to: '', amt: '', tier: 'medium', pw: '' })
const wallet = ref(null), chain = ref(null), rates = ref(null), ratesAt = ref(0), prev = ref(null), failed = ref(false)
const tab = ref('send'), cur = ref('USD')
const btc = (n) => (n / 1e8).toFixed(8)
const test = computed(() => wallet.value?.network !== 'mainnet')
const F = { USD: new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }), NGN: new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }) }
const fiat = (c, sats) => F[c].format((sats / 1e8) * rates.value[c])
const ready = computed(() => !test.value && rates.value)

let tm
watch([err, ok], () => { clearTimeout(tm); if (err.value || ok.value) tm = setTimeout(() => (err.value = ok.value = ''), 7000) })

const score = (v) => { let n = 0; if (v.length >= 12) n++; if (v.length >= 20) n++; if (v.length >= 30) n++; if (/[a-z]/.test(v) && /[A-Z]/.test(v)) n++; if (/\d/.test(v)) n++; if (/[^A-Za-z0-9]/.test(v)) n++; if (/(.)\1\1/.test(v)) n--; return Math.max(0, Math.min(6, n)) }
const L = ['Very weak', 'Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Excellent'], C = ['#d93025', '#d93025', '#f7931a', '#f7931a', '#9bb800', '#0f9d58', '#0f9d58']
const meter = (v) => ({ w: v ? ((score(v) + 1) / 7) * 100 : 0, c: C[score(v)], t: v ? L[score(v)] + ' — ' : '' })
const chk = ref('')
watch(() => f.phrase, async (v) => { chk.value = v ? await W.phraseCheck(v) : '' })
const canonLen = computed(() => W.canon(f.phrase).length)
const odd = computed(() => [...new Set(W.canon(f.phrase).match(/[^\x20-\x7e]/g) || [])].join(' '))
const m1 = computed(() => meter(f.phrase)), m2 = computed(() => meter(f.pw))

const run = async (label, fn) => {
  err.value = ok.value = ''; busy.value = label
  try { await fn() } catch (e) { err.value = e.message || String(e) } finally { busy.value = '' }
}
const create = () => run('Creating & encrypting…', async () => {
  const name = f.name.trim()
  if (!name) throw new Error('Username is required')
  if (f.phrase.length < 25) throw new Error('Seed phrase must be at least 25 characters')
  if (f.phrase !== f.phrase2) throw new Error('Seed phrases do not match')
  if (f.pw.length < 10) throw new Error('Password must be at least 10 characters')
  if (f.pw !== f.pw2) throw new Error('Passwords do not match')
  if (!f.ack) throw new Error('Please confirm you saved your username and seed phrase')
  const all = db.get(), id = f.net + ':' + name.toLowerCase()
  if (all[id]) throw new Error('That username already exists on this device')
  const priv = await W.deriveKey(name, f.phrase, f.net)
  all[id] = { name, network: f.net, address: W.addressOf(priv, f.net), box: await W.seal(priv, f.pw) }
  priv.fill(0); db.set(all)
  Object.assign(f, { phrase: '', phrase2: '', pw: '', pw2: '', ack: false })
  l.name = name; view.value = 'login'; ok.value = 'Wallet created. Log in to continue.'
})
const login = () => run('Unlocking…', async () => {
  const rec = Object.values(db.get()).find((w) => w.name.toLowerCase() === l.name.trim().toLowerCase())
  if (!rec) throw new Error('No wallet with that username on this device')
  ;(await W.unseal(rec.box, l.pw)).fill(0)
  l.pw = ''; wallet.value = rec; chain.value = null; view.value = 'dash'; refresh()
})
const refresh = async () => {
  failed.value = false
  try {
    chain.value = await W.loadChain(wallet.value.address, wallet.value.network)
  } catch (e) { err.value = 'Could not load balance: ' + e.message }
  if (!test.value) { rates.value = (await W.loadRates()) || rates.value; ratesAt.value = Date.now(); failed.value = !rates.value }
}
const review = () => run('Signing…', async () => {
  if (!/^\d+(\.\d{1,8})?$/.test(s.amt.trim())) throw new Error('Enter a valid BTC amount (max 8 decimals)')
  const sats = Math.round(parseFloat(s.amt) * 1e8)
  if (sats < 546) throw new Error('Amount is below the dust limit (546 sats)')
  ;(await W.unseal(wallet.value.box, s.pw)).fill(0)
  prev.value = await W.buildTx({ addr: wallet.value.address, net: wallet.value.network, to: s.to.trim(), sats, rate: chain.value.fees[s.tier] })
  view.value = 'review'
})
const send = () => run('Broadcasting…', async () => {
  const priv = await W.unseal(wallet.value.box, s.pw)
  try {
    const id = await W.signAndSend(prev.value, priv, wallet.value.address, wallet.value.network)
    Object.assign(s, { to: '', amt: '', pw: '' }); prev.value = null; view.value = 'dash'
    ok.value = 'Sent ✓ ' + id.slice(0, 12) + '…'; refresh()
  } finally { priv.fill(0) }
})
const confirmWipe = ref(false)
const wipe = () => {
  localStorage.removeItem('btc_wallets')
  confirmWipe.value = false; l.name = l.pw = ''; view.value = 'create'
  ok.value = 'All accounts removed from this device.'
}
const logout = () => { wallet.value = chain.value = prev.value = null; s.pw = ''; err.value = ok.value = ''; view.value = 'login' }
const copied = ref(false)
const copy = () => navigator.clipboard?.writeText(wallet.value.address).then(() => { copied.value = true; setTimeout(() => (copied.value = false), 1600) })
const time = (t) => new Date(t * 1000).toLocaleString('en', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
</script>

<template>
  <div class="bg"></div>
  <Transition name="t"><div v-if="err || ok" class="toast" :class="err ? 'err' : 'ok'" @click="err = ok = ''">{{ err ? err : '✓ ' + ok }}</div></Transition>

  <Transition name="pg" mode="out-in">
    <!-- CREATE -->
    <main v-if="view === 'create'" key="create" class="auth wide"><div class="card">
      <div class="logo">₿</div>
      <h1>Create your wallet</h1>
      <p class="lead">Set a username and password, and add a seed phrase of your choice. <span class="badge" :class="{ test: f.net !== 'mainnet' }">{{ f.net }}</span></p>
      <div class="alert warn small">Your seed phrase is used <strong>once</strong> to create the key, which is then stored encrypted by your password. It stays your recovery backup, so keep it with your username. Make it genuinely unpredictable — no quotes, lyrics or dates.</div>
      <form autocomplete="off" @submit.prevent="create">
        <div class="field"><label>Network</label><select v-model="f.net"><option value="mainnet">Mainnet</option><option value="testnet">Testnet</option></select></div>
        <div class="field"><label>Username</label><input v-model="f.name" placeholder="control" autocapitalize="none" autocorrect="off" spellcheck="false" required><div class="hint">Also your wallet name and part of key derivation — write it down.</div></div>
        <div class="field"><label>Seed phrase</label><Pw v-model="f.phrase" />
          <div class="meter"><i :style="{ width: m1.w + '%', background: m1.c }"></i></div>
          <div class="hint">{{ m1.t }}Any text, case-sensitive, at least 25 characters.</div>
          <div v-if="f.phrase" class="hint">Check code <b class="mono">{{ chk }}</b> · {{ canonLen }} characters. It must be identical on every device you type this on.</div>
          <div v-if="odd" class="alert warn small" style="margin-top:8px">Unusual characters found: <b>{{ odd }}</b>. Phone keyboards insert look-alike symbols, and these would make your keys differ between devices. Retype them with plain keys.</div></div>
        <div class="field"><label>Confirm seed phrase</label><Pw v-model="f.phrase2" /></div>
        <div class="field"><label>Password</label><Pw v-model="f.pw" />
          <div class="meter"><i :style="{ width: m2.w + '%', background: m2.c }"></i></div>
          <div class="hint">{{ m2.t }}Used to log in and sign. Minimum 10 characters.</div></div>
        <div class="field"><label>Confirm password</label><Pw v-model="f.pw2" /></div>
        <label class="check"><input v-model="f.ack" type="checkbox" required> I've saved my username and seed phrase. I understand that without them (and the password) my coins cannot be recovered.</label>
        <button class="btn" :disabled="!!busy"><span v-if="busy" class="spin"></span>{{ busy || 'Create wallet' }}</button>
      </form>
      <div v-if="Object.keys(db.get()).length" class="foot">Already have a wallet? <a @click="view = 'login'">Log in</a></div>
    </div></main>

    <!-- LOGIN -->
    <main v-else-if="view === 'login'" key="login" class="auth"><div class="card">
      <div class="logo">₿</div>
      <h1>Welcome back</h1>
      <p class="lead">Unlock your local wallet with your username and password.</p>
      <form @submit.prevent="login">
        <div class="field"><label>Username</label><input v-model="l.name" placeholder="control" autocapitalize="none" autocorrect="off" spellcheck="false" required></div>
        <div class="field"><label>Password</label><Pw v-model="l.pw" /></div>
        <button class="btn" :disabled="!!busy"><span v-if="busy" class="spin"></span>{{ busy || 'Unlock wallet' }}</button>
      </form>
      <div class="foot">New here? <a @click="view = 'create'">Create a wallet</a></div>
      <div class="wipe">
        <button v-if="!confirmWipe" type="button" class="wipe-btn" @click="confirmWipe = true">🗑 Clear all accounts from this device</button>
        <div v-else class="wipe-box">
          <b>Erase every wallet on this device?</b>
          <p>This deletes the encrypted keys stored in this browser. Your coins are not lost, but you can only restore them with your username, seed phrase and network.</p>
          <div class="btns"><button type="button" class="btn ghost" @click="confirmWipe = false">Cancel</button><button type="button" class="btn danger" @click="wipe">Yes, erase all</button></div>
        </div>
      </div>
    </div></main>

    <!-- REVIEW -->
    <main v-else-if="view === 'review'" key="review" class="auth wide"><div class="card">
      <div class="logo">₿</div>
      <h1>Review &amp; send</h1>
      <p class="lead">Check everything carefully — Bitcoin transactions can't be reversed.</p>
      <div class="rows">
        <div class="r"><span>To</span><span class="mono small" style="max-width:300px">{{ prev.to }}</span></div>
        <div class="r"><span>Amount</span><span style="font-size:1.15rem">{{ btc(prev.sats) }} BTC</span></div>
        <div class="r"><span>Network fee</span><span>{{ btc(prev.fee) }} BTC</span></div>
        <div class="r"><span>Change back to you</span><span>{{ btc(prev.change) }} BTC</span></div>
        <div class="r"><span>Total leaving wallet</span><span>{{ btc(prev.sats + prev.fee) }} BTC</span></div>
      </div>
      <div class="btns">
        <button class="btn ghost" :disabled="!!busy" @click="view = 'dash'">Cancel</button>
        <button class="btn" :disabled="!!busy" @click="send"><span v-if="busy" class="spin"></span>{{ busy || 'Sign & broadcast' }}</button>
      </div>
    </div></main>

    <!-- DASHBOARD -->
    <div v-else key="dash" class="shell">
      <header class="nav">
        <div class="left"><div class="logo">₿</div></div>
        <div class="right">
          <span class="badge" :class="{ test }">{{ wallet.network }}</span>
          <div class="pill"><div class="avatar">{{ wallet.name[0].toUpperCase() }}</div><span class="nm">{{ wallet.name }}</span></div>
          <button class="out" @click="logout">Log out</button>
        </div>
      </header>

      <div class="grid">
        <div class="stack">
          <section class="card hero">
            <div class="hero-top">
              <div class="eyebrow">Total balance</div>
              <div v-if="ready" class="cur"><button v-for="c in ['BTC', 'USD', 'NGN']" :key="c" :class="{ on: cur === c }" @click="cur = c">{{ c }}</button></div>
            </div>
            <template v-if="chain">
              <div :key="cur" class="amount">
                <template v-if="cur === 'BTC' || !ready">{{ btc(chain.confirmed) }}<small>BTC</small></template>
                <template v-else>{{ fiat(cur, chain.confirmed) }}</template>
              </div>
              <div class="sub">{{ cur === 'BTC' || !ready ? chain.confirmed.toLocaleString('en-US') + ' sats' : '≈ ' + btc(chain.confirmed) + ' BTC' }}</div>
              <div v-if="ready" class="fiat-row"><template v-for="c in ['BTC', 'USD', 'NGN']" :key="c"><span v-if="c !== cur">{{ c === 'BTC' ? btc(chain.confirmed) + ' BTC' : fiat(c, chain.confirmed) }}</span></template></div>
              <span v-if="chain.pending" class="chip">{{ chain.pending > 0 ? '+' : '−' }}{{ btc(Math.abs(chain.pending)) }} BTC pending</span>
            </template>
            <div v-else class="amount"><span class="skel"></span></div>
            <div class="rate">
              <template v-if="test">Testnet coins have no market value, so there is no USD or NGN price.</template>
              <template v-else-if="rates">1 BTC = {{ F.USD.format(rates.USD) }} · {{ F.NGN.format(rates.NGN) }} · updated {{ new Date(ratesAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</template>
              <template v-else-if="failed">Couldn't load prices. <a style="color:#fff;text-decoration:underline" @click="refresh">Retry</a></template>
              <template v-else>Loading rates…</template>
            </div>
          </section>

          <section class="card">
            <div class="sec-title"><h2>Recent activity</h2><a class="small" @click="refresh">Refresh</a></div>
            <div v-if="!chain"><div class="skel" style="width:100%;height:44px;margin-bottom:10px"></div><div class="skel" style="width:100%;height:44px"></div></div>
            <div v-for="(h, i) in chain?.history" :key="h.txid" class="tx" :style="{ animationDelay: i * 50 + 'ms' }">
              <div class="ico" :class="h.net >= 0 ? 'in' : 'outg'">{{ h.net >= 0 ? '↓' : '↑' }}</div>
              <div class="mid">
                <div class="t">{{ h.net >= 0 ? 'Received' : 'Sent' }} <span v-if="!h.confirmed" class="badge test" style="margin-left:6px">Pending</span></div>
                <div class="id"><a :href="W.explorer(wallet.network) + h.txid" target="_blank" rel="noopener noreferrer">{{ h.txid.slice(0, 10) }}…{{ h.txid.slice(-6) }}</a><template v-if="h.time"> · {{ time(h.time) }}</template></div>
              </div>
              <div class="val" :class="h.net >= 0 ? 'in' : 'outg'">{{ h.net >= 0 ? '+' : '' }}{{ btc(h.net) }}</div>
            </div>
            <div v-if="chain && !chain.history.length" class="empty">No transactions yet.<br><span class="small">Receive some coins to get started.</span></div>
          </section>
        </div>

        <section class="card">
          <div class="tabs"><button :class="{ on: tab === 'send' }" @click="tab = 'send'">↑ Send</button><button :class="{ on: tab === 'receive' }" @click="tab = 'receive'">↓ Receive</button></div>

          <form v-if="tab === 'send'" key="s" class="panel" autocomplete="off" @submit.prevent="review">
            <div class="field"><label>Recipient address</label><input v-model="s.to" :placeholder="test ? 'tb1q…' : 'bc1q…'" spellcheck="false" required></div>
            <div class="field"><label>Amount (BTC)</label><input v-model="s.amt" placeholder="0.00010000" inputmode="decimal" required></div>
            <div v-if="chain" class="field"><label>Network fee</label>
              <div class="fees">
                <div v-for="[k, n, eta] in [['slow', 'Slow', '~1 hr'], ['medium', 'Normal', '~30 min'], ['fast', 'Fast', '~10 min']]" :key="k">
                  <input :id="'f-' + k" v-model="s.tier" name="fee" type="radio" :value="k"><label :for="'f-' + k"><b>{{ n }}</b>{{ chain.fees[k] }} sat/vB · {{ eta }}</label>
                </div>
              </div>
            </div>
            <div class="field"><label>Password</label><Pw v-model="s.pw" /><div class="hint">Needed to sign each transaction. You'll review it before it's sent.</div></div>
            <button class="btn" :disabled="!!busy || !chain"><span v-if="busy" class="spin"></span>{{ busy || 'Review transaction →' }}</button>
          </form>

          <div v-else key="r" class="panel">
            <p class="muted" style="margin-top:0">Send only {{ test ? 'testnet coins' : 'Bitcoin (BTC)' }} to this address.</p>
            <div class="addr mono">{{ wallet.address }}</div>
            <button class="btn ghost" @click="copy">{{ copied ? 'Copied ✓' : 'Copy address' }}</button>
            <div class="alert warn small" style="margin:16px 0 0">Always compare the first and last characters of the address on the sender's side before sending.</div>
          </div>
        </section>
      </div>
    </div>
  </Transition>
</template>
