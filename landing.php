<?php
$pageTitle = "I-Record | Office of the Vice President for Research and Extension";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="icon" type="image/jpeg" href="assets/img/ovpre-logo.jpg?v=20261012">

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/landing.css?v=20261005-campus-bg">
</head>

<body>

<!-- =========================
     NAVIGATION
========================= -->
<header class="header">
    <div class="container nav-container">

        <nav class="navbar">
            <a href="#home">Home</a>
            <a href="#about">About</a>
            <a href="#features">Features</a>
            <a href="#contact">Contact</a>
        </nav>

    </div>
</header>


<!-- =========================
     HERO SECTION
========================= -->
<section id="home" class="hero">

    <div class="container hero-content">

        <div class="hero-text">

            <span class="welcome-text">
                <img src="assets/img/dlsp-seal.jpg" alt="Dalubhasaan ng Lungsod ng San Pablo seal">
                <img src="assets/img/ovpre-logo.jpg" alt="Pamantasan ng Lungsod ng San Pablo OVPRE seal">
                OFFICE OF THE VICE PRESIDENT FOR RESEARCH AND EXTENSION
            </span>

            <h1>
                Welcome to <span>I-Record</span>
            </h1>

            <h2>
                Web-Based Record Management System
            </h2>

            <p>
                A centralized platform designed to organize, manage,
                store, and retrieve research and extension records
                efficiently and securely.
            </p>

            <div class="hero-buttons">

                <a href="index.php" class="primary-btn">
                    Get Started
                </a>

                <a href="#about" class="secondary-btn">
                    Learn More
                </a>

            </div>

        </div>


    </div>

</section>


<!-- =========================
     FEATURES SECTION
========================= -->
<section id="features" class="features-section">

    <div class="container">

        <div class="section-heading">

            <span>WHAT WE OFFER</span>

            <h2>System Features</h2>

            <p>
                I-Record provides essential tools for efficient
                research and extension record management.
            </p>

        </div>


        <div class="features-grid">

            <div class="feature-card">

                <div class="feature-icon">📁</div>

                <h3>Record Management</h3>

                <p>
                    Organize and manage research and extension
                    documents in one centralized system.
                </p>

            </div>


            <div class="feature-card">

                <div class="feature-icon">🔍</div>

                <h3>Easy Retrieval</h3>

                <p>
                    Quickly search and access important records
                    whenever they are needed.
                </p>

            </div>


            <div class="feature-card">

                <div class="feature-icon">🔐</div>

                <h3>Secure Access</h3>

                <p>
                    Protect records through authorized user access
                    and secure account management.
                </p>

            </div>


            <div class="feature-card">

                <div class="feature-icon">📊</div>

                <h3>Reports & Monitoring</h3>

                <p>
                    Monitor research and extension activities and
                    generate useful reports.
                </p>

            </div>

        </div>

    </div>

</section>


<!-- =========================
     ABOUT SECTION
========================= -->
<section id="about" class="about-section">

    <div class="container about-content">

        <div class="about-image">

            <div class="about-box">

                <span class="big-icon">🗂️</span>

                <h3>Centralized Records</h3>

                <p>
                    Organized. Accessible. Secure.
                </p>

            </div>

        </div>


        <div class="about-text">

            <span class="section-label">
                ABOUT I-RECORD
            </span>

            <h2>
                Simplifying Research and Extension Records
            </h2>

            <p>
                I-Record is a web-based record management system
                developed for the Office of the Vice President for
                Research and Extension.
            </p>

            <p>
                The system provides a centralized platform for
                storing, organizing, monitoring, and retrieving
                digital research and extension records.
            </p>

            <p>
                It aims to reduce manual paperwork, prevent misplaced
                records, improve document organization, and make
                information retrieval faster and more efficient.
            </p>

        </div>

    </div>

</section>


<!-- =========================
     CONTACT SECTION
========================= -->
<section id="contact" class="contact-section">

    <div class="container contact-content">

        <div>

            <span class="section-label">
                GET IN TOUCH
            </span>

            <h2>
                Office of the Vice President for
                Research and Extension
            </h2>

            <p>
                For inquiries and assistance regarding research,
                extension activities, and records management,
                please contact the office.
            </p>

        </div>


        <div class="contact-card">

            <div class="contact-item">

                <span>📍</span>

                <div>
                    <strong>Office</strong>

                    <p>
                        Pamantasan ng Lungsod ng San Pablo
                    </p>
                </div>

            </div>


            <div class="contact-item">

                <span>✉️</span>

                <div>
                    <strong>Email</strong>

                    <p>
                        OVP Research and Extension Office
                    </p>
                </div>

            </div>

        </div>

    </div>

</section>


<!-- =========================
     FOOTER
========================= -->
<footer class="footer">

    <div class="container footer-content">

        <div>

            <h3>I-Record</h3>

            <p>
                Web-Based Record Management System
            </p>

        </div>


        <div>

            <p>
                © <?php echo date("Y"); ?> I-Record.
                All Rights Reserved.
            </p>

        </div>

    </div>

</footer>

</body>
</html>
