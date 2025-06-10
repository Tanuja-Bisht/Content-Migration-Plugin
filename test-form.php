<?php
/**
 * Test Form for File Upload
 */

// Start session for testing
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Process form submission
$upload_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    $upload_result = 'File received: ' . $_FILES['test_file']['name'] . 
                     ' (Size: ' . $_FILES['test_file']['size'] . ' bytes, Type: ' . $_FILES['test_file']['type'] . ')';
    
    // Store in session for testing
    $_SESSION['test_upload'] = array(
        'filename' => $_FILES['test_file']['name'],
        'size' => $_FILES['test_file']['size'],
        'type' => $_FILES['test_file']['type']
    );
}

// Display session data if available
$session_data = null;
if (isset($_SESSION['test_upload'])) {
    $session_data = 'Session contains: ' . print_r($_SESSION['test_upload'], true);
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Test Form Upload</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .result {
            background-color: #f0f0f0;
            padding: 15px;
            margin: 15px 0;
            border-left: 5px solid #2271b1;
        }
        pre {
            background-color: #f5f5f5;
            padding: 10px;
            border: 1px solid #ddd;
            overflow: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test Form Upload</h1>
        <p>This is a simple test form to verify that file uploads are working correctly.</p>
        
        <?php if ($upload_result): ?>
        <div class="result">
            <h3>Upload Result:</h3>
            <p><?php echo htmlspecialchars($upload_result); ?></p>
        </div>
        <?php endif; ?>
        
        <?php if ($session_data): ?>
        <div class="result">
            <h3>Session Data:</h3>
            <pre><?php echo htmlspecialchars($session_data); ?></pre>
        </div>
        <?php endif; ?>
        
        <h2>Upload Test Form</h2>
        <form method="post" enctype="multipart/form-data" action="">
            <p>
                <label for="test_file">Select a file:</label><br>
                <input type="file" name="test_file" id="test_file">
            </p>
            <p>
                <button type="submit">Upload File</button>
            </p>
        </form>
        
        <h2>Debug Information</h2>
        <div class="result">
            <h3>Request Method:</h3>
            <p><?php echo $_SERVER['REQUEST_METHOD']; ?></p>
            
            <h3>POST Data:</h3>
            <pre><?php echo htmlspecialchars(print_r($_POST, true)); ?></pre>
            
            <h3>FILES Data:</h3>
            <pre><?php echo htmlspecialchars(print_r($_FILES, true)); ?></pre>
            
            <h3>Session ID:</h3>
            <p><?php echo session_id(); ?></p>
        </div>
    </div>
</body>
</html> 