<?php
// add-business.php
require_once 'config.php';

$message = ""; // Variable to hold success or error messages

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Safely grab form data
    $name = $_POST['name'] ?? '';
    $category = $_POST['category'] ?? '';
    $address = $_POST['address'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $logo = $_POST['logo'] ?? '';
    $description = $_POST['description'] ?? '';

    // Default values for new submissions
    $status = 'pending';
    $tier = 'free';

    // Prepare the SQL statement using placeholders (?) for security
    $stmt = $conn->prepare("INSERT INTO businesses (name, category, description, tier, status, logo, address, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    if ($stmt) {
        // Bind the parameters to the placeholders (the "s" means string)
        $stmt->bind_param("ssssssss", $name, $category, $description, $tier, $status, $logo, $address, $phone);

        // Execute the statement
        if ($stmt->execute()) {
            $message = "<p style='color: var(--accent-color); font-weight: bold; text-align: center;'>Application submitted successfully! It is pending review.</p>";
        } else {
            $message = "<p style='color: red; text-align: center;'>Error saving application. Please try again.</p>";
        }
        $stmt->close();
    } else {
        $message = "<p style='color: red; text-align: center;'>Database error: Could not prepare statement.</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add a Business | Recovery Business Hub</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <header>
        <nav class="navbar">
            <a href="index.php" class="logo">Recovery Business Hub</a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="add-business.php" class="btn-primary">Add a Business</a></li>
            </ul>
        </nav>
    </header>

    <main class="form-container">
        <h2>Apply to Join the Directory</h2>
        <p>Submit your business details below. All submissions are reviewed before being published.</p>

        <?php echo $message; ?>

        <form action="add-business.php" method="POST" class="business-form">
            <div class="form-group">
                <label for="name">Business Name *</label>
                <input type="text" id="name" name="name" required placeholder="e.g. Second Chance Cafe">
            </div>

            <div class="form-group">
                <label for="category">Category *</label>
                <select id="category" name="category" required>
                    <option value="">Select a Category...</option>
                    <option value="Food & Dining">Food & Dining</option>
                    <option value="Home Services">Home Services</option>
                    <option value="Retail">Retail</option>
                    <option value="Automotive">Automotive</option>
                    <option value="Consulting">Consulting</option>
                </select>
            </div>

            <div class="form-group">
                <label for="address">Street Address</label>
                <input type="text" id="address" name="address" placeholder="e.g. 123 Main St, Richmond, IN">
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" placeholder="e.g. (765) 555-0101">
            </div>

            <div class="form-group">
                <label for="logo">Logo Image URL</label>
                <input type="url" id="logo" name="logo" placeholder="https://example.com/logo.jpg">
                <small>For now, please provide a link to an image of your logo.</small>
            </div>

            <div class="form-group">
                <label for="description">Business Description *</label>
                <textarea id="description" name="description" rows="4" required placeholder="Tell us about your business and your mission..."></textarea>
            </div>

            <button type="submit" class="btn-submit">Submit Application</button>
        </form>
    </main>

    <footer>
        <p>&copy; 2026 Recovery Business Hub. All rights reserved.</p>
    </footer>

</body>

</html>