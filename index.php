<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CASMS - Car Auto Service Management System</title>
    <meta name="description" content="Car auto service management system. Book appointments, track service history, and get emergency roadside assistance 24/7.">
    <meta name="keywords" content="car service, auto repair, vehicle maintenance, car workshop, mechanic, Nairobi">
    <meta name="author" content="CASMS">
    
    <!-- Open Graph / Social Media Meta Tags -->
    <meta property="og:title" content="CASMS - Car Auto Service Management System">
    <meta property="og:description" content="Car Auto Service Management services with online booking and service tracking">
    <meta property="og:type" content="website">
    <meta property="og:image" content="assets/images/casms-og.jpg">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --primary-light: #3b82f6;
            --secondary: #10b981;
            --secondary-dark: #059669;
            --accent: #f59e0b;
            --dark: #1f2937;
            --light: #f9fafb;
            --gray: #6b7280;
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
            --info: #3b82f6;
            --gradient-primary: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            --gradient-secondary: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --gradient-accent: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --gradient-hero: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            --box-shadow-lg: 0 30px 60px rgba(0,0,0,0.15);
            --border-radius: 12px;
            --border-radius-lg: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: var(--dark);
            background: var(--light);
            overflow-x: hidden;
        }

        /* Preloader */
        .preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease;
        }
        .preloader.fade-out {
            opacity: 0;
            pointer-events: none;
        }
        .loader {
            width: 60px;
            height: 60px;
            border: 5px solid var(--primary-light);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Custom Navbar */
        .navbar {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
            transition: var(--transition);
            box-shadow: 0 2px 20px rgba(0,0,0,0.05);
        }
        .navbar.scrolled {
            padding: 0.7rem 0;
            background: rgba(255,255,255,0.98);
            box-shadow: 0 5px 30px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: 800;
            font-size: 1.8rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .nav-link {
            font-weight: 500;
            color: var(--dark) !important;
            margin: 0 0.5rem;
            position: relative;
            transition: var(--transition);
        }
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 3px;
            background: var(--gradient-primary);
            transition: var(--transition);
            border-radius: 3px;
        }
        .nav-link:hover::after {
            width: 80%;
        }
        .btn-login {
            background: var(--gradient-primary);
            color: white !important;
            padding: 0.6rem 1.5rem !important;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            border: none;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(37,99,235,0.3);
        }
        .btn-login::after {
            display: none;
        }

        /* Hero Section */
        .hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gradient-hero);
            overflow: hidden;
            text-align: center;
        }
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.08" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,165.3C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
            background-size: cover;
            background-position: bottom;
            opacity: 0.15;
        }
        .hero-content {
            position: relative;
            z-index: 2;
            color: white;
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            text-align: center;
        }
        .hero-badge {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(5px);
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 2rem;
            font-weight: 500;
        }
        .hero-title {
            font-size: 4.5rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1.5rem;
        }
        .hero-title span {
            color: #fbbf24;
            display: inline-block;
        }
        .hero-description {
            font-size: 1.2rem;
            opacity: 0.95;
            margin-bottom: 2rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 3rem;
            margin-top: 3rem;
            flex-wrap: wrap;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: #fbbf24;
        }
        .stat-label {
            opacity: 0.9;
            font-weight: 500;
        }

        /* Services Section */
        .services {
            padding: 6rem 0;
            background: white;
        }
        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }
        .section-subtitle {
            color: var(--primary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 1rem;
        }
        .section-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
        }
        .section-desc {
            color: var(--gray);
            max-width: 600px;
            margin: 0 auto;
        }
        .service-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            transition: var(--transition);
            height: 100%;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(37,99,235,0.1);
        }
        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--box-shadow);
            border-color: transparent;
        }
        .service-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
            transform: scaleX(0);
            transition: var(--transition);
        }
        .service-card:hover::before {
            transform: scaleX(1);
        }
        .service-icon {
            width: 70px;
            height: 70px;
            background: var(--gradient-primary);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            color: white;
            font-size: 1.8rem;
            transition: var(--transition);
        }
        .service-card:hover .service-icon {
            border-radius: 50%;
            transform: rotate(360deg);
        }
        .service-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .service-description {
            color: var(--gray);
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }
        .service-price {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        .service-price small {
            font-size: 0.9rem;
            font-weight: 400;
            color: var(--gray);
        }
        .service-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            color: var(--gray);
            font-size: 0.9rem;
        }
        .btn-service {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
            padding: 0.6rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
            width: 100%;
            text-align: center;
        }
        .btn-service:hover {
            background: var(--gradient-primary);
            border-color: transparent;
            color: white;
        }

        /* Features Section */
        .features {
            padding: 6rem 0;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }
        .feature-box {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            transition: var(--transition);
            height: 100%;
        }
        .feature-box:hover {
            transform: translateY(-5px);
            box-shadow: var(--box-shadow);
        }
        .feature-icon {
            width: 80px;
            height: 80px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2rem;
        }
        .feature-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
        }
        .feature-desc {
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Emergency Section */
        .emergency {
            position: relative;
            padding: 5rem 0;
            background: var(--gradient-primary);
            color: white;
            overflow: hidden;
            text-align: center;
        }
        .emergency::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 70%;
            height: 200%;
            background: rgba(255,255,255,0.08);
            transform: rotate(35deg);
        }
        .emergency-content {
            position: relative;
            z-index: 2;
        }
        .emergency-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
        }
        .emergency-phone {
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            background: white;
            color: var(--primary);
            padding: 1rem 3rem;
            border-radius: 50px;
            font-size: 2rem;
            font-weight: 700;
            text-decoration: none;
            transition: var(--transition);
            margin: 1.5rem 0;
        }
        .emergency-phone:hover {
            transform: scale(1.05);
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            color: var(--primary-dark);
        }

        /* Pricing Section */
        .pricing {
            padding: 6rem 0;
            background: white;
        }
        .pricing-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 3rem 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            transition: var(--transition);
            height: 100%;
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
        }
        .pricing-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--box-shadow-lg);
            border-color: var(--primary);
        }
        .pricing-card.popular {
            border: 2px solid var(--primary);
            transform: scale(1.05);
        }
        .pricing-card.popular:hover {
            transform: scale(1.05) translateY(-5px);
        }
        .popular-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--gradient-primary);
            color: white;
            padding: 0.3rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .pricing-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .pricing-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .pricing-price {
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary);
        }
        .pricing-price small {
            font-size: 1rem;
            font-weight: 400;
            color: var(--gray);
        }
        .pricing-features {
            list-style: none;
            padding: 0;
            margin-bottom: 2rem;
        }
        .pricing-features li {
            padding: 0.8rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .pricing-features li i {
            color: var(--secondary);
            margin-right: 0.5rem;
        }
        .btn-pricing {
            background: var(--gradient-primary);
            color: white;
            padding: 1rem;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            display: block;
            text-align: center;
            transition: var(--transition);
        }
        .btn-pricing:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(37,99,235,0.3);
            color: white;
        }

        /* Testimonials */
        .testimonials {
            padding: 6rem 0;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }
        .testimonial-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            transition: var(--transition);
            height: 100%;
        }
        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--box-shadow);
        }
        .testimonial-rating {
            color: #fbbf24;
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }
        .testimonial-text {
            font-size: 0.95rem;
            line-height: 1.7;
            color: var(--gray);
            margin-bottom: 1.5rem;
            font-style: italic;
        }
        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .author-avatar {
            width: 50px;
            height: 50px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
        }
        .author-info h5 {
            font-weight: 700;
            margin-bottom: 0.2rem;
            font-size: 1rem;
        }
        .author-info p {
            color: var(--gray);
            font-size: 0.8rem;
            margin: 0;
        }

        /* CTA Section */
        .cta {
            padding: 5rem 0;
            background: var(--gradient-primary);
            color: white;
            text-align: center;
        }
        .cta-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
        }
        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
        }
        .btn-cta-primary {
            background: white;
            color: var(--primary);
            padding: 1rem 2.5rem;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }
        .btn-cta-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255,255,255,0.3);
            color: var(--primary-dark);
        }
        .btn-cta-secondary {
            background: transparent;
            color: white;
            padding: 1rem 2.5rem;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            border: 2px solid white;
            transition: var(--transition);
        }
        .btn-cta-secondary:hover {
            background: white;
            color: var(--primary);
            transform: translateY(-2px);
        }

        /* Footer */
        .footer {
            background: var(--dark);
            color: white;
            padding: 4rem 0 2rem;
        }
        .footer-brand {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 1rem;
            display: inline-block;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }
        .footer-text {
            color: #9ca3af;
            line-height: 1.7;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        .social-links {
            display: flex;
            gap: 1rem;
        }
        .social-link {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: var(--transition);
            text-decoration: none;
        }
        .social-link:hover {
            background: var(--gradient-primary);
            transform: translateY(-3px);
        }
        .footer-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: white;
        }
        .footer-links {
            list-style: none;
            padding: 0;
        }
        .footer-links li {
            margin-bottom: 0.8rem;
        }
        .footer-links a {
            color: #9ca3af;
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.9rem;
        }
        .footer-links a:hover {
            color: white;
            padding-left: 5px;
        }
        .footer-contact {
            list-style: none;
            padding: 0;
        }
        .footer-contact li {
            color: #9ca3af;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-size: 0.9rem;
        }
        .footer-contact i {
            color: var(--primary);
            width: 20px;
        }
        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: 3rem;
            padding-top: 2rem;
            text-align: center;
            color: #9ca3af;
            font-size: 0.85rem;
        }

        /* Back to Top Button */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: var(--gradient-primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            z-index: 99;
            box-shadow: var(--box-shadow);
        }
        .back-to-top.show {
            opacity: 1;
            visibility: visible;
        }
        .back-to-top:hover {
            transform: translateY(-5px);
            color: white;
        }

        @media (max-width: 992px) {
            .hero-title {
                font-size: 3rem;
            }
            .section-title {
                font-size: 2rem;
            }
            .pricing-card.popular {
                transform: scale(1);
            }
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.2rem;
            }
            .hero-stats {
                flex-direction: column;
                gap: 1rem;
                align-items: center;
            }
            .emergency-phone {
                font-size: 1.2rem;
                padding: 0.8rem 1.5rem;
            }
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            .cta-title {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Preloader -->
    <div class="preloader">
        <div class="loader"></div>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-car-side me-2"></i>CASMS
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#services">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#pricing">Pricing</a></li>
                    <li class="nav-item"><a class="nav-link" href="#testimonials">Testimonials</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                </ul>
                <div class="d-flex gap-2">
                    <a href="simple_login.php" class="btn-login nav-link">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </a>
                    <a href="simple_register.php" class="btn-login nav-link" style="background: var(--gradient-secondary);">
                        <i class="fas fa-user-plus me-2"></i>Register
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="hero-overlay"></div>
        <div class="container">
            <div class="hero-content" data-aos="fade-up">
                <div class="hero-badge">
                    <i class="fas fa-star me-2"></i>Since 2010 | Licensed & Certified
                </div>
                <h1 class="hero-title">
                    CASMS <span>Auto Care</span><br>You Can Trust
                </h1>
                <p class="hero-description">
                    Experience excellence in automotive service with CASMS. From routine maintenance to emergency repairs, we deliver quality, transparency, and convenience.
                </p>
                <div class="hero-buttons">
                    <a href="#services" class="btn-login" style="padding: 1rem 2rem;">
                        <i class="fas fa-wrench me-2"></i>Our Services
                    </a>
                    <a href="simple_login.php" class="btn-login" style="background: var(--gradient-secondary); padding: 1rem 2rem;">
                        <i class="fas fa-calendar-alt me-2"></i>Book Appointment
                    </a>
                    <a href="simple_login.php" class="btn-login" style="background: var(--gradient-accent); padding: 1rem 2rem;">
                        <i class="fas fa-exclamation-triangle me-2"></i>Emergency
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="stat-item">
                        <div class="stat-number">50+</div>
                        <div class="stat-label">Services</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">5000+</div>
                        <div class="stat-label">Happy Clients</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">25+</div>
                        <div class="stat-label">Expert Mechanics</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">Emergency Support</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="services">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <div class="section-subtitle">Our Premium Services</div>
                <h2 class="section-title">Comprehensive Auto Care Solutions</h2>
                <p class="section-desc">Professional services tailored to keep your vehicle in peak condition</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="service-card">
                        <div class="service-icon"><i class="fas fa-oil-can"></i></div>
                        <h3 class="service-title">Oil Change Service</h3>
                        <p class="service-description">Complete oil change with premium quality oils and filter replacement. Includes multi-point inspection.</p>
                        <div class="service-meta"><span><i class="far fa-clock me-1"></i> 45 mins</span><span><i class="fas fa-tag me-1"></i> Starting from</span></div>
                        <div class="service-price">KSh 3,500 <small>+</small></div>
                        <a href="simple_login.php?service=1" class="btn-service">Book Now <i class="fas fa-arrow-right ms-2"></i></a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="service-card">
                        <div class="service-icon"><i class="fas fa-brake-warning"></i></div>
                        <h3 class="service-title">Brake System Service</h3>
                        <p class="service-description">Comprehensive brake inspection, pad replacement, rotor resurfacing, and fluid check.</p>
                        <div class="service-meta"><span><i class="far fa-clock me-1"></i> 1.5 hours</span><span><i class="fas fa-tag me-1"></i> Starting from</span></div>
                        <div class="service-price">KSh 4,500 <small>+</small></div>
                        <a href="simple_login.php?service=2" class="btn-service">Book Now <i class="fas fa-arrow-right ms-2"></i></a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="service-card">
                        <div class="service-icon"><i class="fas fa-tachometer-alt"></i></div>
                        <h3 class="service-title">Engine Diagnostics</h3>
                        <p class="service-description">Advanced computer diagnostics to identify engine issues, performance problems, and error codes.</p>
                        <div class="service-meta"><span><i class="far fa-clock me-1"></i> 1 hour</span><span><i class="fas fa-tag me-1"></i> Starting from</span></div>
                        <div class="service-price">KSh 2,500 <small>+</small></div>
                        <a href="simple_login.php?service=3" class="btn-service">Book Now <i class="fas fa-arrow-right ms-2"></i></a>
                    </div>
                </div>
            </div>
            <div class="text-center mt-5" data-aos="fade-up">
                <a href="services.php" class="btn-login" style="padding: 1rem 3rem;">View All Services <i class="fas fa-arrow-right ms-2"></i></a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <div class="section-subtitle">Why Choose CASMS</div>
                <h2 class="section-title">The CASMS Advantage</h2>
                <p class="section-desc">Experience the difference with our premium auto care services</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-box">
                        <div class="feature-icon"><i class="fas fa-clock"></i></div>
                        <h4 class="feature-title">24/7 Emergency</h4>
                        <p class="feature-desc">Round-the-clock emergency service for your peace of mind</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-box">
                        <div class="feature-icon"><i class="fas fa-certificate"></i></div>
                        <h4 class="feature-title">Certified Mechanics</h4>
                        <p class="feature-desc">Expert technicians with years of experience</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-box">
                        <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                        <h4 class="feature-title">Genuine Parts</h4>
                        <p class="feature-desc">100% authentic spare parts with warranty</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-box">
                        <div class="feature-icon"><i class="fas fa-mobile-alt"></i></div>
                        <h4 class="feature-title">Online Booking</h4>
                        <p class="feature-desc">Easy appointment scheduling via web or mobile</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Emergency Section -->
    <section class="emergency">
        <div class="container">
            <div class="emergency-content" data-aos="zoom-in">
                <h2 class="emergency-title">
                    <i class="fas fa-exclamation-triangle me-3"></i>24/7 Emergency Roadside Assistance
                </h2>
                <p style="font-size: 1.1rem; opacity: 0.95; margin-bottom: 1rem;">
                    Stuck on the road? We're here to help anytime, anywhere in Nairobi
                </p>
                <a href="tel:+254700999999" class="emergency-phone">
                    <i class="fas fa-phone-alt"></i>
                    <span>+254 700 999 999</span>
                </a>
                <p style="opacity: 0.85;">
                    <i class="fas fa-check-circle me-2"></i>
                    Towing • Battery Jumpstart • Flat Tire • Fuel Delivery • Lockout Service
                </p>
                <div class="mt-4">
                    <a href="simple_login.php" class="btn-login" style="background: white; color: var(--primary); padding: 0.8rem 2rem;">
                        Request Emergency Service <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="pricing">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <div class="section-subtitle">Transparent Pricing</div>
                <h2 class="section-title">Simple & Affordable Plans</h2>
                <p class="section-desc">Choose the perfect plan for your vehicle's needs</p>
            </div>
            <div class="row g-4 align-items-center">
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="pricing-card">
                        <div class="pricing-header">
                            <h3 class="pricing-title">Basic Service</h3>
                            <div class="pricing-price">KSh 3,500 <small>/visit</small></div>
                        </div>
                        <ul class="pricing-features">
                            <li><i class="fas fa-check"></i> Oil Change (Premium Oil)</li>
                            <li><i class="fas fa-check"></i> Oil Filter Replacement</li>
                            <li><i class="fas fa-check"></i> Multi-point Inspection</li>
                            <li><i class="fas fa-check"></i> Fluid Top-up</li>
                            <li><i class="fas fa-check"></i> Tire Pressure Check</li>
                            <li class="text-muted"><i class="fas fa-times"></i> Brake Inspection</li>
                        </ul>
                        <a href="simple_login.php" class="btn-pricing">Choose Plan</a>
                    </div>
                </div>
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="pricing-card popular">
                        <div class="popular-badge">Most Popular</div>
                        <div class="pricing-header">
                            <h3 class="pricing-title">Premium Service</h3>
                            <div class="pricing-price">KSh 6,500 <small>/visit</small></div>
                        </div>
                        <ul class="pricing-features">
                            <li><i class="fas fa-check"></i> Everything in Basic</li>
                            <li><i class="fas fa-check"></i> Brake System Check</li>
                            <li><i class="fas fa-check"></i> AC Performance Test</li>
                            <li><i class="fas fa-check"></i> Battery Test</li>
                            <li><i class="fas fa-check"></i> Engine Diagnostics</li>
                            <li><i class="fas fa-check"></i> 3-Month Warranty</li>
                        </ul>
                        <a href="simple_login.php" class="btn-pricing">Choose Plan</a>
                    </div>
                </div>
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="pricing-card">
                        <div class="pricing-header">
                            <h3 class="pricing-title">Comprehensive</h3>
                            <div class="pricing-price">KSh 12,500 <small>/visit</small></div>
                        </div>
                        <ul class="pricing-features">
                            <li><i class="fas fa-check"></i> Everything in Premium</li>
                            <li><i class="fas fa-check"></i> Full Engine Tune-up</li>
                            <li><i class="fas fa-check"></i> Transmission Service</li>
                            <li><i class="fas fa-check"></i> Cooling System Flush</li>
                            <li><i class="fas fa-check"></i> 6-Month Warranty</li>
                            <li><i class="fas fa-check"></i> Free Pick-up & Drop</li>
                        </ul>
                        <a href="simple_login.php" class="btn-pricing">Choose Plan</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="testimonials">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <div class="section-subtitle">Client Testimonials</div>
                <h2 class="section-title">What Our Customers Say</h2>
                <p class="section-desc">Real experiences from satisfied vehicle owners</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="testimonial-card">
                        <div class="testimonial-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p class="testimonial-text">"Excellent service! They diagnosed and fixed my car's AC issue quickly. Very professional and reasonably priced. Highly recommended!"</p>
                        <div class="testimonial-author">
                            <div class="author-avatar">JM</div>
                            <div class="author-info">
                                <h5>John Mwangi</h5>
                                <p>Mercedes-Benz C200</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="testimonial-card">
                        <div class="testimonial-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p class="testimonial-text">"I love their online booking system. Very convenient and they send reminders. The service quality is top-notch and they use genuine parts."</p>
                        <div class="testimonial-author">
                            <div class="author-avatar">AW</div>
                            <div class="author-info">
                                <h5>Alice Wanjiku</h5>
                                <p>Toyota Land Cruiser</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="testimonial-card">
                        <div class="testimonial-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p class="testimonial-text">"Great experience with their emergency service. They arrived within 30 minutes and got my car running again. Thank you CASMS!"</p>
                        <div class="testimonial-author">
                            <div class="author-avatar">DK</div>
                            <div class="author-info">
                                <h5>David Kipchoge</h5>
                                <p>BMW X5</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <div data-aos="fade-up">
                <h2 class="cta-title">Ready to Give Your Car the Best Care?</h2>
                <p class="mb-4 fs-5">Join thousands of satisfied customers who trust us with their vehicles</p>
                <div class="cta-buttons">
                    <a href="simple_login.php" class="btn-cta-primary">
                        <i class="fas fa-calendar-check me-2"></i>Book Appointment
                    </a>
                    <a href="contact.php" class="btn-cta-secondary">
                        <i class="fas fa-envelope me-2"></i>Contact Us
                    </a>
                    <a href="spare-parts.php" class="btn-cta-secondary">
                        <i class="fas fa-shopping-cart me-2"></i>Shop Parts
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact" class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <a href="index.php" class="footer-brand">
                        <i class="fas fa-car-side me-2"></i>CASMS
                    </a>
                    <p class="footer-text">
                        CASMS - Car Auto Service Management System - Your trusted partner in auto care since 2010. 
                        Licensed by the Ministry of Transport.
                    </p>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4">
                    <h5 class="footer-title">Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#pricing">Pricing</a></li>
                        <li><a href="#testimonials">Testimonials</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4">
                    <h5 class="footer-title">Our Services</h5>
                    <ul class="footer-links">
                        <li><a href="#">Oil Change</a></li>
                        <li><a href="#">Brake Service</a></li>
                        <li><a href="#">Engine Repair</a></li>
                        <li><a href="#">AC Service</a></li>
                        <li><a href="#">Battery Service</a></li>
                        <li><a href="#">Diagnostics</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-4">
                    <h5 class="footer-title">Contact Information</h5>
                    <ul class="footer-contact">
                        <li><i class="fas fa-map-marker-alt"></i> Mombasa Road, Nairobi, Kenya</li>
                        <li><i class="fas fa-phone"></i> +254 700 123 456</li>
                        <li><i class="fas fa-phone-alt"></i> Emergency: +254 700 999 999</li>
                        <li><i class="fas fa-envelope"></i> info@casms.co.ke</li>
                        <li><i class="fas fa-clock"></i> Mon-Sat: 7:00 AM - 9:00 PM</li>
                        <li><i class="fas fa-clock"></i> 24/7 Emergency Service</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 CASMS. All rights reserved. | License: MOT/TSL/12345/2026</p>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <a href="#" class="back-to-top" id="backToTop">
        <i class="fas fa-arrow-up"></i>
    </a>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 800, once: true, offset: 100 });
        
        window.addEventListener('load', function() {
            document.querySelector('.preloader')?.classList.add('fade-out');
        });
        
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            const backBtn = document.getElementById('backToTop');
            if (window.scrollY > 50) navbar?.classList.add('scrolled');
            else navbar?.classList.remove('scrolled');
            if (window.scrollY > 300) backBtn?.classList.add('show');
            else backBtn?.classList.remove('show');
        });
        
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    </script>
</body>
</html>