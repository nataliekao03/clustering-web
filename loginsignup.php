<?php //login and signup page
require_once 'login.php';

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
    <form method="post" action="loginsignup.php" onSubmit="return validateLogin(this)">
        <table border="0" cellpadding="2" cellspacing="5" bgcolor="#eeeeee">
            <th colspan="2" align="center">Login</th>
        <tr><td>Username</td>
            <td><input type="text" maxlength="128" name="logUsername"></td></tr>
        <tr><td>Password</td>
            <td><input type="password" maxlength="128" name="logPassword"></td></tr>
        <tr><td colspan="2" align="center"><input type="submit" value="Login"></td></tr>
    </table>
</form>
    <form method="post" action="loginsignup.php" onSubmit="return validateSignUp(this)">
        <table border="0" cellpadding="2" cellspacing="5" bgcolor="#eeeeee">
            <th colspan="2" align="center">Signup Form</th>
            <tr><td>Name</td>
                <td><input type="text" maxlength="128" name="name"></td></tr>
            <tr><td>Username</td>
                <td><input type="text" maxlength="128" name="username"></td></tr>
            <tr><td>Email</td>
                <td><input type="text" maxlength="128" name="email"></td></tr>
            <tr><td>Password</td>
            <td><input type="password" maxlength="255" name="password"></td></tr>
            <tr><td colspan="2" align="center"><input type="submit" value="Signup"></td></tr>
        </table>
    </form>
<script>
    function validateName(field){
        return (field == "") ? "No name was entered.\\n": "";
    }

    function validateUsername(field){
        if (field == "") {
            return "No Username was entered.\\n";
        }
        else if (field.length < 5){
            return "Usernames must be at least 5 characters.\\n";
        }
        else if (/[^a-zA-Z0-9_-]/.test(field)){
            return "Only a-z, A-Z, 0-9, - and _ allowed in Usernames.\\n";
        }
        return "";
    }

    function validateEmail(field){
        if(field.trim() == "") return "No Email was entered.\\n";
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
        fail += validateName(form.name.value);
        fail += validateUsername(form.username.value);
        fail += validateEmail(form.email.value);
        fail += validatePassword(form.password.value);

        if (fail === "") return true;
        else { alert(fail); return false; }
    }

    function validateLogin(form){
        var fail = "";
        fail += validateUsername(form.logUsername.value);
        fail += validatePassword(form.logPassword.value);
    
        if (fail === "") return true;
        else { alert(fail); return false; }
    }
</script>

_END;

if (isset($_POST['name']) && isset($_POST['username']) && isset($_POST['email']) && isset($_POST['password'])) {
    $name = sanitizeString($_POST['name']);
    $username = sanitizeString($_POST['username']);
    $email = sanitizeString($_POST['email']);
    $password = sanitizeString($_POST['password']);

    if(searchUsername($username)){
        echo '<script>alert("This username is already taken.");</script>';
    }else{
        if(verify($name, $username, $email, $password)){
            $salt1 = "qm&h*";
            $salt2 = "pg!@";
            $token = hash('ripemd128', $salt1 . $password . $salt2); // Correct token generation
            insertDB($name, $username, $email, $token);

            session_start();
            $_SESSION['username'] = $username;
            $_SESSION['initiated'] = true;
            $conn->close();
            header('Location: home.php');
            exit();

        }
    }
}

if(isset($_POST['logUsername']) && isset($_POST['logPassword'])){
    $username = sanitizeString($_POST['logUsername']);
    $password = sanitizeString($_POST['logPassword']);

    $salt1 = "qm&h*";
    $salt2 = "pg!@";
    $token = hash('ripemd128', $salt1 . $password . $salt2);

    $stmt = $conn->prepare('SELECT * FROM credentials WHERE username=? AND password=?');
    $stmt->bind_param('ss', $username, $token);
    $stmt->execute();
    $results = $stmt->get_result();
    $row = $results->fetch_array(MYSQLI_NUM);
    $stmt->close();

    if ($row && $token==$row[3]){
        session_start();
        $_SESSION['username'] = $username;
        $_SESSION['initiated'] = true;
        $conn->close();
        header("Location: home.php"); 
        exit();
    }else{
        echo "Incorrect Login Information. Please try again.";
    }
}


function insertDB($name, $username, $email, $password){
    global $conn;
    $stmt = $conn->prepare('INSERT INTO credentials VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $name, $username, $email, $password);
    $stmt->execute();
    $stmt->close();
}


function verify($name, $username, $email, $token){
    if ($name == ''){
        return false;
    }elseif($username == '' || (!preg_match('/^[a-zA-Z0-9_-]+$/', $username))){
        return false;
    }elseif($email == '' || !(strpos($email, '.') > 0 && strpos($email, '@') > 0) || preg_match('/[^a-zA-Z0-9.@_-]/', $email)){
        return false;
    }elseif ($token == '' || strlen($token) < 8 || !preg_match('/[a-z]/', $token) || !preg_match('/[A-Z]/', $token) || !preg_match('/[0-9]/', $token)){
        return false;
    }else{
        return true;
    }

}function searchUsername($username){
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM credentials WHERE username=?');
    $stmt->bind_param('s', $username);
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