/* FARMLINK - Complete Rewrite for Farmer/Admin/Buyer System */
/* Core application logic with 3-role support */

/* UUID polyfill */
(function ensureUUID(){
  if (!window.crypto) window.crypto = {};
  if (!window.crypto.getRandomValues) window.crypto.getRandomValues = (arr)=>{ for(let i=0;i<arr.length;i++) arr[i]=Math.floor(Math.random()*256); return arr; };
  if (!window.crypto.randomUUID){
    window.crypto.randomUUID = function(){
      const b = new Uint8Array(16); crypto.getRandomValues(b);
      b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80;
      const h = [...b].map(v=>v.toString(16).padStart(2,'0')).join('');
      return `${h.slice(0,8)}-${h.slice(8,12)}-${h.slice(12,16)}-${h.slice(16,20)}-${h.slice(20)}`;
    };
  }
})();

/* localStorage helpers */
const LS = {
  get(k, fallback){ try{ return JSON.parse(localStorage.getItem(k)) ?? fallback; }catch{ return fallback; } },
  set(k,v){ localStorage.setItem(k, JSON.stringify(v)); },
  push(k,item){ const a=LS.get(k,[]); a.push(item); LS.set(k,a); },
  remove(k){ localStorage.removeItem(k); }
};

/* Seed demo data for new 3-role system */
(function seed(){
  if (!localStorage.getItem('farmlink-v2-seed')){
    // Users with 3 roles
    LS.set('users', [
      { id: crypto.randomUUID(), username:'admin', password:'admin123', role:'admin', email:'admin@farmlink.app', createdAt: new Date().toISOString() },
      { id: crypto.randomUUID(), username:'farmer1', password:'farmer123', role:'farmer', email:'farmer1@farmlink.app', farmName:'Green Fields Farm', location:'Region 1', createdAt: new Date().toISOString() },
      { id: crypto.randomUUID(), username:'farmer2', password:'farmer123', role:'farmer', email:'farmer2@farmlink.app', farmName:'Sunshine Farm', location:'Region 2', createdAt: new Date().toISOString() },
      { id: crypto.randomUUID(), username:'buyer1', password:'buyer123', role:'buyer', email:'buyer1@farmlink.app', company:'Fresh Market', location:'City Center', createdAt: new Date().toISOString() },
      { id: crypto.randomUUID(), username:'buyer2', password:'buyer123', role:'buyer', email:'buyer2@farmlink.app', company:'Local Grocery', location:'Downtown', createdAt: new Date().toISOString() }
    ]);
    
    LS.set('currentUser', null);
    
    // Products (owned by farmers)
    LS.set('products', [
      { id: crypto.randomUUID(), farmerId: LS.get('users')[1].id, name:'Organic Rice', category:'Grains', quantity:120, price:50, unit:'kg', description:'Premium organic rice', image:'', createdAt: new Date().toISOString() },
      { id: crypto.randomUUID(), farmerId: LS.get('users')[1].id, name:'Fresh Corn', category:'Vegetables', quantity:80, price:42, unit:'kg', description:'Sweet fresh corn', image:'', createdAt: new Date().toISOString() },
      { id: crypto.randomUUID(), farmerId: LS.get('users')[2].id, name:'Tomatoes', category:'Vegetables', quantity:60, price:30, unit:'kg', description:'Ripe red tomatoes', image:'', createdAt: new Date().toISOString() },
      { id: crypto.randomUUID(), farmerId: LS.get('users')[2].id, name:'Papaya', category:'Fruits', quantity:40, price:50, unit:'kg', description:'Sweet papaya', image:'', createdAt: new Date().toISOString() }
    ]);
    
    // Orders (buyer purchases)
    LS.set('orders', [
      { id: crypto.randomUUID(), buyerId: LS.get('users')[3].id, farmerId: LS.get('users')[1].id, items:[{productId: LS.get('products')[0].id, quantity:20, price:50}], total:1000, status:'completed', createdAt: new Date().toISOString() },
      { id: crypto.randomUUID(), buyerId: LS.get('users')[4].id, farmerId: LS.get('users')[2].id, items:[{productId: LS.get('products')[2].id, quantity:15, price:30}], total:450, status:'pending', createdAt: new Date().toISOString() }
    ]);
    
    // Shopping carts
    LS.set('carts', {});
    
    // Activity log
    LS.set('activity', [
      { id: crypto.randomUUID(), type:'system', message:'System initialized with demo data', createdAt: new Date().toISOString() },
      { id: crypto.randomUUID(), type:'order', message:'Buyer1 purchased 20kg Organic Rice', createdAt: new Date().toISOString() }
    ]);
    
    localStorage.setItem('farmlink-v2-seed','1');
  }
})();

