<?php
// index.php - Public Landing Page for Clients, incorporating the full HTML template.

$page_title = "Welcome to Elegance Salon";

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if a staff user is logged in. If so, redirect them to the dashboard.
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Connect to the database
// NOTE: Make sure 'db_connect.php' is in the same directory as index.php
require_once 'db_connect.php'; 

// Fetch all active services
$services = [];
$sql_services = "SELECT name, description, duration_minutes, price FROM services WHERE is_active = TRUE ORDER BY name ASC";
$result_services = $conn->query($sql_services);

if ($result_services) {
    $services = $result_services->fetch_all(MYSQLI_ASSOC);
}

// Set the base path for assets, assuming this file is in the root directory
$asset_path = 'assets/'; 
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Elegance Salon online booking and management" />
        <meta name="author" content="" />
        <title><?php echo $page_title; ?></title>
        <link rel="icon" type="image/x-icon" href="<?php echo $asset_path; ?>favicon.ico" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css?family=Merriweather+Sans:400,700" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css?family=Merriweather:400,300,300italic,400italic,700,700italic" rel="stylesheet" type="text/css" />
        <link href="https://cdnjs.cloudflare.com/ajax/libs/SimpleLightbox/2.1.0/simpleLightbox.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" /> 
    
    </head>
    <body id="page-top">
        <nav class="navbar navbar-expand-lg navbar-light fixed-top py-3" id="mainNav">
            <div class="container px-4 px-lg-5">
                <a class="navbar-brand" href="#page-top">Elegance Salon</a>
                <button class="navbar-toggler navbar-toggler-right" type="button" data-bs-toggle="collapse" data-bs-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
                <div class="collapse navbar-collapse" id="navbarResponsive">
                    <ul class="navbar-nav ms-auto my-2 my-lg-0">
                        <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                        <li class="nav-item"><a class="nav-link" href="#services">Services</a></li>
                        <li class="nav-item"><a class="nav-link" href="#portfolio">Gallery</a></li>
                        <li class="nav-item"><a class="nav-link btn btn-outline-primary" href="public_booking.php">Book Now</a></li>
                        <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <li class="nav-item"><a class="nav-link" href="login.php">Staff Login</a></li>
                        <?php else: ?>
                            <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
        <header class="masthead">
            <div class="container px-4 px-lg-5 h-100">
                <div class="row gx-4 gx-lg-5 h-100 align-items-center justify-content-center text-center">
                    <div class="col-lg-10 align-self-end">
                        <h1 class="text-white font-weight-bold">Elegance Salon: Your Beauty Destinations</h1>
                        <hr class="divider" />
                    </div>
                    <div class="col-lg-8 align-self-baseline">
                        <p class="text-white-75 mb-5">Ready for your next transformation? Book your appointment online today!</p>
                        <a class="btn btn-primary btn-xl" href="public_booking.php">Book an Appointment Now!</a>
                    </div>
                </div>
            </div>
        </header>
        <section class="page-section bg-primary" id="about">
            <div class="container px-4 px-lg-5">
                <div class="row gx-4 gx-lg-5 justify-content-center">
                    <div class="col-lg-8 text-center">
                        <h2 class="text-white mt-0">We've got what you need!</h2>
                        <hr class="divider divider-light" />
                        <p class="text-white-75 mb-4">Our team of expert stylists are dedicated to providing you with the highest quality services, from classic cuts to modern coloring techniques. We use the best products and latest trends!</p>
                        <a class="btn btn-light btn-xl" href="#services">See Our Services</a>
                    </div>
                </div>
            </div>
        </section>
        <section class="page-section" id="services">
            <div class="container px-4 px-lg-5">
                <h2 class="text-center mt-0">Our Available Services</h2>
                <hr class="divider" />
                
                <?php if (empty($services)): ?>
                    <p class="alert alert-warning text-center">No services are currently listed. Please check back later!</p>
                <?php else: ?>
                    <div class="row gx-4 gx-lg-5">
                        <?php foreach ($services as $service): ?>
                            <div class="col-lg-3 col-md-6 text-center">
                                <div class="mt-5">
                                    <div class="mb-2"><i class="bi-scissors fs-1 text-primary"></i></div> 
                                    <h3 class="h4 mb-2"><?php echo htmlspecialchars($service['name']); ?></h3>
                                    <p class="text-muted mb-1"><?php echo htmlspecialchars($service['description']); ?></p>
                                    <p class="text-dark mb-0">
                                        **$<?php echo number_format($service['price'], 2); ?>** | <?php echo $service['duration_minutes']; ?> min
                                    </p>
                                    <a href="public_booking.php" class="btn btn-sm btn-outline-primary mt-2">Book</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <div id="portfolio">
        <div class="container-fluid p-0">
            <div class="row g-0">
                <div class="col-lg-4 col-sm-6">
                    <a class="portfolio-box" href="assets/img/portfolio/fullsize/1.jpg" title="Project Name">
                        <img class="img-fluid" src="assets/img/1.jpg" alt="Classic Haircut" />
                        <div class="portfolio-box-caption">
                            <div class="project-category text-white-50">Hair Styling</div>
                            <div class="project-name">Classic Haircuts</div>
                        </div>
                    </a>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <a class="portfolio-box" href="assets/img/portfolio/fullsize/2.jpg" title="Project Name">
                        <img class="img-fluid" src="assets/img/2.jpg" alt="Party Makeup Look" />
                        <div class="portfolio-box-caption">
                            <div class="project-category text-white-50">Beauty</div>
                            <div class="project-name">Party Makeup Look</div>
                        </div>
                    </a>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <a class="portfolio-box" href="assets/img/portfolio/fullsize/3.jpg" title="Project Name">
                        <img class="img-fluid" src="assets/img/3.jpg" alt="Beard Trim and Shape" />
                        <div class="portfolio-box-caption">
                            <div class="project-category text-white-50">Men’s Care</div>
                            <div class="project-name">Beard Trim and Shape</div>
                        </div>
                    </a>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <a class="portfolio-box" href="assets/img/portfolio/fullsize/4.jpg" title="Project Name">
                        <img class="img-fluid" src="assets/img/4.jpg" alt="Deep Clean Facial" />
                        <div class="portfolio-box-caption">
                            <div class="project-category text-white-50">Skin Care</div>
                            <div class="project-name">Deep Clean Facial</div>
                        </div>
                    </a>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <a class="portfolio-box" href="assets/img/portfolio/fullsize/5.jpg" title="Project Name">
                        <img class="img-fluid" src="assets/img/5.jpg" alt="Clean and Polish" />
                        <div class="portfolio-box-caption">
                            <div class="project-category text-white-50">Hands Care</div>
                            <div class="project-name">Clean and Polish</div>
                        </div>
                    </a>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <a class="portfolio-box" href="assets/img/portfolio/fullsize/6.jpg" title="Project Name">
                        <img class="img-fluid" src="assets/img/6.jpg" alt="Modern Hair Color" />
                        <div class="portfolio-box-caption p-3">
                            <div class="project-category text-white-50">Styling</div>
                            <div class="project-name">Modern Hair Color</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
        <section class="page-section bg-dark text-white">
            <div class="container px-4 px-lg-5 text-center">
                <h2 class="mb-4">Ready for your transformation?</h2>
                <a class="btn btn-light btn-xl" href="public_booking.php">Book Your Appointment Now!</a>
            </div>
        </section>
        <section class="page-section" id="contact">
            <div class="container px-4 px-lg-5">
                <div class="row gx-4 gx-lg-5 justify-content-center">
                    <div class="col-lg-8 col-xl-6 text-center">
                        <h2 class="mt-0">Let's Get In Touch!</h2>
                        <hr class="divider" />
                        <p class="text-muted mb-5">Have questions before booking? Send us a message and we will get back to you as soon as possible!</p>
                    </div>
                </div>
                <div class="row gx-4 gx-lg-5 justify-content-center mb-5">
                    <div class="col-lg-6">
                        <form id="contactForm" data-sb-form-api-token="API_TOKEN">
                            <div class="form-floating mb-3">
                                <input class="form-control" id="name" type="text" placeholder="Enter your name..." data-sb-validations="required" />
                                <label for="name">Full name</label>
                                <div class="invalid-feedback" data-sb-feedback="name:required">A name is required.</div>
                            </div>
                            <div class="form-floating mb-3">
                                <input class="form-control" id="email" type="email" placeholder="name@example.com" data-sb-validations="required,email" />
                                <label for="email">Email address</label>
                                <div class="invalid-feedback" data-sb-feedback="email:required">An email is required.</div>
                                <div class="invalid-feedback" data-sb-feedback="email:email">Email is not valid.</div>
                            </div>
                            <div class="form-floating mb-3">
                                <input class="form-control" id="phone" type="tel" placeholder="(123) 456-7890" data-sb-validations="required" />
                                <label for="phone">Phone number</label>
                                <div class="invalid-feedback" data-sb-feedback="phone:required">A phone number is required.</div>
                            </div>
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="message" type="text" placeholder="Enter your message here..." style="height: 10rem" data-sb-validations="required"></textarea>
                                <label for="message">Message</label>
                                <div class="invalid-feedback" data-sb-feedback="message:required">A message is required.</div>
                            </div>
                            <div class="d-none" id="submitSuccessMessage">
                                <div class="text-center mb-3">
                                    <div class="fw-bolder">Form submission successful!</div>
                                </div>
                            </div>
                            <div class="d-none" id="submitErrorMessage"><div class="text-center text-danger mb-3">Error sending message!</div></div>
                            <div class="d-grid"><button class="btn btn-primary btn-xl disabled" id="submitButton" type="submit">Submit</button></div>
                        </form>
                    </div>
                </div>
                <div class="row gx-4 gx-lg-5 justify-content-center">
                    <div class="col-lg-4 text-center mb-5 mb-lg-0">
                        <i class="bi-phone fs-2 mb-3 text-muted"></i>
                        <div>+1 (555) 123-4567</div>
                    </div>
                </div>
            </div>
        </section>
        <footer class="bg-light py-5">
            <div class="container px-4 px-lg-5"><div class="small text-center text-muted">Copyright &copy; <?php echo date("Y"); ?> - Elegance Salon</div></div>
        </footer>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/SimpleLightbox/2.1.0/simpleLightbox.min.js"></script>
        <script src="<?php echo $asset_path; ?>js/scripts.js"></script>
        <script src="https://cdn.startbootstrap.com/sb-forms-latest.js"></script>
    </body>
</html>
<?php 
// Close the database connection
$conn->close();
?>