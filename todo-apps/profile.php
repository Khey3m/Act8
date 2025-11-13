<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

include 'db.php';

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Ensure uploads directory exists
$uploads_dir = __DIR__ . '/uploads';
if (!is_dir($uploads_dir)) {
    @mkdir($uploads_dir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bio = trim($_POST['bio'] ?? '');
    $profile_pic = $user['profile_pic'];

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_pic'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png']) && $file['size'] < 2000000) {
            $base = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($file['name']));
            $new_name = 'uploads/' . time() . '_' . bin2hex(random_bytes(4)) . '_' . $base;
            $target = __DIR__ . '/' . $new_name;
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $profile_pic = $new_name;
            }
        }
    }

    $update = $pdo->prepare("UPDATE users SET bio = ?, profile_pic = ? WHERE id = ?");
    $update->execute([$bio, $profile_pic, $user_id]);
    header("Location: profile.php");
    exit;
}

// Determine display picture
$default_public = 'uploads/pro.png';
$default_server = __DIR__ . '/' . $default_public;

$user_pic = !empty($user['profile_pic']) ? ltrim($user['profile_pic'], '/') : '';
$user_pic_server = $user_pic ? (__DIR__ . '/' . $user_pic) : '';

if ($user_pic && file_exists($user_pic_server) && is_readable($user_pic_server)) {
    $display_pic = $user_pic;
} elseif (file_exists($default_server) && is_readable($default_server)) {
    $display_pic = $default_public;
} else {
    $display_pic = 'https://cdn-icons-png.flaticon.com/512/847/847969.png';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            padding: 2.5rem;
            margin-top: 2rem;
            margin-bottom: 2rem;
        }

        .avatar-wrapper {
            position: relative;
            display: inline-block;
            margin-bottom: 1.5rem;
        }

        .avatar-img {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            object-fit: cover;
            border: 6px solid #fff;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease;
            background-color: #f8f9fa;
        }

        .avatar-img:hover {
            transform: scale(1.05);
        }

        .avatar-overlay {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: #4361ee;
            color: white;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.4);
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .avatar-overlay:hover {
            background: #3a56d4;
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.5);
        }

        .username {
            font-size: 1.8rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 1.5rem;
        }

        .card-header frst-child {
            background: #4361ee;
            color: white;
            font-weight: 600;
            border-radius: 14px 14px 0 0 !important;
            padding: 1rem 1.5rem;
            font-size: 1.1rem;
        }

        .card-header {
            background: #4361ee;
            color: white;
            font-weight: 600;
            border-radius: 14px 14px 0 0 !important;
            padding: 1rem 1.5rem;
            font-size: 1.1rem;
        }

        .card-body {
            padding: 1.8rem;
        }

        .card-body p {
            color: #555;
            line-height: 1.7;
            margin: 0;
        }

        .form-label {
            font-weight: 500;
            color: #2c3e50;
        }

        .form-control, .form-control:focus {
            border-radius: 10px;
            border: 1.5px solid #dee2e6;
            padding: 0.75rem 1rem;
            box-shadow: none;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.2);
        }

        .btn-primary {
            background: #4361ee;
            border: none;
            border-radius: 12px;
            padding: 0.75rem 2rem;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
        }

        .btn-primary:hover {
            background: #3a56d4;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
        }

        .text-muted {
            color: #6c757d !important;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
                margin: 1rem;
            }
            .avatar-img {
                width: 140px;
                height: 140px;
            }
            .username {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="row g-4">
            <!-- Profile Picture & Name -->
            <div class="col-md-4 text-center">
                <div class="avatar-wrapper">
                    <!-- Hidden form for instant upload on image click -->
                    <form method="POST" enctype="multipart/form-data" id="avatarForm" class="d-inline">
                        <label for="profilePicInput" style="cursor: pointer;">
                            <img src="<?php echo htmlspecialchars($display_pic, ENT_QUOTES); ?>"
                                 alt="Profile Picture"
                                 class="avatar-img">
                        </label>
                        <input type="file" name="profile_pic" id="profilePicInput"
                               class="d-none" accept="image/*" 
                               onchange="this.form.submit()">
                    </form>

                    <!-- Camera icon overlay -->
                    <label for="profilePicInput" class="avatar-overlay">
                        <i class="fas fa-camera"></i>
                    </label>
                </div>

                <h3 class="username"><?php echo htmlspecialchars($user['username'] ?? 'User'); ?></h3>
            </div>

            <!-- Bio & Update Form -->
            <div class="col-md-8">
                <!-- Current Bio -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user me-2"></i> About Me
                    </div>
                    <div class="card-body">
                        <p><?php echo nl2br(htmlspecialchars($user['bio'] ?: '<em class="text-muted">No bio yet. Click "Edit" to add one.</em>')); ?></p>
                    </div>
                </div>

                <!-- Update Form -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-edit me-2"></i> Update Profile
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Bio</label>
                                <textarea name="bio" class="form-control" rows="4" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Profile Picture (JPG/PNG, less than 2MB)</label>
                                <input type="file" name="profile_pic" class="form-control" accept="image/*">
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>