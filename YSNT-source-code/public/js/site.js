(() => {
const S = { get(k, d) { try { return JSON.parse(localStorage.getItem(k)) ?? d } catch { return d } }, set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)) } catch {} } };
const vnd = n => Math.round(n).toLocaleString('vi-VN') + 'đ';
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const csrf = () => document.querySelector('meta[name=csrf-token]').content;
let cart = S.get('ysnt_cart2', []);
const saveCart = () => { S.set('ysnt_cart2', cart); paintCount(); };
const paintCount = () => document.querySelectorAll('[data-cart-count]').forEach(e => e.textContent = cart.reduce((s, i) => s + i.q, 0));
const toast = t => { const d = document.createElement('div'); d.className = 'toast'; d.setAttribute('role', 'status'); d.textContent = t; document.body.appendChild(d); setTimeout(() => d.remove(), 1800); };
const add = (id, q) => { const it = cart.find(x => x.id === id); if (it) it.q = Math.min(99, it.q + q); else cart.push({ id, q }); saveCart(); };
window.YS = { S, vnd, esc, toast, add, get cart() { return cart }, set cart(v) { cart = v; saveCart(); } };
paintCount();

/* product page picker */
const pdEl = document.getElementById('pd-data');
if (pdEl) {
  const P = JSON.parse(pdEl.textContent), root = document.getElementById('pd-pick');
  const parts = P.v.map(v => v.n.split(' – '));
  const two = P.v.length > 1 && parts.every(x => x.length === 2);
  let sel = Math.max(0, P.v.findIndex(v => v.id === P.start)); if (!P.v[sel].ok) sel = Math.max(0, P.v.findIndex(v => v.ok));
  let qty = 1;
  const gram = v => { if (!P.gram) return 0; const m = v.n.match(/(\d+)\s*g\b/i); return m ? +m[1] : 0; };
  function paint() {
    const cur = P.v[sel], cp = parts[sel], g = gram(cur);
    document.querySelectorAll('[data-desc]').forEach(el => { el.hidden = cur.d == null ? true : +el.dataset.desc !== cur.d; });
    let opts = '';
    if (two) {
      const A = [...new Set(parts.map(x => x[0]))], B = [...new Set(parts.map(x => x[1]))];
      const pick = (a, b) => P.v.findIndex(v => v.n === a + ' – ' + b);
      opts = `<div class="opt"><h3>${esc(P.dim1)}</h3><div class="opts">${A.map(a => { let k = pick(a, cp[1]); if (k < 0) k = parts.findIndex(x => x[0] === a); return `<button class="o" data-k="${k}" aria-pressed="${a === cp[0]}">${esc(a)}</button>`; }).join('')}</div></div>
        <div class="opt"><h3>Quy cách</h3><div class="opts">${B.map(b => { const k = pick(cp[0], b), v = P.v[k]; return `<button class="o" data-k="${k}" ${k < 0 || !v.ok ? 'disabled' : ''} aria-pressed="${b === cp[1]}">${esc(b)}<small>${k >= 0 ? vnd(v.p) : 'Không có'}</small></button>`; }).join('')}</div></div>`;
    } else if (P.v.length > 1) {
      opts = `<div class="opt"><h3>${esc(P.dim1)}</h3><div class="opts">${P.v.map((v, k) => { const gg = gram(v); return `<button class="o" data-k="${k}" ${v.ok ? '' : 'disabled'} aria-pressed="${k === sel}">${esc(v.n)}<small>${vnd(v.p)}${gg ? ' · ' + vnd(v.p / gg) + '/g' : ''}</small></button>`; }).join('')}</div></div>`;
    }
    root.innerHTML = `<div class="price">${vnd(cur.p)}</div>
      ${g ? `<p class="pergram">Khoảng ${vnd(cur.p / g)} cho mỗi gram yến.</p>` : ''}
      ${!cur.ok ? '<p class="pergram" style="color:var(--red)">Quy cách này đang tạm hết hàng.</p>' : ''}
      ${opts}
      <div class="opt"><h3>Số lượng</h3><div class="qty"><button data-q="-1" aria-label="Bớt">−</button><span>${qty}</span><button data-q="1" aria-label="Thêm">+</button></div></div>
      <div class="buy"><button class="btn btn-line" data-add ${cur.ok ? '' : 'disabled'}>Thêm vào giỏ</button><button class="btn btn-red" data-buy ${cur.ok ? '' : 'disabled'}>Mua ngay · ${vnd(cur.p * qty)}</button></div>
      <div class="facts"><div><span>Mã sản phẩm</span><b>${esc(cur.sku || '')}</b></div><div><span>Giao hàng</span><b>Toàn quốc, phí báo khi xác nhận đơn</b></div><div><span>Mua tại chỗ</span><b>${esc(window.YS_SHOWROOM || '01 Nguyễn Thiện Thuật, Nha Trang')}</b></div></div>`;
  }
  root.addEventListener('click', e => {
    const b = e.target.closest('button'); if (!b) return;
    if (b.dataset.k != null) { sel = +b.dataset.k; paint(); }
    else if (b.dataset.q) { qty = Math.min(99, Math.max(1, qty + +b.dataset.q)); paint(); }
    else if (b.hasAttribute('data-add')) { add(P.v[sel].id, qty); toast('Đã thêm vào giỏ'); }
    else if (b.hasAttribute('data-buy')) { add(P.v[sel].id, qty); location.href = '/gio-hang'; }
  });
  paint();
}

