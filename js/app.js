const $ = s => document.querySelector(s);
const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const money = n => '₱' + Number(n || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
const H = h => ({h});          // trusted HTML cell
const cell = v => v && v.h !== undefined ? v.h : esc(v);
const TAG = {Completed:'ok',Paid:'ok',Assigned:'ok','In Progress':'bl',Confirmed:'bl',Pending:'wt',Unassigned:'wt',Cancelled:'rd',Refunded:'rd',Failed:'rd'};
const tag = s => H(`<span class="g ${TAG[s] || ''}">${esc(s)}</span>`);
const L = a => a.map(x => [x, x]);
const BS = ['Pending','Confirmed','In Progress','Completed','Cancelled'];
function normalizePhone(value) {
  let phone = value.trim().replace(/[\s().-]/g, '');
  phone = phone.replace(/^00/, '+');
  phone = phone.replace(/^\+?63(?=9\d{9}$)/, '0');
  phone = phone.replace(/^9(?=\d{9}$)/, '09');
  return /^(?:09\d{9}|\d{7,15}|\+[1-9]\d{7,14})$/.test(phone) ? phone : '';
}

async function api(t, m = 'GET', body, id) {
  const r = await fetch(`api.php?t=${t}${id ? '&id=' + id : ''}`, {method: m, headers: {'Content-Type': 'application/json'}, body: body ? JSON.stringify(body) : undefined});
  let d; try { d = await r.json(); } catch (e) { throw new Error('Server error. Is PHP running with the pdo_pgsql extension?'); }
  if (!r.ok) throw new Error(d.error || 'Request failed.');
  return d;
}

/* field: [name, label, type, options (array or key of /options), optional] */
const P = {
  dashboard: {t:'Dashboard'},
  customers: {t:'Customers', one:'customer', f:[['name','Full name'],['phone','Phone number','tel'],['email','Email','email']],
    h:['Name','Phone','Email','Bookings'], r:x => [x.name, x.phone, x.email, x.bookings]},
  vehicles: {t:'Vehicles', one:'vehicle', f:[['plate_no','Plate no.'],['vehicle_type','Type','select',L(['Motorcycle','Tricycle','Car','Van'])],['model','Model'],['driver_id','Assigned driver','select','drivers',1]],
    h:['Plate no.','Type','Model','Assigned driver','Status'], r:x => [x.plate_no, x.vehicle_type, x.model, x.driver || '—', tag(x.driver ? 'Assigned' : 'Unassigned')]},
  bookings: {t:'Bookings', one:'booking', cancel:1, f:[['customer_name','Customer name'],['customer_phone','Customer phone (if new customer)','tel',null,1],['customer_email','Customer email (if new customer)','email',null,1],['pickup','Pickup address'],['pickup_city','Pickup city','select','cities'],['dropoff','Drop-off address'],['dropoff_city','Drop-off city','select','cities'],['driver_id','Driver','select','drivers',1],['booking_status','Status','select',L(BS)]],
    h:['ID','Customer','Route','Driver','Status','Date'], r:x => ['#' + x.id, x.customer, `${x.pickup} → ${x.dropoff}`, x.driver || 'Unassigned', tag(x.booking_status), x.date]},
  payments: {t:'Payments', one:'payment', f:[['booking_id','Booking','select','bookings'],['amount','Amount (₱)','number'],['method','Method','select',L(['Cash','GCash','Maya','Card'])],['status','Status','select',L(['Pending','Paid','Failed','Refunded'])]],
    h:['Booking','Customer','Amount','Method','Status'], r:x => ['#' + x.booking_id, x.customer, money(x.amount), x.method, tag(x.status)]},
  ratings: {t:'Ratings & Feedback', one:'review', f:[['booking_id','Completed booking','select','completed'],['rating','Rating','select',[5,4,3,2,1].map(n => [n, '★'.repeat(n) + ` (${n})`])],['feedback','Feedback','textarea','',1]]}
};
let page, DATA = [], q = '', pendingAdd = false;

function render() {
  page = location.hash.slice(1); if (!P[page]) page = 'dashboard';
  $('#nav').innerHTML = Object.keys(P).map(k => `<a class="n${k === page ? ' on' : ''}" href="#${k}">${P[k].t}</a>`).join('');
  $('#main').innerHTML = '<p>Loading…</p>';
  (page === 'dashboard' ? dash() : list()).catch(e => $('#main').innerHTML = `<div class="c" style="padding:24px"><b>Could not load data</b><p>${esc(e.message)}</p></div>`);
}

async function dash() {
  const d = await api('dashboard'), s = d.stats, tot = d.byStatus.reduce((a, x) => a + x.n, 0) || 1;
  const COL = {Completed:'#2B8A3E','In Progress':'#1864AB',Confirmed:'#1864AB',Pending:'#B36B00',Cancelled:'#C92A2A'};
  $('#main').innerHTML = `<div class="h"><h1>Dashboard</h1><button class="bt" data-a="newbooking">+ New booking</button></div>
  <div class="s"><div>Customers<b>${s.customers}</b></div><div>Active bookings<b>${s.active}</b></div><div>Revenue collected<b>${money(s.revenue)}</b></div><div>Average rating<b>${s.avg_rating ? s.avg_rating + ' ★' : '–'}</b></div></div>
  <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">
  <div class="c" style="flex:2 1 520px"><table><tr><th>ID</th><th>Customer</th><th>Route</th><th>Status</th></tr>${d.recent.map(x => `<tr><td>#${x.id}</td><td>${esc(x.customer)}</td><td>${esc(x.pickup)} → ${esc(x.dropoff)}</td><td>${tag(x.booking_status).h}</td></tr>`).join('') || '<tr><td colspan="4">No bookings yet.</td></tr>'}</table></div>
  <div class="c" style="flex:1 1 260px;padding:20px"><b>Bookings by status</b>${d.byStatus.map(x => `<div style="margin-top:16px">${esc(x.s)} · ${x.n}<div class="bar"><i style="width:${Math.round(x.n / tot * 100)}%;background:${COL[x.s] || '#888'}"></i></div></div>`).join('')}</div></div>`;
}

async function list() {
  const c = P[page]; DATA = await api(page); q = '';
  $('#main').innerHTML = `<div class="h"><h1>${c.t}</h1><input class="in" id="q" type="search" placeholder="Search…" aria-label="Search"><button class="bt" data-a="add">+ Add ${c.one}</button></div><div id="stats"></div><div id="body"></div>`;
  draw();
  if (pendingAdd) { pendingAdd = false; openForm(); }
}

function draw() {
  const c = P[page], ql = q.toLowerCase(), rows = DATA.filter(x => !ql || JSON.stringify(Object.values(x)).toLowerCase().includes(ql));
  const sum = st => money(DATA.filter(x => x.status === st).reduce((a, x) => a + x.amount, 0));
  const five = DATA.filter(x => x.rating == 5).length, avg = DATA.length ? (DATA.reduce((a, x) => a + x.rating, 0) / DATA.length).toFixed(1) : '–';
  $('#stats').innerHTML = page === 'payments' ? `<div class="s"><div>Collected<b>${sum('Paid')}</b></div><div>Pending<b>${sum('Pending')}</b></div><div>Refunded<b>${sum('Refunded')}</b></div></div>`
    : page === 'ratings' ? `<div class="s"><div>Average rating<b>${avg} ★</b></div><div>Total reviews<b>${DATA.length}</b></div><div>5-star reviews<b>${five}</b></div></div>` : '';
  const act = x => `${c.cancel && !['Completed','Cancelled'].includes(x.booking_status) ? `<button class="l" data-a="cancel" data-i="${x.id}">Cancel</button>` : ''}<button class="l" data-a="edit" data-i="${x.id}">Edit</button><button class="l" data-a="del" data-i="${x.id}">Delete</button>`;
  if (!rows.length) { $('#body').innerHTML = '<div class="c" style="padding:30px;text-align:center">No records found.</div>'; return; }
  $('#body').innerHTML = page === 'ratings'
    ? `<div class="rv">${rows.map(x => `<div class="c"><span class="st" role="img" aria-label="${x.rating} out of 5 stars">${'★'.repeat(x.rating)}${'☆'.repeat(5 - x.rating)}</span><p style="margin:10px 0">${esc(x.feedback) || '<i>No comment</i>'}</p><small>${esc(x.customer)} · ${esc(x.pickup)} → ${esc(x.dropoff)} · Driver: ${esc(x.driver || '—')}</small><div style="margin-top:8px">${act(x)}</div></div>`).join('')}</div>`
    : `<div class="c"><table><tr>${c.h.map(h => `<th>${h}</th>`).join('')}<th></th></tr>${rows.map(x => `<tr>${c.r(x).map(v => `<td>${cell(v)}</td>`).join('')}<td class="x">${act(x)}</td></tr>`).join('')}</table></div>`;
}

async function openForm(id) {
  const c = P[page], r = id ? DATA.find(x => x.id == id) : {}, O = await api('options');
  const fields = c.f.map(([k, l, type, o, opt]) => {
    const v = String(r[k] == null ? '' : r[k]), name = `name="${k}" id="f-${k}"`;
    let el;
    if (type === 'select') {
      const emptyLabel = page === 'bookings' && k === 'driver_id' ? '— Random driver —' : '— none —';
      el = `<select class="in" ${name}>${opt ? `<option value="">${emptyLabel}</option>` : ''}${(typeof o === 'string' ? O[o] : o).map(([a, b]) => `<option value="${esc(a)}"${String(a) === v ? ' selected' : ''}>${esc(b)}</option>`).join('')}</select>`;
    }
    else if (type === 'textarea') el = `<textarea class="in" rows="3" ${name}>${esc(v)}</textarea>`;
    else el = `<input class="in" ${name} type="${type || 'text'}" value="${esc(v)}"${type === 'tel' ? ' inputmode="tel" autocomplete="tel" placeholder="e.g. +639175550123 or +1 202 555 0123"' : ''}${type === 'email' ? ' maxlength="254" autocomplete="email" placeholder="name@example.com"' : ''}${type === 'number' ? ' min="0.01" step="0.01"' : ''}>`;
    return `<label for="f-${k}">${l}</label>${el}`;
  }).join('');
  const d = document.createElement('div'); d.className = 'ov';
  d.innerHTML = `<div class="md"><h2>${id ? 'Edit' : 'Add'} ${c.one}</h2>${fields}<div class="err" id="err"></div><div class="row"><button class="bt no" data-x>Cancel</button><button class="bt" data-ok>Save</button></div></div>`;
  document.body.appendChild(d);
  d.onclick = async e => {
    if (e.target === d || e.target.dataset.x !== undefined) return d.remove();
    if (e.target.dataset.ok === undefined) return;
    const body = {};
    for (const [k, l, , , opt] of c.f) {
      body[k] = d.querySelector(`#f-${k}`).value.trim();
      if (!body[k] && !opt) return d.querySelector('#err').textContent = l + ' is required.';
      if (body[k] && ['phone', 'customer_phone'].includes(k)) {
        body[k] = normalizePhone(body[k]);
        if (!body[k]) return d.querySelector('#err').textContent = 'Enter a valid phone number, including its country code when applicable.';
      }
      if (body[k] && ['email', 'customer_email'].includes(k) && !d.querySelector(`#f-${k}`).checkValidity()) {
        return d.querySelector('#err').textContent = 'Enter a valid email address, such as name@example.com.';
      }
    }
    try { await api(page, id ? 'PUT' : 'POST', body, id); d.remove(); render(); } catch (err) { d.querySelector('#err').textContent = err.message; }
  };
}

document.addEventListener('click', async e => {
  const el = e.target.closest('[data-a]'); if (!el) return;
  const {a, i} = el.dataset;
  try {
    if (a === 'add') openForm();
    if (a === 'edit') openForm(i);
    if (a === 'newbooking') { pendingAdd = true; location.hash = 'bookings'; if (page === 'bookings') render(); }
    if (a === 'cancel' && confirm('Cancel this booking?')) { await api(page, 'PUT', {booking_status: 'Cancelled'}, i); render(); }
    if (a === 'del' && confirm(`Delete this ${P[page].one}?`)) { await api(page, 'DELETE', null, i); render(); }
  } catch (err) { alert(err.message); }
});
document.addEventListener('input', e => { if (e.target.id === 'q') { q = e.target.value; draw(); } });
window.addEventListener('hashchange', render);
render();