<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Get category and slug
$category_slug = isset($_GET['category']) ? $_GET['category'] : '';
$video_slug = isset($_GET['slug']) ? $_GET['slug'] : '';

if (empty($category_slug) || empty($video_slug)) {
    header('Location: /video_portal/index.php');
    exit();
}

// Get video details
$query = "SELECT v.*, c.name as category_name,
          COALESCE(v.video_id, '') as embed_video_id,
          COALESCE(v.source, '') as video_source
          FROM videos v 
          LEFT JOIN categories c ON v.category_id = c.id 
          WHERE LOWER(REPLACE(c.name, ' ', '-')) = ? 
          AND LOWER(REPLACE(REPLACE(v.title, ' ', '-'), '.', '-')) = ? 
          AND v.status = 'active'";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $category_slug, $video_slug);
$stmt->execute();
$video = $stmt->get_result()->fetch_assoc();

if (!$video) {
    header('Location: /video_portal/index.php');
    exit();
}

// Get video tags
// $video_tags = get_video_tags($video_id);

// Get related videos
$query = "SELECT v.*, c.name as category_name 
          FROM videos v 
          LEFT JOIN categories c ON v.category_id = c.id 
          WHERE v.id != ? AND v.status = 'active' 
          AND (v.category_id = ? OR v.source = ?)
          ORDER BY RAND()
          LIMIT 4";
