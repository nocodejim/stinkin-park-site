<?php
// admin/edit_song.php
require_once '../includes/functions.php';

// SECURITY NOTE: Authentication/Authorization must be implemented before production use.
// Example: if (!isset($_SESSION['admin_logged_in'])) { header("Location: login.php"); exit; }

$pdo = get_db_connection();

$message = '';
$error = '';
$song_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$song_id) {
    die("Invalid Song ID provided. Please provide a valid song ID.");
}

// Fetch song details
$stmt = db_query($pdo, "SELECT id, title, filename, active FROM songs WHERE id = ?", [$song_id]);
$song = $stmt->fetch();

if (!$song) {
    die("Song not found. The song with ID " . e($song_id) . " does not exist.");
}

// Fetch all tags and current song tags
$all_tags = get_all_tags($pdo);
$stmt = db_query($pdo, "SELECT tag_id FROM song_tags WHERE song_id = ?", [$song_id]);
$current_tags_ids = array_column($stmt->fetchAll(), 'tag_id');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;
    $submitted_tags = $_POST['tags'] ?? [];

    if (empty($title)) {
        $error = "Title cannot be empty. Please provide a title for the song.";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Update song details
            db_query($pdo, "UPDATE songs SET title = ?, active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$title, $active, $song_id]);

            // 2. Sync tags (Delete and Insert) - More robust than separate deletes/inserts for updates
            // Get tags currently in the database for this song
            $existing_song_tags = db_query($pdo, "SELECT tag_id FROM song_tags WHERE song_id = ?", [$song_id])->fetchAll(PDO::FETCH_COLUMN);
            $submitted_tags_int = array_map('intval', $submitted_tags); // Ensure integer types

            // Tags to remove (exist in DB but not in submitted)
            $tags_to_remove = array_diff($existing_song_tags, $submitted_tags_int);
            if (!empty($tags_to_remove)) {
                $placeholders = implode(',', array_fill(0, count($tags_to_remove), '?'));
                db_query($pdo, "DELETE FROM song_tags WHERE song_id = ? AND tag_id IN ($placeholders)", array_merge([$song_id], $tags_to_remove));
            }

            // Tags to add (exist in submitted but not in DB)
            $tags_to_add = array_diff($submitted_tags_int, $existing_song_tags);
            if (!empty($tags_to_add)) {
                $sql_insert = "INSERT INTO song_tags (song_id, tag_id) VALUES (?, ?)";
                $stmt_insert = $pdo->prepare($sql_insert);
                foreach ($tags_to_add as $tag_id) {
                    $stmt_insert->execute([$song_id, $tag_id]);
                }
            }

            $pdo->commit();
            $message = "Song updated successfully!";
            
            // Refresh data in case of successful update
            $song['title'] = $title;
            $song['active'] = $active;
            $current_tags_ids = $submitted_tags_int; // Update the array for display

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to update song: " . $e->getMessage();
            error_log("Error updating song ID " . $song_id . ": " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Song | Admin</title>
    <!-- Basic MVP Styling -->
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            padding: 20px; 
            background-color: #f4f7f6;
            color: #333;
        }
        .container {
            max-width: 800px;
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
        input[type="text"] {
            width: calc(100% - 22px); /* Account for padding and border */
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1em;
        }
        input[type="checkbox"] {
            margin-right: 8px;
            transform: scale(1.2); /* Make checkbox slightly larger */
        }
        .tag-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); 
            gap: 15px; 
            margin-bottom: 20px; 
            border: 1px solid #e0e0e0;
            padding: 15px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .tag-grid label {
            font-weight: normal;
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 5px 0;
            border-radius: 3px;
        }
        .tag-grid label:hover {
            background-color: #eaf2f8;
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
        p strong { color: #555; }

        @media (max-width: 768px) {
            .tag-grid {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            }
            input[type="text"], button[type="submit"] {
                width: 100%;
                box-sizing: border-box;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="manage.php">← Back to Songs List</a>
        <h1>Edit Song: <?php echo e($song['title']); ?></h1>

        <?php if ($message): ?><div class="message"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error"><?php echo e($error); ?></div><?php endif; ?>

        <p><strong>Filename:</strong> <?php echo e($song['filename']); ?></p>

        <form method="POST">
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" value="<?php echo e($song['title']); ?>" required>
            
            <label>
                <input type="checkbox" name="active" <?php echo $song['active'] ? 'checked' : ''; ?>>
                Song is Active (Visible on Public Stations)
            </label>
            <br><br>

            <h2>Manage Tags</h2>
            <div class="tag-grid">
                <?php foreach ($all_tags as $tag): ?>
                    <label style="color: <?php echo e($tag['color_hex']); ?>;">
                        <input type="checkbox" name="tags[]" value="<?php echo $tag['id']; ?>"
                            <?php echo in_array($tag['id'], $current_tags_ids) ? 'checked' : ''; ?>>
                        <?php echo e($tag['name']); ?> (<?php echo e($tag['category']); ?>)
                    </label>
                <?php endforeach; ?>
            </div>

            <button type="submit">Update Song</button>
        </form>
    </div>
</body>
</html>
