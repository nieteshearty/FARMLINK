<?php
require 'api/config.php';
require 'includes/session.php';

// Handle newsletter signup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'newsletter_signup') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if (!empty($fullName) && !empty($email)) {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("INSERT INTO newsletter_signups (full_name, email, phone, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$fullName, $email, $phone]);
            $success_message = "Thank you for signing up! We'll keep you updated on FARMLINK.";
        } catch (Exception $e) {
            $error_message = "Error signing up. Please try again.";
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}

// Check if user is already logged in and redirect to dashboard
if (SessionManager::isLoggedIn()) {
    $user = SessionManager::getUser();
    switch ($user['role']) {
        case 'super_admin':
            header('Location: ' . BASE_URL . '/pages/superadmin/dashboard.php');
            break;
        case 'farmer':
            header('Location: ' . BASE_URL . '/pages/farmer/farmer-dashboard.php');
            break;
        case 'buyer':
            header('Location: ' . BASE_URL . '/pages/buyer/buyer-dashboard.php');
            break;
        default:
            header('Location: ' . BASE_URL . '/pages/auth/login.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FARMLINK • Connecting Farmers, Buyers & Markets in One Click!</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/farmlink-new-logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Hero Section with Beautiful Wheat Field Background */
        .hero {
            min-height: 100vh;
            background: linear-gradient(rgba(27, 94, 32, 0.4), rgba(46, 125, 50, 0.3)), 
                        url('<?= BASE_URL ?>/assets/img/wheat-field-bg.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            display: flex;
            align-items: center;
            position: relative;
            color: white;
            overflow: hidden;
        }
        
        /* Add golden wheat texture overlay for depth */
        .hero::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 70%, rgba(255, 223, 0, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 70% 30%, rgba(255, 193, 7, 0.08) 0%, transparent 50%),
                        linear-gradient(45deg, transparent 40%, rgba(255, 235, 59, 0.05) 50%, transparent 60%);
            pointer-events: none;
            z-index: 1;
        }
        
        /* Fallback background with golden wheat field pattern if image not available */
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(rgba(27, 94, 32, 0.4), rgba(46, 125, 50, 0.3)), 
                        linear-gradient(180deg, 
                            #87CEEB 0%,     /* Sky blue */
                            #E0F6FF 25%,    /* Light sky */
                            #FFF8DC 40%,    /* Cornsilk */
                            #F0E68C 60%,    /* Khaki */
                            #DAA520 80%,    /* Goldenrod */
                            #B8860B 100%    /* Dark goldenrod */
                        );
            background-size: cover;
            background-position: center;
            z-index: -1;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        /* Show fallback if main image fails to load */
        .hero.fallback::before {
            opacity: 1;
        }

        /* Navigation */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(27, 94, 32, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.3);
            background: transparent;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
        
        .logo:hover {
            transform: scale(1.05);
            border-color: #81C784;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .brand-name {
            font-size: 1.8rem;
            font-weight: bold;
            color: #4CAF50;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 1px;
        }

        .nav-links a:hover {
            color: #81C784;
        }

        /* Hero Content */
        .hero-title {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            justify-content: flex-start;
        }
        
        .hero-logo {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            border: 5px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .hero-logo:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }
        
        .hero-title h1 {
            margin: 0;
            flex: 1;
        }

        .hero-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 4rem;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .hero-content h1 {
            font-size: 3.5rem;
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 1rem;
            text-shadow: 3px 3px 6px rgba(0,0,0,0.5);
            text-transform: uppercase;
        }

        .hero-content .tagline {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #81C784;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }

        .hero-content p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.8;
            color: rgba(255,255,255,0.9);
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4);
        }

        .btn-primary:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.6);
        }

        .btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .btn-secondary:hover {
            background: white;
            color: #1B5E20;
            transform: translateY(-2px);
        }

        /* Signup Form */
        .signup-form {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .signup-form h3 {
            color: #1B5E20;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            text-align: center;
            font-weight: 700;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #E0E0E0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            background: white;
        }

        .form-group input:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .form-group input::placeholder {
            color: #999;
        }

        .signup-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 1rem;
        }

        .signup-btn {
            flex: 1;
            background: #4CAF50;
            color: white;
            padding: 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .newsletter-btn {
            background: #4CAF50;
        }

        .newsletter-btn:hover {
            background: #45a049;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4);
        }

        .register-btn {
            background: #2E7D32;
        }

        .register-btn:hover {
            background: #1B5E20;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(46, 125, 50, 0.4);
        }

        @media (max-width: 480px) {
            .signup-buttons {
                flex-direction: column;
            }
        }

        .form-disclaimer {
            font-size: 0.8rem;
            color: #666;
            text-align: center;
            margin-top: 1rem;
            line-height: 1.4;
        }

        /* Features Section */
        .features {
            padding: 4rem 2rem;
            background: #F1F8E9;
        }

        .features-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .features h2 {
            text-align: center;
            font-size: 2.5rem;
            color: #1B5E20;
            margin-bottom: 3rem;
            font-weight: 700;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
        }

        .feature-icon {
            font-size: 3rem;
            color: #4CAF50;
            margin-bottom: 1rem;
        }

        .feature-card h3 {
            color: #1B5E20;
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }

        .feature-card p {
            color: #666;
            line-height: 1.6;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
            font-weight: 500;
        }

        .alert-success {
            background: #E8F5E8;
            color: #2E7D32;
            border: 1px solid #4CAF50;
        }

        .alert-error {
            background: #FFEBEE;
            color: #C62828;
            border: 1px solid #F44336;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-container {
                grid-template-columns: 1fr;
                gap: 2rem;
                text-align: center;
            }
            
            .signup-form {
                order: -1;
            }
            
            .hero-content h1 {
                font-size: 2.5rem;
            }
            
            .hero-title {
                justify-content: center;
                flex-direction: column;
                gap: 15px;
            }
            
            .hero-logo {
                width: 140px;
                height: 140px;
            }
            
            .nav-links {
                display: none;
            }

            .btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .hero-title {
                gap: 10px;
            }
            
            .hero-logo {
                width: 100px;
                height: 100px;
            }
            
            .hero-content h1 {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .hero-content h1 {
                font-size: 2rem;
            }

            .signup-form {
                padding: 1.5rem;
            }

            .features {
                padding: 2rem 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo-section">
                <img src="<?= BASE_URL ?>/assets/img/farmlink.png" alt="FARMLINK Logo" class="logo" onerror="this.style.display='none'">
                <span class="brand-name">FARMLINK</span>
            </div>
            <ul class="nav-links">
                <li><a href="#home">HOME</a></li>
                <li><a href="#features">FEATURES</a></li>
                <li><a href="#products">PRODUCTS</a></li>
                <li><a href="<?= BASE_URL ?>/pages/auth/login.php">LOGIN</a></li>
                <li><a href="<?= BASE_URL ?>/pages/auth/signup.php">REGISTER</a></li>
            </ul>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-title">
                    <img src="<?= BASE_URL ?>/assets/img/farmlink.png" alt="FARMLINK Logo" class="hero-logo" onerror="this.style.display='none'">
                    <h1>WELCOME TO FARMLINK</h1>
                </div>
                <div class="tagline">Connecting Farmers, Buyers & Markets</div>
                <p>Empowering farmers, connecting communities, growing together. Buy & sell effortlessly, get real-time prices, manage orders & deliveries, access anywhere.</p>
                
                <div class="hero-buttons">
                    <a href="<?= BASE_URL ?>/pages/auth/signup.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Get Started
                    </a>
                    <a href="#features" class="btn btn-secondary">
                        <i class="fas fa-info-circle"></i> Learn More
                    </a>
                </div>
            </div>

            <!-- Signup Form -->
            <div class="signup-form">
                <h3>Sign Up to Find Out More!</h3>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
                <?php endif; ?>
                
                <form method="POST" id="newsletterForm">
                    <input type="hidden" name="action" value="newsletter_signup">
                    
                    <div class="form-group">
                        <input type="text" name="full_name" placeholder="Full Name" required>
                    </div>
                    
                    <div class="form-group">
                        <input type="email" name="email" placeholder="Enter your email" required>
                    </div>
                    
                    <div class="form-group">
                        <input type="tel" name="phone" placeholder="Phone Number">
                    </div>
                    
                    <div class="signup-buttons">
                        <button type="submit" class="signup-btn newsletter-btn">
                            <i class="fas fa-envelope"></i> Get Updates
                        </button>
                        <button type="button" class="signup-btn register-btn" onclick="redirectToRegister()">
                            <i class="fas fa-user-plus"></i> Full Registration
                        </button>
                    </div>
                    
                    <p class="form-disclaimer">
                        <strong>Get Updates:</strong> Subscribe to our newsletter<br>
                        <strong>Full Registration:</strong> Create your FARMLINK account
                    </p>
                </form>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="features-container">
            <h2>Why Choose FARMLINK?</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3>Buy & Sell Effortlessly</h3>
                    <p>Connect directly with farmers and buyers. No middlemen, better prices, fresh produce delivered to your doorstep.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Get Real-Time Prices</h3>
                    <p>Access live market prices, track trends, and make informed decisions about buying and selling agricultural products.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <h3>Manage Orders & Deliveries</h3>
                    <p>Track your orders in real-time, manage deliveries efficiently, and ensure fresh produce reaches customers on time.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3>Access Anywhere</h3>
                    <p>Use FARMLINK on any device, anywhere. Our responsive platform works seamlessly on desktop, tablet, and mobile.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Community Driven</h3>
                    <p>Join a growing community of farmers and buyers working together to create a sustainable agricultural ecosystem.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-leaf"></i>
                    </div>
                    <h3>Sustainable Agriculture</h3>
                    <p>Promote sustainable farming practices, reduce food waste, and support local agricultural communities.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Products Section -->
    <section class="products-section" id="products">
        <div class="container">
            <div class="section-header">
                <h2>🌾 Fresh Products from Our Farmers</h2>
                <p>Discover quality agricultural products directly from local farmers</p>
            </div>
            
            <div class="products-grid" id="productsGrid">
                <?php
                // Distance calculation function using Haversine formula
                function calculateDistance($lat1, $lon1, $lat2, $lon2) {
                    $earthRadius = 6371; // Earth's radius in kilometers
                    
                    $dLat = deg2rad($lat2 - $lat1);
                    $dLon = deg2rad($lon2 - $lon1);
                    
                    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
                    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                    
                    return $earthRadius * $c; // Distance in kilometers
                }
                
                // Load real products directly from database
                try {
                    require_once 'api/config.php';
                    $pdo = getDBConnection();
                    
                    $stmt = $pdo->prepare("
                        SELECT 
                            p.id,
                            p.name,
                            p.description,
                            p.price,
                            p.unit,
                            p.quantity,
                            p.category,
                            p.image,
                            p.status,
                            COALESCE(u.farm_name, u.username) as farmer_name,
                            u.location as farmer_location,
                            u.city as farmer_city,
                            u.province as farmer_province,
                            u.latitude as farmer_lat,
                            u.longitude as farmer_lng,
                            u.profile_picture as farmer_image,
                            u.average_rating as farmer_rating,
                            u.total_reviews as farmer_reviews,
                            u.delivery_radius_km,
                            u.delivery_fee_per_km,
                            u.min_delivery_fee,
                            u.free_delivery_threshold,
                            u.delivery_days,
                            u.pickup_available,
                            u.delivery_available,
                            u.pickup_location
                        FROM products p
                        LEFT JOIN users u ON p.farmer_id = u.id
                        WHERE p.status = 'active' OR p.status IS NULL
                        ORDER BY p.id DESC
                    ");
                    
                    $stmt->execute();
                    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($products)) {
                        foreach ($products as $product) {
                            // Handle image path
                            $imagePath = '/FARMLINK/assets/img/product-placeholder.svg';
                            if (!empty($product['image'])) {
                                $image = $product['image'];
                                if (strpos($image, '/uploads/') === 0) {
                                    $imagePath = '/FARMLINK' . $image;
                                } elseif (strpos($image, 'uploads/') === 0) {
                                    $imagePath = '/FARMLINK/' . $image;
                                } elseif (strpos($image, '/FARMLINK/') === 0) {
                                    $imagePath = $image;
                                } else {
                                    $imagePath = '/FARMLINK/uploads/products/' . $image;
                                }
                            }
                            
                            $category = ucfirst($product['category'] ?? 'Fresh');
                            $farmerName = $product['farmer_name'] ?? 'Local Farmer';
                            
                            // Process farmer location
                            $farmerLocation = '';
                            if (!empty($product['farmer_city']) && !empty($product['farmer_province'])) {
                                $farmerLocation = $product['farmer_city'] . ', ' . $product['farmer_province'];
                            } elseif (!empty($product['farmer_location'])) {
                                $farmerLocation = $product['farmer_location'];
                            } elseif (!empty($product['farmer_city'])) {
                                $farmerLocation = $product['farmer_city'];
                            } else {
                                $farmerLocation = 'Location not specified';
                            }
                            
                            // Calculate distance if coordinates are available
                            $distanceText = '';
                            if (!empty($product['farmer_lat']) && !empty($product['farmer_lng'])) {
                                // Default buyer location (Naval, Biliran) - you can make this dynamic later
                                $buyerLat = 11.2421;
                                $buyerLng = 124.0070;
                                $distance = calculateDistance($buyerLat, $buyerLng, $product['farmer_lat'], $product['farmer_lng']);
                                if ($distance < 1) {
                                    $distanceText = '<span class="distance-badge nearby">📍 ' . number_format($distance * 1000, 0) . 'm away</span>';
                                } elseif ($distance < 50) {
                                    $distanceText = '<span class="distance-badge close">' . number_format($distance, 1) . 'km away</span>';
                                } else {
                                    $distanceText = '<span class="distance-badge far">' . number_format($distance, 0) . 'km away</span>';
                                }
                            }
                            
                            // Process farmer rating
                            $ratingDisplay = '';
                            if (!empty($product['farmer_rating']) && $product['farmer_rating'] > 0) {
                                $rating = floatval($product['farmer_rating']);
                                $reviews = intval($product['farmer_reviews'] ?? 0);
                                $stars = str_repeat('⭐', min(5, round($rating)));
                                $ratingDisplay = '<div class="farmer-rating">' . $stars . ' ' . number_format($rating, 1) . ' (' . $reviews . ' reviews)</div>';
                            }
                            
                            // Process delivery information
                            $deliveryInfo = '';
                            $deliveryRadius = intval($product['delivery_radius_km'] ?? 50);
                            $deliveryDays = $product['delivery_days'] ?? '1-2';
                            $deliveryAvailable = $product['delivery_available'] ?? true;
                            $pickupAvailable = $product['pickup_available'] ?? true;
                            $freeDeliveryThreshold = floatval($product['free_delivery_threshold'] ?? 500);
                            
                            // Check if buyer is within delivery range
                            $withinRange = true;
                            if (!empty($product['farmer_lat']) && !empty($product['farmer_lng']) && $distance <= $deliveryRadius) {
                                $withinRange = true;
                            } elseif (!empty($product['farmer_lat']) && !empty($product['farmer_lng']) && $distance > $deliveryRadius) {
                                $withinRange = false;
                            }
                            
                            // Build delivery options display
                            $deliveryOptions = [];
                            if ($deliveryAvailable && $withinRange) {
                                $deliveryOptions[] = '🚚 Delivery (' . $deliveryDays . ' days)';
                            }
                            if ($pickupAvailable) {
                                $deliveryOptions[] = '📦 Pickup available';
                            }
                            if (!$deliveryAvailable && !$pickupAvailable) {
                                $deliveryOptions[] = '❌ No delivery options';
                            } elseif ($deliveryAvailable && !$withinRange) {
                                $deliveryOptions[] = '⚠️ Outside delivery area';
                            }
                            
                            $deliveryInfo = '<div class="delivery-options">' . implode(' • ', $deliveryOptions) . '</div>';
                            
                            // Free delivery badge
                            $freeDeliveryBadge = '';
                            if ($deliveryAvailable && $freeDeliveryThreshold > 0) {
                                $freeDeliveryBadge = '<div class="free-delivery-info">📦 Free delivery over ₱' . number_format($freeDeliveryThreshold, 0) . '</div>';
                            }
                            ?>
                            <div class="product-card" onclick="redirectToAuth('<?= htmlspecialchars($product['name']) ?>')">
                                <div class="product-badge"><?= htmlspecialchars($category) ?></div>
                                <?= $distanceText ?>
                                <img src="<?= htmlspecialchars($imagePath) ?>" 
                                     alt="<?= htmlspecialchars($product['name']) ?>" 
                                     class="product-image" 
                                     onerror="handleImageError(this, '<?= htmlspecialchars($product['name']) ?>')"
                                     onload="this.style.opacity='1'"
                                     style="opacity:0; transition: opacity 0.3s ease;">
                                <div class="product-info">
                                    <h3 class="product-name"><?= htmlspecialchars($product['name']) ?></h3>
                                    
                                    <!-- Enhanced Farmer Information -->
                                    <div class="farmer-info-section">
                                        <div class="product-farmer">
                                            <i class="fas fa-user-tie"></i>
                                            <strong><?= htmlspecialchars($farmerName) ?></strong>
                                        </div>
                                        <div class="farmer-location">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?= htmlspecialchars($farmerLocation) ?>
                                        </div>
                                        <?= $ratingDisplay ?>
                                    </div>
                                    
                                    <div class="product-price">₱<?= number_format($product['price'], 2) ?> / <?= htmlspecialchars($product['unit']) ?></div>
                                    <p class="product-description"><?= htmlspecialchars($product['description'] ?? 'Fresh agricultural product from local farmers') ?></p>
                                    
                                    <!-- Delivery Information Section -->
                                    <div class="delivery-info-section">
                                        <?= $deliveryInfo ?>
                                        <?= $freeDeliveryBadge ?>
                                    </div>
                                    
                                    <div class="product-stock">
                                        <div class="stock-indicator <?= $product['quantity'] < 10 ? 'low' : '' ?>"></div>
                                        <?= $product['quantity'] ?> <?= htmlspecialchars($product['unit']) ?> available
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        ?>
                        <div class="loading-spinner">
                            <i class="fas fa-seedling"></i>
                            <p>No products available at the moment. <a href="init-products.php">Initialize products</a></p>
                        </div>
                        <?php
                    }
                } catch (Exception $e) {
                    ?>
                    <div class="loading-spinner">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Unable to load products. Error: <?= htmlspecialchars($e->getMessage()) ?></p>
                        <p><a href="check-products.php">Check database status</a></p>
                    </div>
                    <?php
                }
                ?>
            </div>
            
            <div class="view-more-section">
                <button class="btn btn-outline" onclick="redirectToAuth()">
                    <i class="fas fa-shopping-cart"></i> View All Products
                </button>
                <p class="auth-prompt">Sign up to browse all products and start shopping!</p>
            </div>
        </div>
    </section>

    <style>
        /* Products Section Styling */
        .products-section {
            padding: 80px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            position: relative;
        }
        
        .products-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="10" cy="10" r="1" fill="%23e8f5e8" opacity="0.3"/><circle cx="90" cy="20" r="1.5" fill="%23c8e6c9" opacity="0.4"/><circle cx="30" cy="80" r="1" fill="%23a5d6a7" opacity="0.3"/><circle cx="70" cy="70" r="1.2" fill="%23e8f5e8" opacity="0.5"/></svg>');
            background-size: 100px 100px;
            opacity: 0.6;
            pointer-events: none;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 50px;
            position: relative;
            z-index: 2;
        }
        
        .section-header h2 {
            font-size: 2.5rem;
            color: #2E7D32;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .section-header p {
            font-size: 1.2rem;
            color: #666;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
            position: relative;
            z-index: 2;
        }
        
        .product-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
        
        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(46, 125, 50, 0.2);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .product-card:hover .product-image {
            transform: scale(1.05);
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-name {
            font-size: 1.3rem;
            font-weight: bold;
            color: #2E7D32;
            margin-bottom: 8px;
            line-height: 1.3;
        }
        
        .product-farmer {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .product-price {
            font-size: 1.4rem;
            font-weight: bold;
            color: #FF6B35;
            margin-bottom: 10px;
        }
        
        .product-description {
            color: #777;
            font-size: 0.9rem;
            line-height: 1.4;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #4CAF50;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .product-stock {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #666;
            font-size: 0.85rem;
        }
        
        .stock-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #4CAF50;
        }
        
        .stock-indicator.low {
            background: #FF9800;
        }
        
        .stock-indicator.out {
            background: #F44336;
        }
        
        /* Enhanced Farmer Information Styles */
        .farmer-info-section {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 12px;
            border-left: 3px solid #4CAF50;
        }
        
        .product-farmer {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 6px;
            font-size: 0.9rem;
            color: #2E7D32;
        }
        
        .product-farmer strong {
            font-weight: 600;
        }
        
        .farmer-location {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 6px;
        }
        
        .farmer-location i {
            color: #FF6B35;
        }
        
        .farmer-rating {
            font-size: 0.8rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        /* Distance Badge Styles */
        .distance-badge {
            position: absolute;
            top: 45px;
            right: 15px;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            color: white;
            z-index: 2;
            backdrop-filter: blur(5px);
        }
        
        .distance-badge.nearby {
            background: linear-gradient(45deg, #4CAF50, #66BB6A);
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
        }
        
        .distance-badge.close {
            background: linear-gradient(45deg, #FF9800, #FFB74D);
            box-shadow: 0 2px 8px rgba(255, 152, 0, 0.3);
        }
        
        .distance-badge.far {
            background: linear-gradient(45deg, #757575, #9E9E9E);
            box-shadow: 0 2px 8px rgba(117, 117, 117, 0.3);
        }
        
        /* Delivery Information Styles */
        .delivery-info-section {
            background: #f0f8f0;
            padding: 10px;
            border-radius: 6px;
            margin: 10px 0;
            border-left: 3px solid #4CAF50;
        }
        
        .delivery-options {
            font-size: 0.85rem;
            color: #2E7D32;
            font-weight: 500;
            margin-bottom: 5px;
            line-height: 1.4;
        }
        
        .free-delivery-info {
            font-size: 0.8rem;
            color: #FF6B35;
            font-weight: 600;
            background: rgba(255, 107, 53, 0.1);
            padding: 3px 6px;
            border-radius: 4px;
            display: inline-block;
        }
        
        .delivery-options:contains("❌") {
            color: #d32f2f;
        }
        
        .delivery-options:contains("⚠️") {
            color: #f57c00;
        }
        
        .loading-spinner {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .loading-spinner i {
            font-size: 2rem;
            margin-bottom: 15px;
            color: #4CAF50;
        }
        
        .view-more-section {
            text-align: center;
            position: relative;
            z-index: 2;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid #2E7D32;
            color: #2E7D32;
            padding: 15px 30px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: bold;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .btn-outline:hover {
            background: #2E7D32;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 125, 50, 0.3);
        }
        
        .auth-prompt {
            color: #666;
            font-style: italic;
            margin: 0;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
            }
            
            .section-header h2 {
                font-size: 2rem;
            }
            
            .product-info {
                padding: 15px;
            }
            
            .farmer-info-section {
                padding: 10px;
            }
            
            .distance-badge {
                top: 40px;
                right: 10px;
                font-size: 0.7rem;
                padding: 3px 6px;
            }
        }
        
        @media (max-width: 480px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .section-header h2 {
                font-size: 1.8rem;
            }
            
            .farmer-info-section {
                padding: 8px;
                margin-bottom: 10px;
            }
            
            .product-farmer, .farmer-location {
                font-size: 0.8rem;
            }
            
            .farmer-rating {
                font-size: 0.75rem;
            }
            
            .distance-badge {
                top: 35px;
                right: 8px;
                font-size: 0.65rem;
                padding: 2px 5px;
            }
        }
    </style>

    <script>
        // Products are now loaded directly via PHP, no need for JavaScript loading
        function initializeProducts() {
            
            // Just ensure images have proper fade-in effect
            const images = document.querySelectorAll('.product-image');
            images.forEach(img => {
                if (img.complete) {
                    img.style.opacity = '1';
                }
            });
        }

        // Handle image loading errors with fallbacks
        function handleImageError(img, productName) {
            
            // Create a colored placeholder based on product name
            const canvas = document.createElement('canvas');
            canvas.width = 400;
            canvas.height = 300;
            const ctx = canvas.getContext('2d');
            
            // Set background color based on product
            const colors = {
                'Petchay': '#4CAF50',
                'Onion': '#FF9800', 
                'Garlic': '#FFF8DC',
                'Luya': '#FFD700',
                'Mani': '#8D6E63',
                'Papaya': '#FF6B35',
                'Cauliflower': '#F5F5F5'
            };
            
            const bgColor = colors[productName] || '#E8F5E8';
            ctx.fillStyle = bgColor;
            ctx.fillRect(0, 0, 400, 300);
            
            // Add product name text
            ctx.fillStyle = '#2E7D32';
            ctx.font = 'bold 24px Arial';
            ctx.textAlign = 'center';
            ctx.fillText(productName, 200, 150);
            
            // Add vegetable icon
            ctx.font = '48px Arial';
            ctx.fillText('🌱', 200, 120);
            
            // Set the canvas as image source
            img.src = canvas.toDataURL();
            img.style.opacity = '1';
        }

        // Redirect to signup page when product is clicked
        function redirectToAuth(productName = '') {
            // Create a more attractive modal-style confirmation
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 10000;
                backdrop-filter: blur(5px);
            `;
            
            modal.innerHTML = `
                <div style="
                    background: white;
                    padding: 40px;
                    border-radius: 20px;
                    text-align: center;
                    max-width: 500px;
                    margin: 20px;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
                ">
                    <div style="font-size: 3rem; margin-bottom: 20px;">🛒</div>
                    <h2 style="color: #2E7D32; margin-bottom: 15px; font-size: 1.5rem;">
                        Interested in ${productName}?
                    </h2>
                    <p style="color: #666; margin-bottom: 30px; line-height: 1.5;">
                        To view detailed information, check availability, and purchase <strong>${productName}</strong>, 
                        you need to create a FARMLINK account first.
                    </p>
                    <div style="display: flex; gap: 15px; justify-content: center;">
                        <button onclick="goToSignup('${productName}')" style="
                            background: #2E7D32;
                            color: white;
                            border: none;
                            padding: 15px 30px;
                            border-radius: 50px;
                            font-size: 1rem;
                            font-weight: bold;
                            cursor: pointer;
                            transition: all 0.3s ease;
                        ">
                            <i class="fas fa-user-plus"></i> Sign Up Now
                        </button>
                        <button onclick="closeModal()" style="
                            background: transparent;
                            color: #666;
                            border: 2px solid #ddd;
                            padding: 15px 30px;
                            border-radius: 50px;
                            font-size: 1rem;
                            cursor: pointer;
                            transition: all 0.3s ease;
                        ">
                            Maybe Later
                        </button>
                    </div>
                    <p style="color: #999; font-size: 0.9rem; margin-top: 20px;">
                        Already have an account? <a href="/FARMLINK/pages/auth/login.php" style="color: #2E7D32; text-decoration: none; font-weight: bold;">Login here</a>
                    </p>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Add click outside to close
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal();
                }
            });
        }
        
        function goToSignup(productName) {
            window.location.href = '/FARMLINK/pages/auth/signup.php' + (productName ? '?product=' + encodeURIComponent(productName) : '');
        }
        
        function closeModal() {
            const modal = document.querySelector('div[style*="position: fixed"]');
            if (modal) {
                modal.remove();
            }
        }

        // Check if background image loads, use fallback if not
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize products (already loaded via PHP)
            initializeProducts();
            
            const hero = document.querySelector('.hero');
            const img = new Image();
            
            img.onload = function() {
                // Image loaded successfully, keep current background
            };
            
            img.onerror = function() {
                // Image failed to load, use fallback pattern
                hero.classList.add('fallback');
            };
            
            img.src = '/FARMLINK/assets/img/rice-field-bg.jpg';
        });

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Navbar background on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(27, 94, 32, 0.98)';
            } else {
                navbar.style.background = 'rgba(27, 94, 32, 0.95)';
            }
        });

        // Registration redirect function
        function redirectToRegister() {
            const form = document.getElementById('newsletterForm');
            const name = form.querySelector('input[name="full_name"]').value;
            const email = form.querySelector('input[name="email"]').value;
            const phone = form.querySelector('input[name="phone"]').value;
            
            // Build URL with pre-filled data
            let registerUrl = '/FARMLINK/pages/auth/signup.php';
            const params = new URLSearchParams();
            
            if (name) params.append('name', name);
            if (email) params.append('email', email);
            if (phone) params.append('phone', phone);
            
            if (params.toString()) {
                registerUrl += '?' + params.toString();
            }
            
            window.location.href = registerUrl;
        }

        // Form validation and enhancement
        document.querySelector('#newsletterForm').addEventListener('submit', function(e) {
            const email = this.querySelector('input[name="email"]').value;
            const name = this.querySelector('input[name="full_name"]').value;
            
            if (!email || !name) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            // Simple email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
            
            // Show success message for newsletter signup
            
            // Add visual feedback
            const submitBtn = this.querySelector('.newsletter-btn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            submitBtn.disabled = true;
            
            // Re-enable after a moment (form will redirect anyway)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 2000);
        });

        // Add subtle parallax effect to background
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const hero = document.querySelector('.hero');
            const rate = scrolled * -0.5;
            
            if (hero) {
                hero.style.backgroundPosition = `center ${rate}px`;
            }
        });
    </script>
</body>
</html>