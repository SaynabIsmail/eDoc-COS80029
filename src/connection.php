<?php

    $database= new mysqli("db", "edoc_user", "edoc_pass", "edoc_local");
    if ($database->connect_error){
        die("Connection failed:  ".$database->connect_error);
    }

?>
