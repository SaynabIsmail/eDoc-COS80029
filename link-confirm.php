 <?php session_start();
    if (empty($_SESSION['pending_link_email'])) {
        header('Location: login.php');
        exit;
    }
    $email = htmlspecialchars($_SESSION['pending_link_email']); ?>
 <!DOCTYPE html>
 <html>

 <head>
     <link rel="stylesheet" href="css/main.css">
     <link rel="stylesheet" href="css/login.css">
     <title>Confirm Account Link</title>
 </head>

 <body>
     <center>
         <div class="container" style="max-width: 500px; margin-top: 80px;">
             <p class="header-text">Link your Google account?</p>
             <p class="sub-text"> An account already exists for <b><?php echo $email; ?></b>.<br><br> Do you want to link your Google account to it? Next time, you'll be able to sign in with either your password or Google. </p> <br> <a href="link-confirm-action.php?confirm=yes" class="non-style-link"> <button class="login-btn btn-primary btn" style="width: 100%; margin-bottom: 10px;">Yes, link my account</button> </a> <a href="link-confirm-action.php?confirm=no" class="non-style-link"> <button class="login-btn btn-primary-soft btn" style="width: 100%;">No, cancel</button> </a>
         </div>
     </center>
 </body>

 </html>