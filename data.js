const API = 'api.php';

async function apiFetch(action, method = 'GET', body = null, params = {}) {
  const folder = location.pathname.substring(0, location.pathname.lastIndexOf('/') + 1);
  const url    = new URL(location.origin + folder + API);
  url.searchParams.set('action', action);
  Object.entries(params).forEach(([k, v]) => { if (v !== '' && v !== undefined) url.searchParams.set(k, v); });
  const opts = { method, credentials: 'include', headers: {} };
  if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
  const res  = await fetch(url, opts);
  const data = await res.json().catch(() => ({ error: 'Réponse invalide du serveur' }));
  if (!res.ok) throw new Error(data.error || 'Erreur serveur ' + res.status);
  return data;
}

const DB = {
  _user: null,

  async loadUser() {
    try { const d = await apiFetch('me'); this._user = d.user; }
    catch { this._user = null; }
    return this._user;
  },

  currentUser() { return this._user; },
  isAdmin()     { return this._user?.role === 'admin'; },

  async login(email, password) {
    const d = await apiFetch('login', 'POST', { email, password });
    this._user = d.user; updateCartBadge(); return d;
  },
  async logout() {
    await apiFetch('logout', 'POST'); this._user = null; updateCartBadge();
  },
  async register(nom, email, password) {
    const d = await apiFetch('register', 'POST', { nom, email, password });
    this._user = d.user; updateCartBadge(); return d;
  },

  async getProducts(filters = {})  { return apiFetch('products', 'GET', null, filters); },
  async getProductById(id)         { return apiFetch('product',  'GET', null, { id }); },
  async saveProduct(product)       { return apiFetch(product.id ? 'product_update' : 'product_create', 'POST', product); },
  async deleteProduct(id)          { return apiFetch('product_delete', 'POST', { id }); },

  async getCart()                        { return apiFetch('cart'); },
  async addToCart(productId, taille='M') { await apiFetch('cart_add',    'POST', { produit_id: productId, taille }); updateCartBadge(); },
  async removeFromCart(id)               { await apiFetch('cart_remove', 'POST', { id }); updateCartBadge(); },
  async updateQty(id, quantite)          { await apiFetch('cart_update', 'POST', { id, quantite }); updateCartBadge(); },
  async clearCart()                      { await apiFetch('cart_clear',  'POST'); updateCartBadge(); },

  async cartTotal() { return (await this.getCart()).reduce((s,i) => s + i.prix * i.quantite, 0); },
  async cartCount() { return (await this.getCart()).reduce((s,i) => s + i.quantite, 0); },

  async createOrder() { return apiFetch('order', 'POST'); },
  async getOrders()   { return apiFetch('orders'); },
  async adminUsers()  { return apiFetch('admin_users'); },

  async init() { await this.loadUser(); await updateCartBadge(); }
};

async function updateCartBadge() {
  try {
    const count = await DB.cartCount();
    document.querySelectorAll('.cart-badge').forEach(el => {
      el.textContent = count;
      el.style.display = count > 0 ? 'flex' : 'none';
    });
  } catch { /* silencieux */ }
}

function buildNav(genre) {
  const user    = DB.currentUser();
  const isAdmin = DB.isAdmin();
  const cats = genre === 'homme'
    ? [['vestes','Vestes'],['chemises','Chemises'],['pantalons','Pantalons'],['pulls','Pulls'],['tops','Tops']]
    : [['robes','Robes'],['vestes','Vestes'],['pantalons','Pantalons'],['tops','Tops']];

  const catLinks  = cats.map(([k,l]) => `<a href="catalogue.html?genre=${genre}&cat=${k}">${l}</a>`).join('');
  const opposite  = genre === 'homme' ? 'femme' : 'homme';
  const label     = genre === 'homme' ? '→ Femme' : '→ Homme';
  const userArea  = user
    ? `<a href="compte.html" class="nav-user">${user.nom.split(' ')[0]}</a>
       ${isAdmin ? `<a href="admin.html" class="nav-admin-btn">Admin</a>` : ''}
       <a href="#" class="nav-user" onclick="doLogout()">Déco.</a>`
    : `<a href="compte.html" class="nav-user">Connexion</a>`;

  return `<nav>
    <a href="index.html" class="nav-logo">${genre==='femme'?'<em>VTHM Street</em>':'VTHM Street'} <span>·</span> <span class="genre-label">${genre[0].toUpperCase()+genre.slice(1)}</span></a>
    <div class="nav-links"><a href="catalogue.html?genre=${genre}">Tout</a>${catLinks}</div>
    <div class="nav-right">${userArea}
      <a href="catalogue.html?genre=${opposite}" class="nav-switch">${label}</a>
      <a href="panier.html" class="nav-cart">🛍<span class="cart-badge" style="display:none">0</span></a>
    </div>
  </nav>`;
}

