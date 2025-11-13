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
            // sanitize filename
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

// determine which image to show (use default if missing)
$default_public = 'uploads/pro.png';
$default_server = __DIR__ . '/' . $default_public;

$user_pic = !empty($user['profile_pic']) ? ltrim($user['profile_pic'], '/') : '';
$user_pic_server = $user_pic ? (__DIR__ . '/' . $user_pic) : '';

if ($user_pic && file_exists($user_pic_server) && is_readable($user_pic_server)) {
    $display_pic = $user_pic;
} elseif (file_exists($default_server) && is_readable($default_server)) {
    $display_pic = $default_public;
} else {
    // fallback external avatar if uploads/pro.png missing
    $display_pic = 'https://cdn-icons-png.flaticon.com/512/847/847969.png';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ensure overlay sits on top of the image container */
        .avatar-wrapper { display: inline-block; position: relative; }
        .avatar-overlay { position: absolute; bottom: 0; left: 0; transform: translate(25%, 25%); }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-5">
        <div class="row">
            <!-- Profile picture with camera icon at bottom-left -->
            <div class="col-md-4 text-center">
                <div class="avatar-wrapper">
                    <!-- Avatar clickable for upload -->
                    <form method="POST" enctype="multipart/form-data" id="avatarForm">
                        <label for="profilePicInput" style="cursor: pointer; display:inline-block;">
                            <img src="<?php echo htmlspecialchars($display_pic, ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="Profile"
                                 class="rounded-circle border border-3 border-light shadow-sm"
                                 width="180" height="180"
                                 style="object-fit: cover; background-color: #f8f9fa; display:block;">
                        </label>
                        <input type="file" name="profile_pic" id="profilePicInput"
                               class="d-none" accept="image/*" onchange="document.getElementById('avatarForm').submit()">
                    </form>

                    <!-- Camera icon overlay at bottom-left (also triggers file input) -->
                    <label for="profilePicInput"
                           class="avatar-overlay bg-light rounded-circle p-2 shadow-sm"
                           style="cursor:pointer; z-index:2;">
                        <i class="fas fa-camera"></i>
                    </label>
                </div>

                <h4 class="mt-3"><?php echo htmlspecialchars($user['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"><h5>About Me</h5></div>
                    <div class="card-body">
                        <p><?php echo nl2br(htmlspecialchars($user['bio'] ?: 'No bio.')); ?></p>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header"><h5>Update Profile</h5></div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label>Bio</label>
                                <textarea name="bio" class="form-control" rows="4"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label>Profile Picture (JPG/PNG, &lt;2MB)</label>
                                <input type="file" name="profile_pic" class="form-control" accept="image/*">
                            </div>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