/* cart + checkout */
const cartRoot = document.getElementById('cart-root'), coRoot = document.getElementById('co-root');
let code = S.get('ysnt_code', window.YS_REF || '') || '', disc = 0, refState = '', V = {};
async function loadVariants() {
  if (!cart.length) return;
  const r = await fetch('/api/variants?ids=' + cart.map(i => i.id).join(','), { headers: { Accept: 'application/json' } });
  (await r.json()).forEach(v => V[v.id] = v);
  const before = cart.length; cart = cart.filter(i => V[i.id]); if (cart.length !== before) saveCart();
}
async function checkRef() {
  if (!code) { disc = 0; refState = ''; return; }
  try { const j = await (await fetch('/api/ref/' + encodeURIComponent(code))).json(); disc = j.valid ? j.discount_percent : 0; refState = j.valid ? 'ok' : 'unknown'; } catch { refState = ''; }
}
const lines = () => cart.map(i => ({ i, v: V[i.id], t: V[i.id].p * i.q }));
const totals = () => { const sub = lines().reduce((s, l) => s + l.t, 0), d = Math.round(sub * disc / 100); return { sub, d, tot: sub - d }; };
const refMsg = () => refState === 'ok' ? (disc ? `Mã hợp lệ, bạn được giảm ${disc}%.` : 'Mã hợp lệ. Đơn sẽ ghi nhận cho người giới thiệu.') : refState === 'unknown' ? 'Mã này chưa có trong hệ thống. Đơn vẫn đặt được, mã được ghi lại để đối soát.' : 'Mã của nhân viên, hướng dẫn viên hoặc người đã giới thiệu bạn, nếu có.';
const sumBox = T => `<div class="sum"><div><span>Tạm tính</span><b>${vnd(T.sub)}</b></div>${T.d ? `<div><span>Giảm ${disc}% theo mã giới thiệu</span><b>−${vnd(T.d)}</b></div>` : ''}<div><span>Phí giao hàng</span><span>Báo khi xác nhận đơn</span></div><div class="tot"><span>Tổng tiền hàng</span><span>${vnd(T.tot)}</span></div></div>`;
function paintCart() {
  if (!cart.length) { cartRoot.innerHTML = `<div class="empty"><p>Giỏ hàng đang trống.</p><a class="btn btn-red" href="/san-pham">Xem sản phẩm</a></div>`; return; }
  const T = totals();
  cartRoot.innerHTML = `<div class="two"><div>${lines().map((l, k) => `<div class="row"><img src="${esc(l.v.img)}" alt=""><div><a class="t" href="/san-pham/${esc(l.v.slug)}">${esc(l.v.product)}</a><div class="v">${esc(l.v.n)} · ${vnd(l.v.p)}</div>${l.v.ok ? '' : '<div class="v" style="color:var(--red)">Tạm hết hàng, vui lòng bỏ khỏi giỏ</div>'}<button class="rm" data-rm="${k}">Bỏ khỏi giỏ</button></div>
      <div style="text-align:right"><div class="qty"><button data-d="${k}" data-x="-1" aria-label="Bớt">−</button><span>${l.i.q}</span><button data-d="${k}" data-x="1" aria-label="Thêm">+</button></div><div style="margin-top:6px;font-weight:700">${vnd(l.t)}</div></div></div>`).join('')}</div>
    <aside class="box"><div class="field"><label for="code">Mã giới thiệu</label><input id="code" value="${esc(code)}" placeholder="Nếu có"></div><p class="note" style="margin-top:-6px">${refMsg()}</p>${sumBox(T)}
      <a class="btn btn-red" style="width:100%;margin-top:14px" href="/thanh-toan" ${lines().some(l => !l.v.ok) ? 'aria-disabled="true" onclick="return false"' : ''}>Đặt hàng</a></aside></div>`;
}
if (cartRoot) {
  (async () => { await loadVariants(); await checkRef(); paintCart(); })();
  cartRoot.addEventListener('click', e => {
    const b = e.target.closest('button'); if (!b) return;
    if (b.dataset.rm != null) { cart.splice(+b.dataset.rm, 1); saveCart(); paintCart(); }
    if (b.dataset.d != null) { const it = cart[+b.dataset.d]; it.q = Math.min(99, it.q + +b.dataset.x); if (it.q < 1) cart.splice(+b.dataset.d, 1); saveCart(); paintCart(); }
  });
  cartRoot.addEventListener('change', async e => { if (e.target.id === 'code') { code = e.target.value.trim().toUpperCase(); S.set('ysnt_code', code); await checkRef(); paintCart(); } });
}