$stmt = $conn->prepare($query);
$stmt->bind_param("iis", $video_id, $video['category_id'], $video['source']);
$stmt->execute();
$related_videos = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $video['title']; ?> - Video Portal</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar-brand i {
            color: #ff0000;
        }
        /* Base responsive video container */
        .video-container {
            position: relative;
            overflow: hidden;
            max-width: 100%;
            margin-bottom: 0;
            background-color: #000;
            border-radius: 8px;
        }
        
        /* Responsive video classes with different aspect ratios */
        .video-responsive {
            position: relative;
            overflow: hidden;
            height: 0;
            width: 100%;
        }
        
        /* 16:9 aspect ratio (widescreen) - good for YouTube */
        .video-responsive-16by9 {
            padding-bottom: 56.25%;
        }
        
        /* 4:3 aspect ratio - good for Facebook */
        .video-responsive-4by3 {
            padding-bottom: 75%;
        }
        
        /* 1:1 aspect ratio (square) - good for TikTok */
        .video-responsive-1by1 {
            padding-bottom: 100%;
        }
        
        /* Common iframe styling */
        .video-responsive iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }
        .related-video {
            transition: transform 0.3s;
        }
        .related-video:hover {
            transform: translateY(-5px);
        }
        .related-thumbnail {
            position: relative;
            padding-top: 56.25%;
            background-color: #000;
            border-radius: 4px;
            overflow: hidden;
        }
        .related-thumbnail img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .related-thumbnail .play-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 2rem;
            opacity: 0.8;
            transition: opacity 0.3s;
        }
        .related-video:hover .play-icon {
            opacity: 1;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/video_portal/index.php">
                <i class="fas fa-video me-2"></i>
                Video Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/video_portal/index.php">Home</a>
                    </li>
                </ul>
                <form class="d-flex" action="/video_portal/index.php" method="get">
                    <input class="form-control me-2" type="search" name="search" placeholder="Search videos...">
                    <button class="btn btn-outline-light" type="submit">Search</button>
                </form>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-5">
        <div class="row">
            <!-- Video Player Column -->
            <div class="col-lg-8">
                <!-- Video Player -->
                <div class="card shadow-sm mb-4">
                    <div class="video-container">
                        <?php 
                        if (!empty($video['embed_video_id']) && !empty($video['video_source'])) {
                            echo get_video_embed($video['video_source'], $video['embed_video_id']);
                        } else {
                            echo '<div class="alert alert-warning">Video not available</div>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Video Info -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h4 class="mb-3"><?php echo $video['title']; ?></h4>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <?php if ($video['source'] == 'youtube'): ?>
                                <span class="badge bg-danger"><i class="fab fa-youtube"></i> YouTube</span>
                            <?php elseif ($video['source'] == 'tiktok'): ?>
                                <span class="badge bg-dark"><i class="fab fa-tiktok"></i> TikTok</span>
                            <?php elseif ($video['source'] == 'facebook'): ?>
                                <span class="badge bg-primary"><i class="fab fa-facebook"></i> Facebook</span>
                            <?php endif; ?>

                            <?php if ($video['category_name']): ?>
                                <span class="badge bg-secondary">
                                    <i class="fas fa-folder me-1"></i>
                                    <?php echo $video['category_name']; ?>
                                </span>
                            <?php endif; ?>

                            <span class="badge bg-info">
                                <i class="far fa-clock me-1"></i>
                                <?php echo date('M d, Y', strtotime($video['created_at'])); ?>
                            </span>
                        </div>

                        <?php if (!empty($video_tags)): ?>
                            <div class="mb-3">
                                <i class="fas fa-tags me-2"></i>
                                <?php foreach ($video_tags as $tag): ?>
                                    <a href="index.php?tag=<?php echo $tag['id']; ?>" class="text-decoration-none">
                                        <span class="badge bg-light text-dark"><?php echo $tag['name']; ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Related Videos Column -->
            <div class="col-lg-4">
                <h5 class="mb-3">Related Videos</h5>
                <?php if ($related_videos->num_rows > 0): ?>
                    <?php while ($related = $related_videos->fetch_assoc()): ?>
                        <a href="/video_portal/video/<?php echo $related['id']; ?>/<?php echo strtolower(str_replace(' ', '-', preg_replace('/[^a-zA-Z0-9\s-]/', '', $related['title']))); ?>" class="text-decoration-none text-dark">
                            <div class="card shadow-sm mb-3 related-video">
                                <div class="row g-0">
                                    <div class="col-4">
                                        <div class="related-thumbnail">
                                            <?php if ($related['source'] == 'youtube'): ?>
                                                <img src="https://img.youtube.com/vi/<?php echo $related['video_id']; ?>/mqdefault.jpg" alt="<?php echo $related['title']; ?>">
                                            <?php elseif ($related['source'] == 'tiktok' && !empty($related['thumbnail'])): ?>
                                                <img src="uploads/thumbnails/<?php echo $related['thumbnail']; ?>" alt="<?php echo $related['title']; ?>">
                                            <?php else: ?>
                                                <div class="bg-secondary h-100 d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-video text-white-50"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="play-icon">
                                                <i class="fas fa-play-circle"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-8">
                                        <div class="card-body py-2">
                                            <h6 class="card-title mb-1" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                <?php echo $related['title']; ?>
                                            </h6>
                                            <div class="small text-muted">
                                                <?php if ($related['source'] == 'youtube'): ?>
                                                    <i class="fab fa-youtube text-danger"></i>
                                                <?php elseif ($related['source'] == 'tiktok'): ?>
                                                    <i class="fab fa-tiktok"></i>
                                                <?php elseif ($related['source'] == 'facebook'): ?>
                                                    <i class="fab fa-facebook text-primary"></i>
                                                <?php endif; ?>
                                                <?php echo date('M d, Y', strtotime($related['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        No related videos found.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-video me-2"></i>Video Portal</h5>
                    <p class="small">Watch and share amazing videos from YouTube, TikTok, and Facebook.</p>
                </div>
                <div class="col-md-3">
                    <h6>Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-white-50 text-decoration-none">Home</a></li>
                        <li><a href="about.php" class="text-white-50 text-decoration-none">About Us</a></li>
                        <li><a href="disclaimer.php" class="text-white-50 text-decoration-none">Disclaimer</a></li>
                        <li><a href="/video_portal/admin/login.php" class="text-white-50 text-decoration-none">Admin Login</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6>Follow Us</h6>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-white-50"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-white-50"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-white-50"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-white-50"><i class="fab fa-youtube fa-lg"></i></a>
                    </div>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center text-white-50 small">
                &copy; <?php echo date('Y'); ?> Video Portal. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
