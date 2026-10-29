import { argon2id } from 'hash-wasm'
import { HDKey } from '@scure/bip32'
import * as btc from '@scure/btc-signer'

const enc = new TextEncoder()
const hex = (b) => [...b].map((x) => x.toString(16).padStart(2, '0')).join('')
const unhex = (h) => Uint8Array.from(h.match(/../g), (x) => parseInt(x, 16))
export const NET = (n) => (n === 'mainnet' ? btc.NETWORK : btc.TEST_NETWORK)
const API = (n) => (n === 'mainnet' ? 'https://mempool.space/api' : 'https://mempool.space/testnet4/api')
export const explorer = (n) => (n === 'mainnet' ? 'https://mempool.space/tx/' : 'https://mempool.space/testnet4/tx/')

// Canonical form of typed text, so phone and desktop keyboards give identical keys.
// Decomposes accents (NFKD), turns curly quotes/dashes into plain ones, drops invisible
// characters, collapses whitespace and trims. Case is NOT changed (phrases stay case-sensitive).
export const canon = (s) =>
  s.normalize('NFKD')
    .replace(/[\u2018-\u201B]/g, "'")
    .replace(/[\u201C-\u201E]/g, '"')
    .replace(/[\u2013\u2014\u2212]/g, '-')
    .replace(/[\u200B-\u200D\u2060\uFEFF]/g, '')
    .replace(/\s+/g, ' ')
    .trim()
// Short code to compare a phrase across devices (16 bits only, reveals nothing useful)
export async function phraseCheck(p) {
  const h = new Uint8Array(await crypto.subtle.digest('SHA-256', enc.encode('chk|' + canon(p))))
  return hex(h.slice(0, 2))
}

// ---- Key derivation: identical to the PHP app / rebuild-wallet.php ----
export async function deriveKey(user, phrase, network) {
  const p = canon(phrase)
  const lower = canon(user).toLowerCase()
  const salt = new Uint8Array(
    await crypto.subtle.digest('SHA-256', enc.encode(`btc-local-wallet-v1|${network}|${lower}`))
  ).slice(0, 16)
  const seed = await argon2id({
    password: p, salt, parallelism: 1, iterations: 3, memorySize: 262144, hashLength: 64, outputType: 'binary',
  })
  const node = HDKey.fromMasterSeed(seed).derive(`m/84'/${network === 'mainnet' ? 0 : 1}'/0'/0/0`)
  return node.privateKey
}
export const addressOf = (priv, network) => btc.getAddress('wpkh', priv, NET(network))

// ---- Encrypt the key with the password (PBKDF2 -> AES-GCM) ----
async function aesKey(pw, salt, usage) {
  const m = await crypto.subtle.importKey('raw', enc.encode(pw), 'PBKDF2', false, ['deriveKey'])
  return crypto.subtle.deriveKey(
    { name: 'PBKDF2', salt, iterations: 600000, hash: 'SHA-256' }, m, { name: 'AES-GCM', length: 256 }, false, [usage]
  )
}
export async function seal(priv, pw) {
  const salt = crypto.getRandomValues(new Uint8Array(16)), iv = crypto.getRandomValues(new Uint8Array(12))
  const ct = new Uint8Array(await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, await aesKey(pw, salt, 'encrypt'), priv))
  return { salt: hex(salt), iv: hex(iv), ct: hex(ct) }
}
export async function unseal(box, pw) {
  try {
    return new Uint8Array(await crypto.subtle.decrypt({ name: 'AES-GCM', iv: unhex(box.iv) }, await aesKey(pw, unhex(box.salt), 'decrypt'), unhex(box.ct)))
  } catch { throw new Error('Wrong password') }
}

// ---- Chain data (mempool.space) ----
async function http(u, o) {
  const r = await fetch(u, o)
  if (!r.ok) throw new Error((await r.text()) || r.statusText)
  return r
}
export async function loadChain(addr, net) {
  const b = API(net)
  const [a, h, f] = await Promise.all([
    http(`${b}/address/${addr}`).then((r) => r.json()),
    http(`${b}/address/${addr}/txs`).then((r) => r.json()),
    http(`${b}/v1/fees/recommended`).then((r) => r.json()),
  ])
  const c = a.chain_stats, m = a.mempool_stats
  return {
    confirmed: c.funded_txo_sum - c.spent_txo_sum,
    pending: m.funded_txo_sum - m.spent_txo_sum,
    fees: { slow: f.hourFee, medium: f.halfHourFee, fast: f.fastestFee },
    history: h.slice(0, 10).map((t) => {
      const out = t.vout.filter((o) => o.scriptpubkey_address === addr).reduce((s, o) => s + o.value, 0)
      const inn = t.vin.filter((i) => i.prevout?.scriptpubkey_address === addr).reduce((s, i) => s + i.prevout.value, 0)
      return { txid: t.txid, net: out - inn, confirmed: t.status.confirmed, time: t.status.block_time }
    }),
  }
}
export async function loadRates() {
  try {
    const j = await http('https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd,ngn').then((r) => r.json())
    return { USD: j.bitcoin.usd, NGN: j.bitcoin.ngn }
  } catch { return null }
}

// ---- Build, sign, broadcast ----
export async function buildTx({ addr, net, to, sats, rate }) {
  const N = NET(net)
  try { btc.Address(N).decode(to) } catch { throw new Error('Invalid recipient address for ' + net) }
  const utxos = await http(`${API(net)}/address/${addr}/utxo`).then((r) => r.json())
  utxos.sort((a, b) => b.value - a.value)
  const sel = []; let tot = 0, fee = 0
  for (const u of utxos) {
    sel.push(u); tot += u.value
    fee = Math.ceil((11 + 68 * sel.length + 43 + 31) * rate)
    if (tot >= sats + fee) break
  }
  if (tot < sats + fee) throw new Error('Insufficient funds (amount + fee)')
  let change = tot - sats - fee
  if (change < 294) { fee += change; change = 0 } // dust goes to fee
  return { to, sats, fee, change, sel }
}
export async function signAndSend(p, priv, addr, net) {
  const N = NET(net)
  const script = btc.OutScript.encode(btc.Address(N).decode(addr))
  const tx = new btc.Transaction()
  for (const u of p.sel) tx.addInput({ txid: u.txid, index: u.vout, witnessUtxo: { script, amount: BigInt(u.value) } })
  tx.addOutputAddress(p.to, BigInt(p.sats), N)
  if (p.change) tx.addOutputAddress(addr, BigInt(p.change), N)
  tx.sign(priv); tx.finalize()
  return (await http(`${API(net)}/tx`, { method: 'POST', body: tx.hex })).text()
}