/* Auth API - Updated for 3 roles */
function signupSubmit(username, email, password, role, additionalData = {}){
  const users = LS.get('users', []);
  if (users.some(u => u.username.toLowerCase() === username.toLowerCase())) {
    return { ok: false, msg: 'Username already exists' };
  }
  if (users.some(u => u.email.toLowerCase() === email.toLowerCase())) {
    return { ok: false, msg: 'Email already registered' };
  }
  
  const userData = {
    id: crypto.randomUUID(),
    username,
    email,
    password,
    role,
    createdAt: new Date().toISOString(),
    ...additionalData
  };
  
  users.push(userData);
  LS.set('users', users);
  
  // Log activity
  LS.push('activity', {
    id: crypto.randomUUID(),
    type: 'user',
    message: `New ${role} account created: ${username}`,
    createdAt: new Date().toISOString()
  });
  
  return { ok: true, msg: 'Account created successfully. You may now login.' };
}

function loginSubmit(username, password, role){
  const users = LS.get('users', []);
  const user = users.find(u => 
    u.username === username && 
    u.password === password && 
    u.role === role
  );
  
  if (!user) return { ok: false, msg: 'Invalid credentials' };
  
  LS.set('currentUser', { 
    id: user.id,
    username: user.username, 
    email: user.email, 
    role: user.role,
    ...(user.role === 'farmer' && { farmName: user.farmName, location: user.location }),
    ...(user.role === 'buyer' && { company: user.company, location: user.location })
  });
  
  // Log activity
  LS.push('activity', {
    id: crypto.randomUUID(),
    type: 'auth',
    message: `${user.role} ${user.username} logged in`,
    createdAt: new Date().toISOString()
  });
  
  return { ok: true, user: LS.get('currentUser') };
}

function logout(){ 
  const currentUser = LS.get('currentUser');
  if (currentUser) {
    LS.push('activity', {
      id: crypto.randomUUID(),
      type: 'auth',
      message: `${currentUser.role} ${currentUser.username} logged out`,
      createdAt: new Date().toISOString()
    });
  }
  LS.set('currentUser', null); 
  window.location.href = 'index.html'; 
}

function getCurrentUser(){ return LS.get('currentUser', null); }

function requireAuth(roles = ['admin','farmer','buyer']){
  const me = getCurrentUser();
  if (!me || !roles.includes(me.role)) { 
    window.location.href = 'index.html'; 
    return null; 
  }
  return me;
}

function requireRole(role){
  const me = getCurrentUser();
  if (!me || me.role !== role) {
    window.location.href = 'index.html';
    return null;
  }
  return me;
}

/* Product Management */
function getProducts(farmerId = null) {
  const products = LS.get('products', []);
  if (farmerId) {
    return products.filter(p => p.farmerId === farmerId);
  }
  return products;
}

function createProduct(productData) {
  const me = requireRole('farmer');
  if (!me) return { ok: false, msg: 'Unauthorized' };
  
  const product = {
    id: crypto.randomUUID(),
    farmerId: me.id,
    ...productData,
    createdAt: new Date().toISOString()
  };
  
  LS.push('products', product);
  
  LS.push('activity', {
    id: crypto.randomUUID(),
    type: 'product',
    message: `Farmer ${me.username} added product: ${productData.name}`,
    createdAt: new Date().toISOString()
  });
  
  return { ok: true, product };
}

