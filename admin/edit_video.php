<?php
// Include session
require_once '../includes/session.php';

// Get video ID
$video_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($video_id == 0) {
    set_message('Invalid video ID', 'danger');
    redirect('videos.php');
}

// Get video details
$query = "SELECT * FROM videos WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $video_id);
$stmt->execute();
$video = $stmt->get_result()->fetch_assoc();

if (!$video) {
    set_message('Video not found', 'danger');
    redirect('videos.php');
}

// Get video tags
$video_tags = get_video_tags($video_id);
$video_tag_ids = array_map(function($tag) {
    return $tag['id'];
}, $video_tags);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = isset($_POST['title']) ? sanitize($_POST['title']) : '';
    $video_link = isset($_POST['video_link']) ? sanitize($_POST['video_link']) : '';
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $status = isset($_POST['status']) ? sanitize($_POST['status']) : 'active';
    $tags = isset($_POST['tags']) ? $_POST['tags'] : [];
    
    // Validate input
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'Title is required';
    }
    
    if (empty($video_link)) {
        $errors[] = 'Video link is required';
    } else if (!filter_var($video_link, FILTER_VALIDATE_URL)) {
        $errors[] = 'Please enter a valid URL';
    }
    
    // Extract video ID from URL
    $new_video_id = extract_video_id($video_link, $video['source']);
    if (!$new_video_id) {
        $errors[] = 'Invalid ' . ucfirst($video['source']) . ' video URL. Please check the URL format.';
    }
    
    // Handle thumbnail for TikTok videos
    $thumbnail = $video['thumbnail'];
    if ($video['source'] == 'tiktok' && isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == 0) {
        $new_thumbnail = upload_thumbnail($_FILES['thumbnail']);
        if ($new_thumbnail) {
            // Delete old thumbnail
            if (!empty($video['thumbnail'])) {
                @unlink("../uploads/thumbnails/" . $video['thumbnail']);
            }
            $thumbnail = $new_thumbnail;
        } else {
            $errors[] = 'Error uploading thumbnail. Please try again.';
        }
    }
    
    // If no errors, update video
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update video
            $query = "UPDATE videos SET title = ?, video_id = ?, thumbnail = ?, category_id = ?, status = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssssi", $title, $new_video_id, $thumbnail, $category_id, $status, $video_id);
            $stmt->execute();
            
            // Delete existing tags
            $query = "DELETE FROM video_tags WHERE video_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $video_id);
            $stmt->execute();
            
            // Insert new tags
            if (!empty($tags)) {
                foreach ($tags as $tag_id) {
                    $query = "INSERT INTO video_tags (video_id, tag_id) VALUES (?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ii", $video_id, $tag_id);
                    $stmt->execute();
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Set success message
            set_message('Video updated successfully!');
            
            // Redirect to videos page
            redirect('videos.php');
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
}

// Get categories
$categories = get_categories();

// Get tags
$tags = get_tags();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Video - Video Portal</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background-color: #4e73df;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 5px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0">
                <div class="d-flex flex-column p-3">
                    <a href="dashboard.php" class="d-flex align-items-center mb-3 text-decoration-none text-white">
                        <i class="fas fa-video me-2"></i>
                        <h4 class="mb-0">Video Portal</h4>
                    </a>
                    <hr>
                    <ul class="nav nav-pills flex-column mb-auto">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="videos.php" class="nav-link active">
                                <i class="fas fa-film"></i> Videos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="add_video.php" class="nav-link">
                                <i class="fas fa-plus-circle"></i> Add Video
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="categories.php" class="nav-link">
                                <i class="fas fa-tags"></i> Categories
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="profile.php" class="nav-link">
                                <i class="fas fa-user"></i> Profile
                            </a>
                        </li>
                    </ul>
                    <hr>
                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-2 fs-5"></i>
                            <strong><?php echo $_SESSION['full_name']; ?></strong>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Sign out</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-edit me-2"></i>Edit Video</h2>
                    <a href="videos.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to List
                    </a>
                </div>
                
                <?php display_message(); ?>
                
                <?php if (isset($errors) && !empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="card shadow">
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $video_id); ?>" method="post" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Title</label>
                                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($video['title']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="video_link" class="form-label">Video Link</label>
                                        <input type="text" class="form-control" id="video_link" name="video_link" value="<?php 
                                            // Reconstruct URL based on source and video_id
                                            $url = '';
                                            switch($video['source']) {
                                                case 'youtube':
                                                    $url = 'https://www.youtube.com/watch?v=' . $video['video_id'];
                                                    break;
                                                case 'tiktok':
                                                    $url = 'https://www.tiktok.com/@username/video/' . $video['video_id'];
                                                    break;
                                                case 'facebook':
                                                    $url = 'https://www.facebook.com/watch/?v=' . $video['video_id'];
                                                    break;
                                            }
                                            echo htmlspecialchars($url); 
                                        ?>" required>
                                        <div class="form-text">Enter the URL of the <?php echo ucfirst($video['source']); ?> video</div>
                                    </div>
                                    
                                    <?php if ($video['source'] == 'tiktok'): ?>
                                        <div class="mb-3">
                                            <label for="thumbnail" class="form-label">Custom Thumbnail</label>
                                            <input type="file" class="form-control" id="thumbnail" name="thumbnail" accept="image/*">
                                            <?php if (!empty($video['thumbnail'])): ?>
                                                <div class="mt-2">
                                                    <img src="../uploads/thumbnails/<?php echo htmlspecialchars($video['thumbnail']); ?>" alt="Current thumbnail" class="img-thumbnail" style="max-width: 200px;">
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="category_id" class="form-label">Category</label>
                                        <select class="form-select" id="category_id" name="category_id">
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>" <?php echo $category['id'] == $video['category_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="tags" class="form-label">Tags</label>
                                        <select class="form-select" id="tags" name="tags[]" multiple>
                                            <?php foreach ($tags as $tag): ?>
                                                <option value="<?php echo $tag['id']; ?>" <?php echo in_array($tag['id'], $video_tag_ids) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($tag['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="active" <?php echo $video['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $video['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Update Video
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2 for tags
            $('#tags').select2({
                theme: 'bootstrap-5',
                placeholder: 'Select tags',
                allowClear: true
            });
        });
    </script>
</body>
</html>