function paintCheckout() {
  if (!cart.length) { location.href = '/gio-hang'; return; }
  const T = totals();
  coRoot.innerHTML = `<div class="two"><form id="co-form" novalidate>
    <h2 class="h2" style="font-size:22px">Thông tin giao hàng</h2><p class="note" style="margin:0 0 16px">Nhân viên gọi xác nhận đơn và báo phí giao hàng trước khi gửi.</p>
    <div class="field"><label for="f1">Họ tên người nhận</label><input id="f1" name="name" autocomplete="name"><span class="err">Nhập họ tên người nhận.</span></div>
    <div class="field"><label for="f2">Số điện thoại</label><input id="f2" name="phone" inputmode="tel" autocomplete="tel"><span class="err">Số điện thoại gồm 10 chữ số, bắt đầu bằng 0.</span></div>
    <div class="field"><label for="f3">Địa chỉ nhận hàng</label><input id="f3" name="addr" autocomplete="street-address"><span class="err">Nhập địa chỉ nhận hàng đầy đủ.</span></div>
    <div class="field"><label for="f4">Ghi chú</label><textarea id="f4" name="note" rows="2" placeholder="Ví dụ: giao giờ hành chính, gói quà"></textarea></div>
    <h2 class="h2" style="font-size:22px;margin-top:8px">Thanh toán</h2>
    <div class="pay">
      <label><input type="radio" name="pay" value="cod" checked><span>Trả tiền khi nhận hàng<small>Kiểm tra hàng rồi mới trả tiền</small></span></label>
      <label><input type="radio" name="pay" value="bank"><span>Chuyển khoản ngân hàng<small>Nhân viên gửi thông tin chuyển khoản khi xác nhận đơn</small></span></label>
      <label><input type="radio" name="pay" value="vnpay"><span>VNPay, MoMo<small>Đang kết nối, nhân viên sẽ gửi link thanh toán</small></span></label>
    </div>
    <p class="note" style="margin-top:14px">Đặt hàng là bạn đồng ý với <a href="/chinh-sach/doi-tra">chính sách đổi trả</a> và <a href="/chinh-sach/bao-mat">chính sách bảo mật</a>.</p>
  </form>
  <aside class="box"><h3 style="margin:0 0 8px">Đơn hàng (${lines().length})</h3>${lines().map(l => `<div style="display:flex;justify-content:space-between;gap:10px;padding:6px 0;font-size:14px"><span>${esc(l.v.product)} <span class="note">· ${esc(l.v.n)} × ${l.i.q}</span></span><b>${vnd(l.t)}</b></div>`).join('')}
    ${code ? `<p class="note">Mã giới thiệu: <b>${esc(code)}</b></p>` : ''}${sumBox(T)}
    <button class="btn btn-red" style="width:100%;margin-top:14px" form="co-form" type="submit">Xác nhận đặt hàng</button></aside></div>`;
  document.getElementById('co-form').addEventListener('submit', submitOrder);
}
async function submitOrder(e) {
  e.preventDefault();
  const f = e.target, rules = { name: v => v.length >= 2, phone: v => /^0\d{9}$/.test(v.replace(/[\s.]/g, '')), addr: v => v.length >= 6 };
  let first = null;
  for (const [k, t] of Object.entries(rules)) { const el = f.elements[k], ok = t(el.value.trim()); el.closest('.field').classList.toggle('bad', !ok); el.oninput = () => el.closest('.field').classList.remove('bad'); if (!ok && !first) first = el; }
  if (first) { first.focus(); return; }
  const btn = document.querySelector('button[form=co-form]'); btn.disabled = true; btn.textContent = 'Đang gửi đơn…';
  const body = { name: f.elements.name.value.trim(), phone: f.elements.phone.value.replace(/[\s.]/g, ''), address: f.elements.addr.value.trim(), note: f.elements.note.value.trim(), pay: f.elements.pay.value, ref: code || null, items: cart.map(i => ({ id: i.id, q: i.q })) };
  try {
    const r = await fetch('/api/orders', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify(body) });
    const j = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(j.message || 'Chưa gửi được đơn. Vui lòng thử lại.');
    sessionStorage.setItem('ysnt_last', JSON.stringify({ ...j, name: body.name, phone: body.phone }));
    cart = []; saveCart(); location.href = '/dat-hang-thanh-cong';
  } catch (err) { btn.disabled = false; btn.textContent = 'Xác nhận đặt hàng'; toast(err.message); }
}
if (coRoot) (async () => { await loadVariants(); await checkRef(); paintCheckout(); })();

