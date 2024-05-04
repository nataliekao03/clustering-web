<?php
require_once 'clustering_login.php';

$conn = new mysqli($hn, $un, $pw, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo <<<_END
<html>
    <head>
        <title>Login/Signup Page</title>
        <style>
        .signup {
            border:1px solid #999999; font: normal 14px helvetica; color: #444444;
        }
    </style>
    </head>
<body>
    <form method="post" action="a6_secondpage.php" onSubmit="return validateLogin(this)">
        <table border="0" cellpadding="2" cellspacing="5" bgcolor="#eeeeee">
            <th colspan="2" align="center">Login</th>
        <tr><td>Username</td>
            <td><input type="text" maxlength="9" name="logUsername"></td></tr>
        <tr><td>Password</td>
            <td><input type="password" maxlength="128" name="logPassword"></td></tr>
        <tr><td colspan="2" align="center"><input type="submit" value="Login"></td></tr>
    </table>
</form>
    <form method="post" action="a6_secondpage.php" onSubmit="return validateSignUp(this)">
        <table border="0" cellpadding="2" cellspacing="5" bgcolor="#eeeeee">
            <th colspan="2" align="center">Signup Form</th>
            <tr><td>Name</td>
                <td><input type="text" maxlength="128" name="studentName"></td></tr>
            <tr><td>Username</td>
                <td><input type="text" maxlength="9" name="username"></td></tr>
            <tr><td>Email</td>
                <td><input type="text" maxlength="128" name="studentEmail"></td></tr>
            <tr><td>Password</td>
            <td><input type="password" maxlength="128" name="studentPassword"></td></tr>
            <tr><td colspan="2" align="center"><input type="submit" value="Signup"></td></tr>
        </table>
    </form>
<script>
    function validateName(field){
        return (field == "") ? "No name was entered<br>": "";
    }

    function validateUsername(field){
        if (field.trim() === "") {
            return "No username was entered.\\n";
        }
        else if (!/^[a-zA-Z0-9_-]+$/.test(field))
            return "Username can only contain English letters, numbers, and characters '_' and '-'.\\n";
        return "";
    }

    function validateEmail(field){
        if(field.trim() == "") return "No Email was entered<br>";
        else if (!((field.indexOf(".") > 0)  && (field.indexOf("@") > 0)) || /[^a-zA-Z0-9.@_-]/.test(field))
        return "The Email address is invalid.\\n";
        return "";
    }

    function validatePassword(field) {
        if (field.trim() === "") return "No Password was entered.\\n";
        else if (field.length < 6)
            return "Passwords must be at least 6 characters.\\n";
        else if (!/[a-z]/.test(field) || !/[A-Z]/.test(field) || !/[0-9]/.test(field))
            return "Passwords require one each of a-z, A-Z and 0-9.\\n";
        return "";
    }

    function validateSignUp(form){
        var fail = "";
        fail += validateName(form.studentName.value);
        fail += validateUsername(form.username.value);
        fail += validateEmail(form.studentEmail.value);
        fail += validatePassword(form.studentPassword.value);

        if (fail === "") return true;
        else { alert(fail); return false; }
    }

    function validateLogin(form){
        var fail = "";
        fail += validateUsername(form.logID.value);
        fail += validatePassword(form.logUsername.value);
    
        if (fail === "") return true;
        else { alert(fail); return false; }
    }
</script>

_END;

if (isset($_POST['studentName']) && isset($_POST['studentID']) && isset($_POST['studentEmail']) && isset($_POST['studentPassword'])) {
    $name = sanitizeString($_POST['studentName']);
    $studentID = sanitizeString($_POST['studentID']);
    $email = sanitizeString($_POST['studentEmail']);
    $password = sanitizeString($_POST['studentPassword']);

    if(searchID($studentID)){
        echo '<script>alert("This ID is already taken.");</script>';
    }else{
        if(verify($name, $studentID, $email, $password)){
            $salt1 = "qm&h*";
            $salt2 = "pg!@";
            $token = hash('ripemd128', $salt1 . $password . $salt2); // Correct token generation
            insertDB($name, $studentID, $email, $token);

            session_start();
            $_SESSION['studentID'] = $studentID;
            $_SESSION['initiated'] = true;
            $conn->close();
            header('Location: a6_firstpage.php');
            exit();

        }
    }
}



if(isset($_POST['logID']) && isset($_POST['logPassword'])){
    $id = sanitizeString($_POST['logID']);
    $password = sanitizeString($_POST['logPassword']);

    $salt1 = "qm&h*";
    $salt2 = "pg!@";
    $token = hash('ripemd128', $salt1 . $password . $salt2); 

    $stmt = $conn->prepare('SELECT * FROM credentials where id=? AND password=?');
    $stmt->bind_param('is', $id, $token);
    $stmt->execute();
    $results = $stmt->get_result();
    $row = $results->fetch_array(MYSQLI_NUM);
    $stmt->close();

    if ($token==$row[3]){
        session_start();
        $_SESSION['id'] = $id;
        $_SESSION['initiated'] = true;
        $conn->close();
        header("Location: a6_firstpage.php"); 
        exit();
    }else{
        echo "Incorrect Login Information. Please try again.";
    }
}

function insertDB($name, $studentID, $email, $password){
    global $conn;
    $stmt = $conn->prepare('INSERT INTO credentials VALUES(?,?,?,?)');
    $stmt->bind_param('siss', $name, $studentID, $email, $password);
    $stmt->execute();
    $stmt->close();
}

function verify($name, $studentID, $email, $token){
    if ($name == ''){
        return false;
    }elseif($studentID == '' || !is_numeric($studentID) || $studentID < 0 || strlen($studentID) > 9){
        return false;
    }elseif($email == '' || !(strpos($email, '.') > 0 && strpos($email, '@') > 0) || preg_match('/[^a-zA-Z0-9.@_-]/', $email)){
        return false;
    }elseif ($token == '' || strlen($token) < 8 || !preg_match('/[a-z]/', $token) || !preg_match('/[A-Z]/', $token) || !preg_match('/[0-9]/', $token)){
        return false;
    }else{
        return true;
    }

}

function searchID($studentID){
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM credentials WHERE id=?');
    $stmt->bind_param('i', $studentID);
    $stmt->execute();
    $results = $stmt->get_result();
    $exists = $results->num_rows > 0;
    $stmt->close();
    return $exists;

}

function sanitizeString($var) {
    $var = stripslashes($var);
    $var = strip_tags($var);
    $var = htmlentities($var);
    return $var;
}
?>