<?php
ob_start();
session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"] == "" || $_SESSION['usertype'] != 'a') {
    header("location: ../login.php");
    exit;
}

include("../connection.php");

$error = "";
$success = "";

if ($_POST) {
    $name = trim($_POST['docname']);
    $email = trim($_POST['docemail']);
    $nic = trim($_POST['docnic']);
    $tel = trim($_POST['doctel']);
    $specialty = intval($_POST['specialties']);

    $check = $database->prepare("SELECT * FROM webuser WHERE email=?");
    $check->bind_param("s", $email);
    $check->execute();
    $existing = $check->get_result();

    if ($existing->num_rows == 1) {
        $error = "An account with this email already exists.";
    } else {
        $temp_password = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789"), 0, 10);

        $insert = $database->prepare("INSERT INTO doctor (docemail, docname, docpassword, docnic, doctel, specialties) VALUES (?, ?, ?, ?, ?, ?)");
        $insert->bind_param("sssssi", $email, $name, $temp_password, $nic, $tel, $specialty);
        $insert->execute();

        $insert2 = $database->prepare("INSERT INTO webuser (email, usertype) VALUES (?, 'd')");
        $insert2->bind_param("s", $email);
        $insert2->execute();

        $success = "Doctor account created. Share these temporary login details with them securely (not by unencrypted email):";
        $created_email = $email;
        $created_password = $temp_password;
    }
}

$specialties_list = $database->query("SELECT id, sname FROM specialties ORDER BY sname");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Doctor | eDoc</title>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .form-box { max-width: 480px; margin: 50px auto; padding: 30px; }
        .form-box input, .form-box select { width: 100%; padding: 10px; margin-bottom: 15px; box-sizing: border-box; }
        .error-text { color: #e02424; }
        .success-box { background: #eafbea; border: 1px solid #2f9e44; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .cred-box { font-family: monospace; background: #f4f4f4; padding: 10px; border-radius: 6px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="form-box">
        <p><a href="index.php">&larr; Back to dashboard</a></p>
        <h2>Add New Doctor</h2>
        <?php if ($success != ""): ?>
            <div class="success-box">
                <p><?php echo $success; ?></p>
                <div class="cred-box">
                    Email: <?php echo htmlspecialchars($created_email); ?><br>
                    Temporary password: <?php echo htmlspecialchars($created_password); ?>
                </div>
            </div>
            <p><a href="add-doctor.php">Add another doctor</a> | <a href="index.php">Back to dashboard</a></p>
        <?php else: ?>
            <?php if ($error != ""): ?><p class="error-text"><?php echo $error; ?></p><?php endif; ?>
            <form method="POST">
                <label>Full name</label>
                <input type="text" name="docname" required>
                <label>Email</label>
                <input type="email" name="docemail" required>
                <label>NIC number</label>
                <input type="text" name="docnic" required>
                <label>Phone number</label>
                <input type="text" name="doctel" required>
                <label>Specialty</label>
                <select name="specialties" required>
                    <?php while ($row = $specialties_list->fetch_assoc()): ?>
                        <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['sname']); ?></option>
                    <?php endwhile; ?>
                </select>
                <input type="submit" value="Create Doctor Account" class="login-btn btn-primary btn" style="width:100%;">
            </form>
        <?php endif; ?>
    </div>
</body>
</html>