const okRoot = document.getElementById('ok-root');
if (okRoot) {
  let o = null; try { o = JSON.parse(sessionStorage.getItem('ysnt_last')); } catch {}
  const pay = { cod: 'Trả tiền khi nhận hàng', bank: 'Chuyển khoản ngân hàng', vnpay: 'VNPay, MoMo' };
  okRoot.innerHTML = o ? `<h1 class="h1">Đã nhận đơn ${esc(o.code)}</h1><p class="lead">Cảm ơn ${esc(o.name)}. Nhân viên sẽ gọi số ${esc(o.phone)} để xác nhận đơn và báo phí giao hàng trong giờ làm việc.</p>
    <div class="box" style="max-width:460px"><div class="sum"><div><span>Tổng tiền hàng</span><b>${vnd(o.total)}</b></div><div><span>Thanh toán</span><span>${pay[o.pay] || ''}</span></div>${o.ref ? `<div><span>Mã giới thiệu</span><span>${esc(o.ref)}</span></div>` : ''}</div></div>
    <p style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap"><a class="btn btn-sea" href="/tra-cuu-don?ma=${encodeURIComponent(o.code)}&sdt=${encodeURIComponent(o.phone)}">Xem trạng thái đơn</a><a class="btn btn-line" href="/san-pham">Tiếp tục mua</a></p>`
    : `<h1 class="h1">Không tìm thấy đơn vừa đặt</h1><p class="lead">Bạn có thể tra cứu bằng mã đơn và số điện thoại.</p><a class="btn btn-sea" href="/tra-cuu-don">Tra cứu đơn</a>`;
  // purchase for Meta pixel / GA4, once per order; eventID = order code matches the server-side Conversions API event
  if (o && !S.get('ysnt_tracked_' + o.code, false)) {
    try { if (window.fbq) fbq('track', 'Purchase', { value: o.total, currency: 'VND' }, { eventID: o.code }); if (window.gtag) gtag('event', 'purchase', { transaction_id: o.code, value: o.total, currency: 'VND' }); } catch {}
    S.set('ysnt_tracked_' + o.code, true);
  }
}

