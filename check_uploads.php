<?php
echo "<h2>Checking Uploads Folder</h2>";

$upload_dir = 'uploads/';
echo "<p>Looking for folder: " . realpath($upload_dir) . "</p>";

// Check if folder exists
if (file_exists($upload_dir)) {
    echo "<p style='color: green;'>✅ Folder EXISTS</p>";
    
    // Check if it's a directory
    if (is_dir($upload_dir)) {
        echo "<p style='color: green;'>✅ Is a DIRECTORY</p>";
    } else {
        echo "<p style='color: red;'>❌ Not a directory</p>";
    }
    
    // Check permissions
    if (is_writable($upload_dir)) {
        echo "<p style='color: green;'>✅ Folder is WRITABLE</p>";
    } else {
        echo "<p style='color: red;'>❌ Folder is NOT writable</p>";
    }
    
    // Check current permissions
    $perms = fileperms($upload_dir);
    echo "<p>Folder permissions: " . decoct($perms & 0777) . "</p>";
    
} else {
    echo "<p style='color: red;'>❌ Folder does NOT exist</p>";
    
    // Try to create it
    if (mkdir($upload_dir, 0755, true)) {
        echo "<p style='color: green;'>✅ Created folder successfully</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to create folder</p>";
    }
}

// Test writing a file
$test_file = $upload_dir . 'test.txt';
if (file_put_contents($test_file, 'test')) {
    echo "<p style='color: green;'>✅ Can WRITE files to folder</p>";
    unlink($test_file); // Clean up
} else {
    echo "<p style='color: red;'>❌ Cannot write files to folder</p>";
}
?>