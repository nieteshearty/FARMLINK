    /* FARMLINK - PHP Backend Version */
/* Core application logic with PHP backend integration */

const API_BASE = 'api/';

// API Helper Functions
async function apiCall(endpoint, options = {}) {
    try {
        console.log('Making API call to:', API_BASE + endpoint, 'with options:', options);
        console.log('Making API call to:', API_BASE + endpoint, 'with options:', options);
        console.log('Making API call to:', API_BASE + endpoint, 'with options:', options);
        const response = await fetch(API_BASE + endpoint, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || data.msg || 'API request failed');
        }
        
        return data;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

/* Auth API - Updated for PHP backend */
async function signupSubmit(username, email, password, role, additionalData = {}) {
    try {
        const response = await apiCall('auth.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'signup',
                username,
                email,
                password,
                role,
                ...additionalData
            })
        });
        
        return response;
    } catch (error) {
        return { ok: false, msg: error.message };
    }
}

async function loginSubmit(username, password, role) {
    console.log('Attempting to log in with:', { username, password, role });
    console.log('Making API call to: auth.php with body:', JSON.stringify({
        action: 'login',
        username,
        password,
        role
    }));
    try {
        const response = await apiCall('auth.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'login',
                username,
                password,
                role
            })
        });
        
        if (response.ok) {
            // Store user session (in production, use proper session management)
            localStorage.setItem('currentUser', JSON.stringify(response.user));
        }
        
        return response;
    } catch (error) {
        return { ok: false, msg: error.message };
    }
}

function logout() {
    localStorage.removeItem('currentUser');
    window.location.href = 'index.html';
}

function getCurrentUser() {
    const user = localStorage.getItem('currentUser');
    return user ? JSON.parse(user) : null;
}

function requireAuth(roles = ['admin','farmer','buyer']) {
    const me = getCurrentUser();
    if (!me || !roles.includes(me.role)) { 
        window.location.href = 'index.html'; 
        return null; 
    }
    return me;
}

function requireRole(role) {
    const me = getCurrentUser();
    if (!me || me.role !== role) {
        window.location.href = 'index.html';
        return null;
    }
    return me;
}

/* Product Management */
async function getProducts(farmerId = null) {
    try {
        const url = farmerId ? `products.php?farmer_id=${farmerId}` : 'products.php';
        const response = await apiCall(url);
        return response;
    } catch (error) {
        return { ok: false, msg: error.message };
    }
}

async function createProduct(productData) {
    const me = requireRole('farmer');
    if (!me) return { ok: false, msg: 'Unauthorized' };
    
    try {
        const response = await apiCall('products.php', {
            method: 'POST',
            body: JSON.stringify({
                ...productData,
                farmer_id: me.id
            })
        });
        return response;
    } catch (error) {
        return { ok: false, msg: error.message };
    }
}

async function updateProduct(productId, updates) {
    const me = requireRole('farmer');
    if (!me) return { ok: false, msg: 'Unauthorized' };
    
    try {
        const response = await apiCall(`products.php?id=${productId}`, {
            method: 'PUT',
            body: JSON.stringify(updates)
        });
        return response;
    } catch (error) {
        return { ok: false, msg: error.message };
    }
}

async function deleteProduct(productId) {
    const me = requireRole('farmer');
    if (!me) return { ok: false, msg: 'Unauthorized' };
    
    try {
        const response = await apiCall(`products.php?id=${productId}`, {
            method: 'DELETE'
        });
        return response;
    } catch (error) {
        return { ok: false, msg: error.message };
    }
}

/* Shopping Cart */
async function getCart() {
    const me = requireRole('buyer');
    if (!me) return { ok: false, msg: 'Unauthorized' };
    
    try {
        const response = await apiCall(`cart.php?buyer_id=${me.id}`);
        return response;
    } catch (error) {
        return { ok: false, msg: error.message };
    }
}

