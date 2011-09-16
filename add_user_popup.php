<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Database.inc.php");
require_once("./inc/hrm_config.inc.php");
require_once("./inc/Mail.inc.php");
require_once("./inc/Util.inc.php");
require_once("./inc/Validator.inc.php");

session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

$added = False;

/*
 *
 * SANITIZE INPUT
 *   We check the relevant contents of $_POST for validity and store them in
 *   a new array $clean that we will use in the rest of the code.
 *
 *   After this step, only the $clean array and no longer the $_POST array
 *   should be used!
 *
 */

  // Here we store the cleaned variables
  $clean = array(
    "username" => "",
    "email"    => '',
    "group"    => "",
    "pass1"    => "",
    "pass2"    => "",
    "note"     => "" );

  // Username
  if ( isset( $_POST["username"] ) ) {
    if ( Validator::isUsernameValid( $_POST["username"] ) ) {
      $clean["username"] = $_POST["username"];
    }
  }

  // Email
  if ( isset( $_POST["email"] ) ) {
    if ( Validator::isEmailValid( $_POST["email"] ) ) {
      $clean["email"] = $_POST["email"];
    }
  }

  // Group name
  if ( isset( $_POST["group"] ) ) {
    if ( Validator::isGroupNameValid( $_POST["group"] ) ) {
      $clean["group"] = $_POST["group"];
    }
  }

/*
 *
 * END OF SANITIZE INPUT
 *
 */

// TODO refactor from here
if (isset($_POST['add'])) {
  //$user = new User();
  //$user->setName( $clean['username'] );

  if ( $clean["username"] != "" ) {
    if ( $clean["email"] != "" ) {
      if ($clean['group'] != "") {
        $db = new DatabaseConnection();
        // Is the user name already taken?
        if ($db->emailAddress($clean['username']) == "") {
          $password = get_rand_id(8);
          $result = $db->addNewUser( $clean["username"],
                md5($password), $clean["email"], $clean["group"], 'a' );

          // TODO refactor
          if ($result) {
            $text = "Your account has been activated:\n\n";
            $text .= "\t      Username: ".$clean["username"]."\n";
            $text .= "\t      Password: ".$password."\n\n";
            $text .= "Login here\n";
            $text .= $hrm_url."\n\n";
            $folder = $image_folder . "/" . $clean["username"];
            $text .= "Source and destination folders for your images are located on server ".$image_host." under ".$folder.".";
            $mail = new Mail($email_sender);
            $mail->setReceiver($clean['email']);
            $mail->setSubject('Account activated');
            $mail->setMessage($text);
            $mail->send();
            //$user->setName( '' );
            $message = "            <p class=\"warning\">New user successfully added to the system</p>";
            shell_exec("$userManager create \"" . $clean["username"] . "\"" );
            $added = True;
          }
          else $message = "            <p class=\"warning\">Database error, please inform the person in charge</p>";
        }
        else $message = "            <p class=\"warning\">This user name is already in use</p>";
      }
      else $message = "            <p class=\"warning\">Please fill in group field</p>";
    }
    else $message = "            <p class=\"warning\">Please fill in email field with a valid address</p>";
  }
  else $message = "            <p class=\"warning\">Please fill in name field</p>";
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";

?>

<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">

<head>
    <title>Huygens Remote Manager</title>
    <script type="text/javascript">
    <!--
<?php

if ($added) echo "        var added = true;\n";
else echo "        var added = false;\n";

?>
    -->
    </script>
    <style type="text/css">
        @import "stylesheets/default.css";
    </style>
</head>

<body<?php if ($added) echo " onload=\"parent.report()\"" ?>>

<div>

  <form method="post" action="">

    <div id="box">

      <fieldset>

        <legend>account details</legend>

        <div id="adduser">

          <label for="username">Username: </label>
          <input type="text" name="username" id="username" value="" class="texfield" />

          <br />

          <label for="email">E-mail address: </label>
          <input type="text" name="email" id="email" value="" class="texfield" />

          <br />

          <label for="group">Research group: </label>
          <input type="text" name="group" id="group" value="" class="texfield" />

          <br />

          <input name="add" type="submit" value="add" class="button" />

        </div>

      </fieldset>

      <div>
        <input type="button" value="close" onclick="window.close()" />
      </div>

    </div> <!-- box -->

    <div id="notice">
<?php

  print $message;

?>
    </div>

  </form>

</div>

</body>

</html>
