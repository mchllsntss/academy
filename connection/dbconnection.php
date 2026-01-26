<?php

   $db_server ="127.0.0.1";
   $db_user ="root";
   $db_pass ="";
   $db_name = "academy";
   $conn ="";

   
   $conn = mysqli_connect($db_server, $db_user, $db_pass, $db_name);

   // Check connection
   if (!$conn) {
      // Log the error and display a generic message to the user
      error_log('Connection failed: ' . mysqli_connect_error());
      die("Could not connect to the database. Please try again later.");
   }
   ?>