/* home: gift finder */
const hd = document.getElementById('home-data');
if (hd) {
  const DATA = JSON.parse(hd.textContent), root = document.getElementById('ask');
  const WHO = [
    { k: 'bome', t: 'bố mẹ', cats: ['Tổ yến tinh chế', 'Tổ yến chưng sẵn', 'Tổ yến ăn liền', 'Bộ quà tặng'] },
    { k: 'mebau', t: 'mẹ bầu', cats: ['Tổ yến chưng sẵn', 'Tổ yến tinh chế', 'Tổ yến ăn liền'] },
    { k: 'be', t: 'bé nhà mình', cats: ['Sữa chua sấy khô', 'Tổ yến ăn liền', 'Tổ yến chưng sẵn'] },
    { k: 'doitac', t: 'đối tác, sếp', cats: ['Bộ quà tặng', 'Tổ yến tinh chế', 'Tổ yến nguyên tổ'] },
    { k: 'minh', t: 'chính mình', cats: ['Tổ yến ăn liền', 'Súp tổ yến', 'Nước yến thật', 'Cà phê tổ yến', 'Tổ yến chưng sẵn'] },
    { k: 'dulich', t: 'làm quà du lịch', cats: ['Bộ quà tặng', 'Sữa chua sấy khô', 'Tổ yến chưng sẵn', 'Súp tổ yến', 'Tổ yến ăn liền'] },
  ];
  const BUD = [
    { k: 'nho', t: 'dưới 300 nghìn', lo: 0, hi: 300000, at: 250000 },
    { k: 'tr1', t: 'khoảng 1 triệu', lo: 500000, hi: 1500000, at: 1000000 },
    { k: 'tr3', t: '2 đến 5 triệu', lo: 2000000, hi: 5000000, at: 3000000 },
    { k: 'lon', t: 'trên 5 triệu', lo: 5000000, hi: 1e12, at: 7000000 },
  ];
  const WHY = { 'Tổ yến tinh chế': 'Đã làm sạch, chỉ việc chưng', 'Tổ yến nguyên tổ': 'Tổ nguyên, hộp sang để biếu', 'Tổ yến ăn liền': '5 phút là có chén yến', 'Tổ yến chưng sẵn': 'Mở nắp là dùng được', 'Sữa chua sấy khô': 'Viên nhỏ, bé cầm ăn được', 'Súp tổ yến': 'Bữa sáng nhanh, pha là ăn', 'Nước yến thật': 'Uống liền, mang theo tiện', 'Cà phê tổ yến': 'Pha như cà phê gói', 'Bộ quà tặng': 'Đóng hộp sẵn, hợp để biếu' };
  let who = S.get('ysnt_who', 'bome'), bud = S.get('ysnt_budget', 'tr1'), open = '';
  const suggest = () => {
    const W = WHO.find(x => x.k === who), B = BUD.find(x => x.k === bud), out = [];
    W.cats.forEach((c, rank) => DATA.filter(p => p.cat === c).forEach(p => {
      const vs = p.v.filter(v => v.ok && v.p >= B.lo && v.p <= B.hi).sort((a, b) => Math.abs(a.p - B.at) - Math.abs(b.p - B.at));
      if (vs.length) out.push({ p, v: vs[0], rank });
    }));
    out.sort((a, b) => a.rank - b.rank || Math.abs(a.v.p - B.at) - Math.abs(b.v.p - B.at));
    const seen = new Set(); return out.filter(x => (seen.has(x.p.cat) && out.length > 3 ? false : (seen.add(x.p.cat), true))).slice(0, 3);
  };
  function paintAsk() {
    const W = WHO.find(x => x.k === who), B = BUD.find(x => x.k === bud), L = suggest();
    const sheet = open === 'who' ? WHO.map(x => `<button data-who="${x.k}" aria-pressed="${x.k === who}">${x.t}</button>`).join('') : open === 'bud' ? BUD.map(x => `<button data-bud="${x.k}" aria-pressed="${x.k === bud}">${x.t}</button>`).join('') : '';
    root.querySelector('.ask').innerHTML = `<p class="k">Chạm vào chữ gạch dưới để đổi</p>
      <p class="say">Tôi muốn mua yến cho <button data-open="who" aria-expanded="${open === 'who'}">${W.t}</button>, <button data-open="bud" aria-expanded="${open === 'bud'}">${B.t}</button>.</p>
      ${sheet ? `<div class="sheet" role="group">${sheet}</div>` : ''}<p class="count">${L.length ? 'Gợi ý hợp nhất cho lựa chọn của bạn' : ''}</p>`;
    root.querySelector('.picks').innerHTML = L.length ? L.map(({ p, v }) => `<a class="pick" href="/san-pham/${p.slug}?qc=${v.id}"><div class="ph">${p.img ? `<img src="${esc(p.img)}" alt="">` : ''}</div>
      <div class="bd"><span class="why">${esc(WHY[p.cat] || 'Hợp với lựa chọn của bạn')}</span><span class="nm">${esc(p.name)}</span><span class="vn">${esc(v.n === 'Tiêu chuẩn' ? p.cat : v.n)}</span><span class="pr">${vnd(v.p)}</span></div></a>`).join('')
      : `<div class="empty" style="grid-column:1/-1">Chưa có món cho ${esc(W.t)} ở mức ${esc(B.t)}. Chạm vào mức giá để chọn mức khác.</div>`;
  }
  root.addEventListener('click', e => {
    const b = e.target.closest('button'); if (!b) return;
    if (b.dataset.open) open = open === b.dataset.open ? '' : b.dataset.open;
    if (b.dataset.who) { who = b.dataset.who; S.set('ysnt_who', who); open = ''; }
    if (b.dataset.bud) { bud = b.dataset.bud; S.set('ysnt_budget', bud); open = ''; }
    paintAsk();
  });
  paintAsk();
}
})();