async function addToCart(productId, quantity = 1) {
    const me = requireRole('buyer');
    if (!me) return { ok: false, msg: 'Unauthorized' };
    
    try {
        const response = await apiCall('cart.php', {
            method: 'POST',
            body: JSON.stringify({
                buyer_id: me.id,
                product_id: productId,
                quantity: quantity
            })
        });
        return response;
    } catch (error) {
        return { ok: false, msg: error.message };
    }
}

async function removeFromCart(productId) {
    const me = requireRole('buyer');
    if (!me) return { ok: false, msg: 'Unauthorized' };
    
    try {
        // First get cart item ID
        const cartResponse = await getCart();
        if (!cartResponse.ok) return cartResponse;
        
        const cartItem = cartResponse.cart.find(item => item.product_id == productId);
        if (!cartItem) return { ok: false, msg: 'Item not in cart' };
        
        const response = await apiCall(`cart.php?id=${cartItem.id}`, {
            method: 'DELETE'
        });
        return response;
    } catch (error) {
        return { ok: false, msg: error.message };
    }
}

/* Order Management */
async function createOrder(cartItems) {
    const me = requireRole('buyer');
    if (!me) return { ok: false, msg: 'Unauthorized' };
    
    if (!cartItems || cartItems.length === 0) {
        return { ok: false, msg: 'Cart is empty' };
    }
    
    try {
        const response = await apiCall('orders.php', {
            method: 'POST',
            body: JSON.stringify({
                buyer_id: me.id,
                items: cartItems.map(item => ({
                    product_id: item.product_id,
                    quantity: item.quantity
                }))
            })
        });
        return response;
    } catch (error) {
        return { ok: false, msg: error.message };
    }
}

/* User Management (Admin only) */
async function getUsers() {
    const me = requireRole('admin');
    if (!me) return { ok: false, msg: 'Unauthorized' };
    
    try {
        const response = await apiCall('users.php');
        return response;
    } catch (error) {
        return { ok: false, msg: error.message };
    }
}

async function createUser(userData) {
    const me = requireRole('admin');
    if (!me) return { ok: false, msg: 'Unauthorized' };
    
    try {
        const response = await apiCall('users.php', {
            method: 'POST',
            body: JSON.stringify(userData)
        });
        return response;
    } catch (error) {
        return { ok: false, msg: error.message };
    }
}

async function createAdmin(userData) {
    const me = requireRole('admin');
    if (!me) return { ok: false, msg: 'Unauthorized' };
    
    try {
        const response = await apiCall('users.php', {
            method: 'POST',
            body: JSON.stringify({
                ...userData,
                role: 'admin'
            })
        });
        return response;
    } catch (error) {
        return { ok: false, msg: error.message };
    }
}

async function deleteUser(userId) {
    const me = requireRole('admin');
    if (!me) return { ok: false, msg: 'Unauthorized' };
    
    try {
        const response = await apiCall(`users.php?id=${userId}`, {
            method: 'DELETE'
        });
        return response;
    } catch (error) {
        return { ok: false, msg: error.message };
    }
}

/* Analytics and Reports */
async function getSalesAnalytics(farmerId = null) {
    const me = requireAuth();
    if (!me) return { ok: false, msg: 'Unauthorized' };
    
    try {
        // This would need a dedicated analytics endpoint
        // For now, we'll use the orders endpoint
        const ordersResponse = await apiCall(`orders.php?user_id=${farmerId || me.id}&role=${me.role}`);
        if (!ordersResponse.ok) return ordersResponse;
        
        const orders = ordersResponse.orders;
        const totalSales = orders.reduce((sum, order) => sum + parseFloat(order.total), 0);
        const completedOrders = orders.filter(order => order.status === 'completed').length;
        const pendingOrders = orders.filter(order => order.status === 'pending').length;
        
        return {
            ok: true,
            analytics: {
                totalOrders: orders.length,
                completedOrders,
                pendingOrders,
                totalSales,
                averageOrderValue: orders.length > 0 ? totalSales / orders.length : 0
            }
        };
    } catch (error) {
        return { ok: false, msg: error.message };
    }
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