function updateProduct(productId, updates) {
  const me = requireRole('farmer');
  if (!me) return { ok: false, msg: 'Unauthorized' };
  
  const products = LS.get('products', []);
  const index = products.findIndex(p => p.id === productId && p.farmerId === me.id);
  
  if (index === -1) return { ok: false, msg: 'Product not found' };
  
  products[index] = { ...products[index], ...updates };
  LS.set('products', products);
  
  LS.push('activity', {
    id: crypto.randomUUID(),
    type: 'product',
    message: `Farmer ${me.username} updated product: ${products[index].name}`,
    createdAt: new Date().toISOString()
  });
  
  return { ok: true, product: products[index] };
}

function deleteProduct(productId) {
  const me = requireRole('farmer');
  if (!me) return { ok: false, msg: 'Unauthorized' };
  
  const products = LS.get('products', []);
  const product = products.find(p => p.id === productId && p.farmerId === me.id);
  
  if (!product) return { ok: false, msg: 'Product not found' };
  
  const filteredProducts = products.filter(p => p.id !== productId);
  LS.set('products', filteredProducts);
  
  LS.push('activity', {
    id: crypto.randomUUID(),
    type: 'product',
    message: `Farmer ${me.username} deleted product: ${product.name}`,
    createdAt: new Date().toISOString()
  });
  
  return { ok: true };
}

/* Shopping Cart */
function getCart() {
  const me = requireRole('buyer');
  if (!me) return { ok: false, msg: 'Unauthorized' };
  
  const carts = LS.get('carts', {});
  return { ok: true, cart: carts[me.id] || [] };
}

function addToCart(productId, quantity = 1) {
  const me = requireRole('buyer');
  if (!me) return { ok: false, msg: 'Unauthorized' };
  
  const product = LS.get('products', []).find(p => p.id === productId);
  if (!product) return { ok: false, msg: 'Product not found' };
  
  const carts = LS.get('carts', {});
  const userCart = carts[me.id] || [];
  
  const existingItem = userCart.find(item => item.productId === productId);
  if (existingItem) {
    existingItem.quantity += quantity;
  } else {
    userCart.push({
      productId,
      quantity,
      price: product.price,
      name: product.name,
      farmerId: product.farmerId
    });
  }
  
  carts[me.id] = userCart;
  LS.set('carts', carts);
  
  return { ok: true, cart: userCart };
}

function removeFromCart(productId) {
  const me = requireRole('buyer');
  if (!me) return { ok: false, msg: 'Unauthorized' };
  
  const carts = LS.get('carts', {});
  const userCart = carts[me.id] || [];
  
  carts[me.id] = userCart.filter(item => item.productId !== productId);
  LS.set('carts', carts);
  
  return { ok: true, cart: carts[me.id] };
}

/* Order Management */
function createOrder(cartItems) {
  const me = requireRole('buyer');
  if (!me) return { ok: false, msg: 'Unauthorized' };
  
  if (!cartItems || cartItems.length === 0) {
    return { ok: false, msg: 'Cart is empty' };
  }
  
  // Group items by farmer
  const ordersByFarmer = {};
  cartItems.forEach(item => {
    if (!ordersByFarmer[item.farmerId]) {
      ordersByFarmer[item.farmerId] = [];
    }
    ordersByFarmer[item.farmerId].push(item);
  });
  
  const orders = LS.get('orders', []);
  const createdOrders = [];
  
  // Create separate order for each farmer
  for (const [farmerId, items] of Object.entries(ordersByFarmer)) {
    const total = items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    
    const order = {
      id: crypto.randomUUID(),
      buyerId: me.id,
      farmerId,
      items: items.map(item => ({
        productId: item.productId,
        quantity: item.quantity,
        price: item.price
      })),
      total,
      status: 'pending',
      createdAt: new Date().toISOString()
    };
    
    orders.push(order);
    createdOrders.push(order);
    
    // Clear cart after successful order
    const carts = LS.get('carts', {});
    carts[me.id] = [];
    LS.set('carts', carts);
  }
  
  LS.set('orders', orders);
  
  LS.push('activity', {
    id: crypto.randomUUID(),
    type: 'order',
    message: `Buyer ${me.username} placed ${createdOrders.length} order(s)`,
    createdAt: new Date().toISOString()
  });
  
  return { ok: true, orders: createdOrders };
}

