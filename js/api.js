// ============================================================
// API Configuration - TokoOlahraga
// ============================================================
const API_BASE_URL = 'php/api/';

class API {
    static async request(endpoint, method = 'GET', data = null) {
        const options = {
            method: method,
            headers: { 'Content-Type': 'application/json' }
        };

        if (data && (method === 'POST' || method === 'PUT' || method === 'DELETE')) {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(`${API_BASE_URL}${endpoint}.php`, options);
            if (!response.ok && response.status !== 400 && response.status !== 401 && response.status !== 404) {
                console.error(`HTTP Error: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('API Error:', error);
            return { success: false, message: 'Network error: ' + error.message };
        }
    }

    // Auth
    static async register(userData) {
        return await this.request('auth', 'POST', { action: 'register', ...userData });
    }

    static async login(email, password) {
        return await this.request('auth', 'POST', { action: 'login', email, password });
    }

    static async verifyOtp(userId, otp) {
        return await this.request('auth', 'POST', { action: 'verify_otp', user_id: userId, otp });
    }

    static async resendOtp(userId) {
        return await this.request('auth', 'POST', { action: 'resend_otp', user_id: userId });
    }

    static async getUser(userId) {
        return await this.request(`auth?user_id=${userId}`, 'GET');
    }

    static async updateUser(userId, data) {
        return await this.request('auth', 'PUT', { user_id: userId, ...data });
    }

    static async deleteUser(userId) {
        return await this.request('auth', 'DELETE', { user_id: userId });
    }

    // Products
    static async getProducts() {
        return await this.request('products', 'GET');
    }

    // AI Recommendations
    static async getRecommendations(userId) {
        return await this.request('ai-recommend', 'POST', { user_id: userId });
    }

    // Orders - sertakan email agar notifikasi terkirim
    static async createOrder(orderData) {
        const localUser = JSON.parse(localStorage.getItem('user') || 'null') || {};
        const customerEmail = orderData.email || localUser.email || '';
        return await this.request('orders', 'POST', {
            action: 'create_order',
            email: customerEmail,
            ...orderData
        });
    }

    static async getUserOrders(userId) {
        return await this.request(`orders?user_id=${userId}`, 'GET');
    }
}
