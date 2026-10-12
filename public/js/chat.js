(() => {
const me = document.getElementById('ys-chat-js'), brand = me?.dataset.brand || 'Yến Sào Nha Trang', hotline = me?.dataset.hotline || '';
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const vnd = n => Math.round(n).toLocaleString('vi-VN') + 'đ';
const ls = { get(k) { try { return localStorage.getItem(k) } catch { return null } }, set(k, v) { try { localStorage.setItem(k, v) } catch {} } };
let k = ls.get('ysnt_chat_k');
if (!k || !/^[A-Za-z0-9]{24,64}$/.test(k)) { const a = new Uint8Array(24); crypto.getRandomValues(a); k = Array.from(a, b => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'[b % 62]).join(''); ls.set('ysnt_chat_k', k); }
let last = 0, open = false, busy = false, started = ls.get('ysnt_chat_started') === '1', timer = null, seen = +(ls.get('ysnt_chat_seen') || 0);
const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';

const btn = document.createElement('button');
btn.className = 'ysc-btn'; btn.type = 'button'; btn.setAttribute('aria-label', 'Mở khung tư vấn');
btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/></svg><span class="t">Hỏi tư vấn</span><span class="ysc-dot"></span>';
const box = document.createElement('section');
box.className = 'ysc'; box.hidden = true; box.setAttribute('aria-label', 'Tư vấn ' + brand);
box.innerHTML = `<div class="ysc-hd"><div><b>Tư vấn ${esc(brand)}</b><small>Trợ lý AI trả lời ngay · nhân viên hỗ trợ khi cần</small></div><button type="button" aria-label="Đóng">×</button></div>
  <div class="ysc-list" role="log" aria-live="polite"></div><div class="ysc-typing" hidden>Đang trả lời…</div>
  <div class="ysc-ft"><form class="ysc-form" hidden><div class="ysc-note">Để lại tên và số điện thoại, nhân viên gọi lại cho anh/chị.</div><input name="name" placeholder="Tên anh/chị" autocomplete="name"><div class="row"><input name="phone" placeholder="Số điện thoại" inputmode="tel" autocomplete="tel"><button>Gửi</button></div></form>
  <form class="ysc-in"><textarea rows="1" placeholder="Anh/chị cần tìm quà cho ai?" aria-label="Nội dung tin nhắn" maxlength="1000"></textarea><button aria-label="Gửi">Gửi</button></form>
  <div class="ysc-links"><button type="button" data-human>Gặp nhân viên</button><button type="button" data-contact>Để lại số điện thoại</button>${hotline ? `<a href="tel:${esc(hotline.replace(/\D/g, ''))}" style="font-size:12px;color:var(--sea)">Gọi ${esc(hotline)}</a>` : ''}</div></div>`;
document.body.append(btn, box);
const list = box.querySelector('.ysc-list'), typing = box.querySelector('.ysc-typing'), form = box.querySelector('.ysc-in'), ta = form.querySelector('textarea'), cform = box.querySelector('.ysc-form');

function cards(cs) {
  if (!cs?.length) return '';
  return `<div class="ysc-cards">${cs.map(c => `<div class="ysc-card"><img src="${esc(c.img || '')}" alt="" loading="lazy"><div><a href="${esc(c.url)}">${esc(c.name)}</a>${c.variant ? `<div class="v">${esc(c.variant)}</div>` : ''}
    <div class="row"><span class="pr">${vnd(c.price)}</span><button type="button" data-add="${+c.id}" ${c.ok ? '' : 'disabled'}>${c.ok ? 'Thêm vào giỏ' : 'Tạm hết'}</button></div></div></div>`).join('')}</div>`;
}
function add(ms) {
  for (const m of ms) {
    if (m.id <= last) continue; last = m.id;
    const d = document.createElement('div');
    d.className = 'ysc-m' + (m.me ? ' me' : '') + (m.by === 'system' ? ' sys' : '');
    d.innerHTML = esc(m.text) + (m.me || m.by === 'system' ? '' : `<small>${m.by === 'ai' ? 'Trợ lý AI' : 'Nhân viên'} · ${esc(m.at)}</small>`);
    list.append(d);
    if (m.cards?.length) { const c = document.createElement('div'); c.innerHTML = cards(m.cards); list.append(c.firstElementChild); }
  }
  list.scrollTop = list.scrollHeight;
  if (open) { seen = last; ls.set('ysnt_chat_seen', String(seen)); btn.classList.remove('unread'); } else if (last > seen) btn.classList.add('unread');
}
function intro() {
  if (list.childElementCount) return;
  const d = document.createElement('div'); d.className = 'ysc-m';
  d.innerHTML = `Chào anh/chị, em là trợ lý AI của ${esc(brand)}. Anh/chị cần tìm yến cho ai, dịp gì, tầm bao nhiêu tiền ạ?<small>Trợ lý AI</small>`;
  const ch = document.createElement('div'); ch.className = 'ysc-chips';
  ch.innerHTML = ['Biếu bố mẹ tầm 1 triệu', 'Yến cho mẹ bầu, bé', 'Quà du lịch mang về', 'Quà tặng doanh nghiệp'].map(t => `<button type="button">${t}</button>`).join('');
  list.append(d, ch);
}
async function api(method, url, body) {
  const r = await fetch(url, { method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: body ? JSON.stringify(body) : undefined });
  const j = await r.json().catch(() => ({}));
  if (!r.ok) throw new Error(j.message || 'Chưa gửi được, anh/chị thử lại giúp em.');
  return j;
}
async function poll() { try { const j = await api('GET', `/api/chat?k=${k}&after=${last}`); add(j.messages || []); } catch {} }
function schedule() { clearTimeout(timer); if (started) timer = setTimeout(async () => { await poll(); schedule(); }, open ? 5000 : 30000); }
async function send(text, human) {
  text = text.trim(); if (!text || busy) return;
  busy = true; form.querySelector('button').disabled = true; typing.hidden = false;
  const tmp = document.createElement('div'); tmp.className = 'ysc-m me'; tmp.textContent = text; list.append(tmp); list.scrollTop = list.scrollHeight;
  try {
    const j = await api('POST', '/api/chat', { k, text, after: last, human: !!human });
    tmp.remove(); add(j.messages || []);
    if (!started) { started = true; ls.set('ysnt_chat_started', '1'); }
  } catch (e) { tmp.classList.add('sys'); tmp.textContent = e.message; }
  busy = false; form.querySelector('button').disabled = false; typing.hidden = true; schedule();
}
function toggle(v) {
  open = v; box.hidden = !v; btn.hidden = v;
  if (v) { intro(); seen = last; ls.set('ysnt_chat_seen', String(seen)); btn.classList.remove('unread'); setTimeout(() => ta.focus(), 50); }
  schedule();
}
btn.addEventListener('click', () => toggle(true));
box.querySelector('.ysc-hd button').addEventListener('click', () => toggle(false));
form.addEventListener('submit', e => { e.preventDefault(); const t = ta.value; ta.value = ''; send(t); });
ta.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
list.addEventListener('click', e => {
  const b = e.target.closest('button'); if (!b) return;
  if (b.dataset.add) { window.YS?.add(+b.dataset.add, 1); window.YS?.toast('Đã thêm vào giỏ'); }
  else if (b.closest('.ysc-chips')) { b.parentElement.remove(); send(b.textContent); }
});
box.querySelector('[data-human]').addEventListener('click', () => send('Em muốn gặp nhân viên tư vấn', true));
box.querySelector('[data-contact]').addEventListener('click', () => { cform.hidden = !cform.hidden; if (!cform.hidden) cform.elements.name.focus(); });
cform.addEventListener('submit', async e => {
  e.preventDefault();
  const name = cform.elements.name.value.trim(), phone = cform.elements.phone.value.replace(/[\s.]/g, '');
  if (name.length < 2 || !/^0\d{9}$/.test(phone)) { (name.length < 2 ? cform.elements.name : cform.elements.phone).focus(); window.YS?.toast('Nhập tên và số điện thoại 10 số'); return; }
  try {
    if (!started) await send('Em để lại số điện thoại để được gọi lại');
    const j = await api('POST', '/api/chat/lien-he', { k, name, phone, after: last }); add(j.messages || []); cform.hidden = true;
  } catch (err) { window.YS?.toast(err.message); }
});
if (started) poll().then(schedule);
})();