/* User Management (Admin only) */
function getUsers() {
  const me = requireRole('admin');
  if (!me) return { ok: false, msg: 'Unauthorized' };
  
  return { ok: true, users: LS.get('users', []) };
}

function createAdmin(userData) {
  const me = requireRole('admin');
  if (!me) return { ok: false, msg: 'Unauthorized' };
  
  return signupSubmit(
    userData.username,
    userData.email,
    userData.password,
    'admin',
    userData.additionalData || {}
  );
}

function createUser(userData) {
  const me = requireRole('admin');
  if (!me) return { ok: false, msg: 'Unauthorized' };
  
  return signupSubmit(
    userData.username,
    userData.email,
    userData.password,
    userData.role,
    userData.additionalData || {}
  );
}

function deleteUser(userId) {
  const me = requireRole('admin');
  if (!me) return { ok: false, msg: 'Unauthorized' };
  
  const users = LS.get('users', []);
  const user = users.find(u => u.id === userId);
  
  if (!user) return { ok: false, msg: 'User not found' };
  if (user.id === me.id) return { ok: false, msg: 'Cannot delete your own account' };
  
  const filteredUsers = users.filter(u => u.id !== userId);
  LS.set('users', filteredUsers);
  
  LS.push('activity', {
    id: crypto.randomUUID(),
    type: 'user',
    message: `Admin ${me.username} deleted user: ${user.username}`,
    createdAt: new Date().toISOString()
  });
  
  return { ok: true };
}

/* Analytics and Reports */
function getSalesAnalytics(farmerId = null) {
  const me = requireAuth();
  if (!me) return { ok: false, msg: 'Unauthorized' };
  
  const orders = LS.get('orders', []);
  const products = LS.get('products', []);
  
  let filteredOrders = orders;
  if (farmerId) {
    filteredOrders = orders.filter(order => order.farmerId === farmerId);
  } else if (me.role === 'farmer') {
    filteredOrders = orders.filter(order => order.farmerId === me.id);
  }
  
  const totalSales = filteredOrders.reduce((sum, order) => sum + order.total, 0);
  const completedOrders = filteredOrders.filter(order => order.status === 'completed').length;
  const pendingOrders = filteredOrders.filter(order => order.status === 'pending').length;
  
  return {
    ok: true,
    analytics: {
      totalOrders: filteredOrders.length,
      completedOrders,
      pendingOrders,
      totalSales,
      averageOrderValue: filteredOrders.length > 0 ? totalSales / filteredOrders.length : 0
    }
  };
}

/* Expose API */
window.FarmLink = {
  // Auth
  signupSubmit,
  loginSubmit,
  logout,
  getCurrentUser,
  requireAuth,
  requireRole,
  
  // Products
  getProducts,
  createProduct,
  updateProduct,
  deleteProduct,
  
  // Cart
  getCart,
  addToCart,
  removeFromCart,
  
  // Orders
  createOrder,
  
  // Users (Admin only)
  getUsers,
  createUser,
  createAdmin,
  deleteUser,
  
  // Analytics
  getSalesAnalytics
};

/* Bootstrap */
document.addEventListener('DOMContentLoaded', () => {
  // Add click animations to sidebar links
  document.querySelectorAll('.sidebar a').forEach(a => {
    a.addEventListener('click', () => { 
      a.classList.add('clicked'); 
      setTimeout(() => a.classList.remove('clicked'), 200); 
    });
  });
  
  // Page-specific rendering
  const page = document.body.dataset.page;
  if (!page) return;
  
  try {
    // Page-specific render functions will be called from individual pages
    console.log(`Loaded page: ${page}`);
  } catch(err) { 
    console.error('Page render error:', err); 
  }
});
