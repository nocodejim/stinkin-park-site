<?php
// admin/edit_station.php
require_once '../includes/functions.php';

// SECURITY NOTE: Authentication required.
// Example: if (!isset($_SESSION['admin_logged_in'])) { header("Location: login.php"); exit; }

$pdo = get_db_connection();

$message = '';
$error = '';
$station_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$station_id) {
    die("Invalid Station ID provided. Please provide a valid station ID.");
}

// Fetch station details
$stmt = db_query($pdo, "SELECT * FROM stations WHERE id = ?", [$station_id]);
$station = $stmt->fetch();

if (!$station) {
    die("Station not found. The station with ID " . e($station_id) . " does not exist.");
}

// Fetch all tags and current rules
$all_tags = get_all_tags($pdo);
$current_rules = get_station_tag_rules($pdo, $station_id);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;
    $auto_play = isset($_POST['auto_play']) ? 1 : 0;
    $background_type = $_POST['background_type'] ?? 'default';
    $background_file = trim($_POST['background_file'] ?? '');
    $tag_rules_input = $_POST['tag_rules'] ?? [];

    // Validation
    if (empty($name) || empty($slug)) {
        $error = "Name and Slug cannot be empty. Both fields are required.";
    } elseif (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        $error = "Slug must be lowercase letters, numbers, and hyphens only (e.g., 'my-station-name').";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Update station details
            $sql = "UPDATE stations SET name = ?, slug = ?, active = ?, auto_play = ?, background_type = ?, background_file = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            db_query($pdo, $sql, [$name, $slug, $active, $auto_play, $background_type, $background_file, $station_id]);

            // 2. Sync tag rules (delete existing and insert new ones)
            db_query($pdo, "DELETE FROM station_tags WHERE station_id = ?", [$station_id]);
            
            $valid_requirements = ['required', 'optional', 'excluded'];
            $sql_insert = "INSERT INTO station_tags (station_id, tag_id, requirement_type) VALUES (?, ?, ?)";
            $stmt_insert = $pdo->prepare($sql_insert);

            foreach ($tag_rules_input as $tag_id => $requirement_type) {
                // Only insert if the requirement type is valid and not 'none'
                if (in_array($requirement_type, $valid_requirements)) {
                    $stmt_insert->execute([$station_id, (int)$tag_id, $requirement_type]);
                }
            }

            $pdo->commit();
            $message = "Station updated successfully!";

            // Refresh data to show latest changes after update
            $stmt = db_query($pdo, "SELECT * FROM stations WHERE id = ?", [$station_id]);
            $station = $stmt->fetch();
            $current_rules = get_station_tag_rules($pdo, $station_id);

        } catch (PDOException $e) {
            $pdo->rollBack();
            // Handle duplicate slug (Integrity constraint violation - MySQL error code 23000)
            if ($e->getCode() == '23000') {
                $error = "Failed to update station: The slug '{$slug}' is already in use by another station. Please choose a unique slug.";
            } else {
                $error = "Failed to update station. A system error occurred: " . $e->getMessage();
            }
            error_log("Error updating station ID " . $station_id . ": " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Station | Admin</title>
    <!-- Basic MVP Styling -->
     <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            padding: 20px; 
            background-color: #f4f7f6;
            color: #333;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        h1, h2 { 
            color: #34495e; 
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-top: 30px;
        }
        h1 { font-size: 1.8em; }
        h2 { font-size: 1.4em; }
        .message { 
            color: #28a745; 
            background-color: #d4edda; 
            border: 1px solid #c3e6cb; 
            padding: 10px; 
            border-radius: 5px; 
            margin-bottom: 20px;
        } 
        .error { 
            color: #dc3545; 
            background-color: #f8d7da; 
            border: 1px solid #f5c6cb; 
            padding: 10px; 
            border-radius: 5px; 
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        input[type="text"], select {
            width: calc(100% - 22px);
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1em;
        }
        input[type="checkbox"] {
            margin-right: 8px;
            transform: scale(1.2);
            margin-bottom: 15px;
        }
        .checkbox-group {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .rules-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px; 
            margin-top: 20px; 
        }
        .rule-item { 
            border: 1px solid #e0e0e0; 
            padding: 15px; 
            border-radius: 8px; 
            background-color: #f9f9f9;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
        }
        .rule-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .rule-item h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #34495e;
            font-size: 1.1em;
        }
        .rule-item label {
            display: flex;
            align-items: center;
            font-weight: normal;
            margin-bottom: 10px;
            cursor: pointer;
        }
        .rule-item input[type="radio"] {
            margin-right: 10px;
            transform: scale(1.1);
        }
        button[type="submit"] {
            background-color: #28a745;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1em;
            transition: background-color 0.3s ease, transform 0.1s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        button[type="submit"]:hover {
            background-color: #218838;
            transform: translateY(-1px);
        }
        a {
            color: #007bff;
            text-decoration: none;
            margin-bottom: 20px;
            display: inline-block;
        }
        a:hover {
            text-decoration: underline;
        }
     </style>
</head>
<body>
    <div class="container">
        <a href="stations.php">← Back to Stations List</a>
        <h1>Edit Station: <?php echo e($station['name']); ?></h1>

        <?php if ($message): ?><div class="message"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error"><?php echo e($error); ?></div><?php endif; ?>

        <form method="POST">
            <label for="name">Station Name:</label>
            <input type="text" id="name" name="name" value="<?php echo e($station['name']); ?>" required>

            <label for="slug">Station Slug (for URL, e.g., 'rock-anthems'):</label>
            <input type="text" id="slug" name="slug" value="<?php echo e($station['slug']); ?>" required>

            <label for="background_type">Background Type:</label>
            <select id="background_type" name="background_type">
                <option value="default" <?php echo $station['background_type'] == 'default' ? 'selected' : ''; ?>>Default (No specific media)</option>
                <option value="image" <?php echo $station['background_type'] == 'image' ? 'selected' : ''; ?>>Image</option>
                <option value="video" <?php echo $station['background_type'] == 'video' ? 'selected' : ''; ?>>Video</option>
            </select>

            <label for="background_file">Background File (filename in `/assets/media/` directory):</label>
            <input type="text" id="background_file" name="background_file" value="<?php echo e($station['background_file']); ?>" placeholder="e.g., my_station_bg.mp4 or bg_image.jpg">

            <div class="checkbox-group">
                <label>
                    <input type="checkbox" name="active" <?php echo $station['active'] ? 'checked' : ''; ?>> Station is Active (Visible to Public)
                </label>
                <label>
                    <input type="checkbox" name="auto_play" <?php echo $station['auto_play'] ? 'checked' : ''; ?>> Auto-play Station on Load (Browser Policy Permitting)
                </label>
            </div>
            
            <h2>Tag Rules (How songs are selected for this station)</h2>
            <p>Define whether songs must have (Required), may have (Optional), or must not have (Excluded) certain tags to be included in this station's playlist. 'None' means the tag doesn't affect this station.</p>
            <div class="rules-grid">
                <?php foreach ($all_tags as $tag): 
                    $rule = $current_rules[$tag['id']] ?? 'none';
                ?>
                    <div class="rule-item" style="border-color: <?php echo e($tag['color_hex']); ?>;">
                        <h4><?php echo e($tag['name']); ?> <small>(<?php echo e($tag['category']); ?>)</small></h4>
                        <label><input type="radio" name="tag_rules[<?php echo $tag['id']; ?>]" value="required" <?php echo $rule == 'required' ? 'checked' : ''; ?>> Required</label>
                        <label><input type="radio" name="tag_rules[<?php echo $tag['id']; ?>]" value="optional" <?php echo $rule == 'optional' ? 'checked' : ''; ?>> Optional</label>
                        <label><input type="radio" name="tag_rules[<?php echo $tag['id']; ?>]" value="excluded" <?php echo $rule == 'excluded' ? 'checked' : ''; ?>> Excluded</label>
                        <label><input type="radio" name="tag_rules[<?php echo $tag['id']; ?>]" value="none" <?php echo $rule == 'none' ? 'checked' : ''; ?>> None</label>
                    </div>
                <?php endforeach; ?>
            </div>
            <br>
            <button type="submit">Update Station</button>
        </form>
    </div>
</body>
</html>