async function doLogout() { await DB.logout(); location.href = 'index.html'; }

async function initPage(genre) {
  await DB.init();
  const ph = document.getElementById('navPlaceholder');
  if (ph) ph.outerHTML = buildNav(genre);
  await updateCartBadge();
}

function showToast(msg) {
  const t = document.createElement('div');
  t.className = 'toast'; t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 2500);
}

if (!document.getElementById('velour-nav-css')) {
  const s = document.createElement('style');
  s.id = 'velour-nav-css';
  s.textContent = `
    nav{display:flex;justify-content:space-between;align-items:center;padding:1.4rem 3rem;border-bottom:1px solid rgba(255,255,255,.07);position:sticky;top:0;background:rgba(8,8,8,.96);backdrop-filter:blur(12px);z-index:200;gap:1rem}
    .nav-logo{font-family:'Cormorant Garamond',serif;font-size:1.4rem;letter-spacing:.35em;text-transform:uppercase;color:#f5f0eb;text-decoration:none;white-space:nowrap}.nav-logo em{font-style:italic}.nav-logo span{color:var(--accent,#c9a84c)}
    .nav-links{display:flex;gap:1.8rem;flex-wrap:wrap}.nav-links a{font-size:.58rem;letter-spacing:.22em;text-transform:uppercase;color:#888;text-decoration:none;transition:color .3s;white-space:nowrap}.nav-links a:hover{color:var(--accent,#c9a84c)}
    .nav-right{display:flex;align-items:center;gap:1rem;white-space:nowrap}
    .nav-user{font-size:.58rem;letter-spacing:.2em;text-transform:uppercase;color:#aaa;text-decoration:none;transition:color .3s}.nav-user:hover{color:var(--accent,#c9a84c)}
    .nav-admin-btn{font-size:.55rem;letter-spacing:.2em;text-transform:uppercase;background:rgba(201,168,76,.15);color:var(--accent,#c9a84c);padding:.3rem .8rem;border:1px solid rgba(201,168,76,.3);text-decoration:none;transition:all .3s}.nav-admin-btn:hover{background:var(--accent,#c9a84c);color:#000}
    .nav-switch{font-size:.55rem;letter-spacing:.18em;text-transform:uppercase;color:var(--accent,#c9a84c);text-decoration:none;border:1px solid rgba(201,168,76,.3);padding:.4rem 1rem;transition:all .3s}.nav-switch:hover{background:var(--accent,#c9a84c);color:#000}
    .nav-cart{position:relative;font-size:1.2rem;text-decoration:none;display:flex;align-items:center}
    .cart-badge{position:absolute;top:-6px;right:-8px;background:var(--accent,#c9a84c);color:#000;font-size:.5rem;font-weight:700;min-width:16px;height:16px;border-radius:50%;align-items:center;justify-content:center;font-family:'Montserrat',sans-serif}
    .toast{position:fixed;bottom:2rem;right:2rem;z-index:9999;background:#1a1a1a;border:1px solid rgba(201,168,76,.4);color:#f5f0eb;padding:1rem 1.6rem;font-size:.65rem;letter-spacing:.15em;text-transform:uppercase;animation:toastIn .4s ease;pointer-events:none}
    @keyframes toastIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}`;
  document.head.appendChild(s);
}
